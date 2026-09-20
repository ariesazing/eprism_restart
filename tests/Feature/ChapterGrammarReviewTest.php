<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\SubmissionSection;
use App\Models\User;
use App\Services\SubmissionAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The read-only grammar review for chapters edited in ONLYOFFICE, which can't underline grammar as
 * you type — see App\Services\ChapterGrammarReview.
 */
class ChapterGrammarReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.languagetool.url' => 'http://languagetool.test']);
    }

    private function docx(array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $text) {
            $body .= '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p>';
        }

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private function chapter(User $owner, ?array $paragraphs = null, EditorEngine $engine = EditorEngine::ONLYOFFICE, string $key = 'context_and_rationale'): array
    {
        $submission = ResearchSubmission::where('researcher_id', $owner->id)->first() ?? $owner->submissions()->create([
            'title' => 'Study', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => $engine,
        ]);

        $section = $submission->sections()->create(['section_key' => $key, 'label' => 'Chapter I. Context and Rationale', 'type' => 'rich_text', 'sort_order' => 0]);

        if ($paragraphs !== null) {
            $path = "onlyoffice-documents/{$submission->id}/{$key}.docx";
            Storage::disk('local')->put($path, $this->docx($paragraphs));
            $section->update(['onlyoffice_path' => $path, 'onlyoffice_key' => 'k']);
        }

        return [$submission, $section->fresh()];
    }

    private function url(ResearchSubmission $submission, SubmissionSection $section): string
    {
        return route('submissions.sections.grammar-review', [$submission, $section]);
    }

    private function fakeLanguageTool(?callable $matchesFor = null): void
    {
        Http::fake(['languagetool.test/*' => function (Request $request) use ($matchesFor) {
            $text = $request['text'] ?? '';

            return Http::response(['matches' => $matchesFor ? $matchesFor($text) : []]);
        }]);
    }

    private function match(int $offset, int $length, array $replacements = ['their'], string $type = 'grammar'): array
    {
        return [
            'message' => 'Possible grammar problem.', 'shortMessage' => 'Grammar', 'offset' => $offset, 'length' => $length,
            'replacements' => array_map(fn ($value) => ['value' => $value], $replacements),
            'context' => ['text' => 'a very long context sentence that the review does not need', 'offset' => 0, 'length' => 1],
            'rule' => ['id' => 'THERE_THEIR', 'issueType' => $type, 'category' => ['id' => 'CONFUSED_WORDS', 'name' => 'Commonly Confused Words']],
        ];
    }

    public function test_it_reviews_the_saved_docx_text_and_returns_slimmed_matches(): void
    {
        $owner = User::factory()->create();
        [$submission, $section] = $this->chapter($owner, ['Chapter I. Context and Rationale', 'Students dont like there homework.', 'A second paragraph.']);
        $this->fakeLanguageTool(fn () => [$this->match(9, 4, ['do not', "don't"], 'grammar')]);

        $response = $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk();

        // The seeded chapter-title heading isn't the researcher's writing — not part of the review.
        $response->assertJsonPath('available', true)
            ->assertJsonPath('truncated', false)
            ->assertJsonPath('incomplete', false)
            ->assertJsonPath('issue_count', 1)
            ->assertJsonPath('chunks.0.paragraphs', ['Students dont like there homework.', 'A second paragraph.'])
            ->assertJsonPath('chunks.0.matches.0', [
                'offset' => 9, 'length' => 4, 'message' => 'Possible grammar problem.', 'short' => 'Grammar',
                'type' => 'grammar', 'category' => 'Commonly Confused Words', 'suggestions' => ['do not', "don't"],
            ]);

        // Only what the review draws: LanguageTool's context sentence and rule internals are dropped.
        $this->assertArrayNotHasKey('context', $response->json('chunks.0.matches.0'));
        $this->assertNotNull($response->json('saved_at'));
        $this->assertSame(8, $response->json('word_count'));

        // One request, the paragraphs joined by a blank line — the offsets the front end slices by.
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['text'] === "Students dont like there homework.\n\nA second paragraph.");
    }

    public function test_a_long_chapter_is_checked_in_pieces_and_each_pieces_offsets_stay_relative_to_it(): void
    {
        $owner = User::factory()->create();
        $paragraphs = array_map(fn ($n) => "Paragraph {$n} ".str_repeat('word ', 780), range(1, 3)); // ~3,900 chars each
        [$submission, $section] = $this->chapter($owner, $paragraphs);
        $this->fakeLanguageTool(fn (string $text) => [$this->match(0, 9)]);

        $response = $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk();

        // 3 x ~3,900 chars can't share a 6,000-char request, so each goes alone...
        Http::assertSentCount(3);
        $response->assertJsonCount(3, 'chunks')->assertJsonPath('issue_count', 3);
        // ...and every match's offset is relative to its own chunk (0), not the whole chapter.
        foreach (range(0, 2) as $i) {
            $response->assertJsonPath("chunks.{$i}.matches.0.offset", 0);
        }
    }

    public function test_an_unavailable_checker_still_returns_the_text_and_says_so(): void
    {
        config(['services.languagetool.url' => null]);
        $owner = User::factory()->create();
        [$submission, $section] = $this->chapter($owner, ['Some perfectly saved text.']);

        $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('issue_count', 0)
            ->assertJsonPath('chunks.0.paragraphs', ['Some perfectly saved text.']);
    }

    public function test_a_checker_that_fails_partway_gives_a_partial_review_not_a_clean_one(): void
    {
        $owner = User::factory()->create();
        $paragraphs = array_map(fn ($n) => "Paragraph {$n} ".str_repeat('word ', 780), range(1, 3));
        [$submission, $section] = $this->chapter($owner, $paragraphs);

        $calls = 0;
        Http::fake(['languagetool.test/*' => function () use (&$calls) {
            return ++$calls === 1 ? Http::response(['matches' => [$this->match(0, 9)]]) : Http::response('boom', 500);
        }]);

        $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('incomplete', true)
            ->assertJsonPath('issue_count', 1);
    }

    public function test_a_chapter_with_no_saved_text_returns_an_empty_review_without_calling_the_checker(): void
    {
        $owner = User::factory()->create();
        [$submission, $section] = $this->chapter($owner, ['Chapter I. Context and Rationale']); // just the seeded title
        Http::fake();

        $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk()
            ->assertJsonPath('chunks', [])
            ->assertJsonPath('issue_count', 0)
            ->assertJsonPath('word_count', 0);

        Http::assertNothingSent();
    }

    public function test_a_chapter_that_was_never_opened_falls_back_to_its_html_and_has_no_saved_time(): void
    {
        $owner = User::factory()->create();
        [$submission, $section] = $this->chapter($owner, null, EditorEngine::CANVAS_EDITOR);
        $section->update(['content_html' => '<p>First paragraph.</p><p>Second one.</p>']);
        $this->fakeLanguageTool();

        $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk()
            ->assertJsonPath('saved_at', null)
            ->assertJsonPath('chunks.0.paragraphs', ['First paragraph.', 'Second one.']);
    }

    /**
     * The readiness (SRAM) grammar score used to read each chapter's content_html — for an
     * ONLYOFFICE chapter only a lagging mirror of its saved .docx, so a chapter the researcher had
     * really written could score as empty. It now reads the same saved text the review does.
     */
    public function test_the_readiness_grammar_score_reads_the_saved_docx_not_a_stale_html_mirror(): void
    {
        $owner = User::factory()->create();
        [$submission, $section] = $this->chapter($owner, ['Chapter I. Context and Rationale', 'The teachers recieved training in six schools.']);
        // The mirror never caught up (a failed conversion): blank, or stale text that was since deleted.
        $section->update(['content_html' => '<p>Old text that is no longer in the chapter at all.</p>']);
        $this->fakeLanguageTool(fn () => [$this->match(13, 8)]);

        $result = app(SubmissionAssessmentService::class)->assess($submission->fresh());

        $this->assertSame(7, $result['word_count']);
        $this->assertSame(1, $result['issue_count']);
        Http::assertSent(fn (Request $request) => $request['text'] === 'The teachers recieved training in six schools.');
    }

    public function test_an_enormous_chapter_is_capped(): void
    {
        $owner = User::factory()->create();
        // 30 paragraphs of ~3,000 chars = 90,000 chars, past the 60,000 ceiling.
        [$submission, $section] = $this->chapter($owner, array_map(fn ($n) => "P{$n} ".str_repeat('word ', 600), range(1, 30)));
        $this->fakeLanguageTool();

        $response = $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk()->assertJsonPath('truncated', true);

        $shown = array_sum(array_map(fn ($chunk) => count($chunk['paragraphs']), $response->json('chunks')));
        $this->assertLessThan(30, $shown);
        $this->assertGreaterThan(10, $shown);
    }

    public function test_it_is_limited_to_the_owner_and_to_real_rich_text_chapters(): void
    {
        $owner = User::factory()->create();
        [$submission, $section] = $this->chapter($owner, ['Some text.']);
        $table = $submission->sections()->create(['section_key' => 'work_plan_and_timelines', 'label' => 'Plan', 'type' => 'table', 'sort_order' => 1]);
        $otherOwner = User::factory()->create();
        [$theirSubmission, $theirSection] = $this->chapter($otherOwner, ['Their text.']);
        $this->fakeLanguageTool();

        foreach ([User::factory()->create(), User::factory()->admin()->create(), User::factory()->reviewer()->create()] as $intruder) {
            $this->actingAs($intruder)->getJson($this->url($submission, $section))->assertForbidden();
        }

        // A chapter of someone else's submission, or a table chapter, or a mismatched pair — never.
        $this->actingAs($owner)->getJson($this->url($submission, $table))->assertNotFound();
        $this->actingAs($owner)->getJson($this->url($submission, $theirSection))->assertNotFound();
        $this->actingAs($owner)->getJson($this->url($submission, $section))->assertOk();
        $this->assertNotNull($theirSubmission);
    }

    public function test_the_whole_manuscript_engine_has_no_per_chapter_review(): void
    {
        $owner = User::factory()->create();
        [$submission, $section] = $this->chapter($owner, ['Some text.'], EditorEngine::ONLYOFFICE_MANUSCRIPT);

        $this->actingAs($owner)->getJson($this->url($submission, $section))->assertNotFound();
    }

    public function test_the_button_and_modal_are_on_onlyoffice_chapter_pages_only(): void
    {
        config(['services.onlyoffice.enabled' => true, 'services.onlyoffice.url' => 'https://office.test', 'services.onlyoffice.jwt_secret' => str_repeat('s', 40)]);
        $owner = User::factory()->create();
        $onlyoffice = $owner->submissions()->create(['title' => 'A', 'research_type' => 'basic', 'classification' => 'proposal', 'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE]);
        $canvas = $owner->submissions()->create(['title' => 'B', 'research_type' => 'basic', 'classification' => 'proposal', 'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::CANVAS_EDITOR]);
        $locked = $owner->submissions()->create(['title' => 'C', 'research_type' => 'basic', 'classification' => 'proposal', 'status' => SubmissionStatus::UNDER_REVIEW, 'editor_engine' => EditorEngine::ONLYOFFICE]);

        // ONLYOFFICE: the button sits under Save now, before chapter 1, and the modal knows every rich_text chapter.
        $this->actingAs($owner)->get(route('submissions.chapters', $onlyoffice))->assertOk()
            ->assertSeeInOrder(['data-manual-save-button', 'data-autosave-status', 'data-grammar-review-button', 'Check grammar', 'data-wizard-chapter="0"'], false)
            ->assertSee('x-data="grammarReview(', false)
            // The chapter list handed to the component (JSON inside the attribute) carries each
            // chapter's key and review URL.
            ->assertSee('context_and_rationale', false)
            ->assertSee('grammar-review', false);

        // Canvas keeps its live squiggles — no button. A locked submission has no editor at all.
        $this->actingAs($owner)->get(route('submissions.chapters', $canvas))->assertOk()
            ->assertDontSee('data-grammar-review-button', false)->assertDontSee('grammarReview(', false);
        $this->actingAs($owner)->get(route('submissions.chapters', $locked))->assertOk()
            ->assertDontSee('data-grammar-review-button', false)->assertDontSee('grammarReview(', false);
    }
}
