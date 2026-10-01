<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Jobs\RunSimilarityCheck;
use App\Models\ResearchSubmission;
use App\Models\SimilarityCheck;
use App\Models\User;
use App\Similarity\SimilarityCheckRunner;
use App\Similarity\SimilarityReportBuilder;
use App\Similarity\Tokenizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimilarityCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_repository_documents_match_without_external_requests(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $submission = $this->makeSubmission($owner, ['status' => SubmissionStatus::APPROVED, 'approved_at' => now()]);
        $submission->snapshots()->create(['version' => 1, 'path' => 'self.pdf', 'generated_by' => $owner->id,
            'generated_at' => now()->subMinute(), 'similarity_text' => self::INTRO]);
        $this->makeSubmission(User::factory()->create(), ['title' => 'Unapproved matching draft']);
        $this->repositorySources([['title' => 'Approved source']]);
        $check = $this->runCheck($owner, $submission);
        $this->assertSame(['repository'], $check->matches->pluck('source_type')->all());
        $this->assertSame(['Approved source (completed)'], $check->matches->pluck('title')->all());
        $this->assertNull($check->matches->first()->url);
        $this->assertGreaterThan(0, $check->score);
        Http::assertNothingSent();
    }

    public function test_promoted_proposal_uses_approved_snapshot_not_the_new_draft(): void
    {
        $owner = User::factory()->create();
        $submission = $this->makeSubmission($owner);
        $source = $this->makeSubmission(User::factory()->create(), ['proposal_approved_at' => now()->subDay(),
            'classification' => 'completed', 'title' => 'Approved proposal'], '<p>Unrelated changed draft</p>');
        $source->snapshots()->create(['version' => 1, 'path' => 'proposal.pdf', 'generated_by' => $source->researcher_id,
            'generated_at' => now()->subDays(2), 'similarity_text' => self::PASSAGE]);
        $source->snapshots()->create(['version' => 2, 'path' => 'draft.pdf', 'generated_by' => $source->researcher_id,
            'generated_at' => now(), 'similarity_text' => 'Unrelated new draft']);
        $check = $this->runCheck($owner, $submission);
        $this->assertSame(['Approved proposal (proposal)'], $check->matches->pluck('title')->all());
    }

    public function test_empty_repository_reports_limited_coverage_and_short_sentences_still_match(): void
    {
        $owner = User::factory()->create();
        $submission = $this->makeSubmission($owner, [], str_repeat('<p>These nine simple words describe classroom teaching and learning.</p>', 8));
        $empty = $this->runCheck($owner, $submission);
        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $empty->status);
        $this->assertNotEmpty($empty->warnings);
        $this->repositorySources([['title' => 'Short sentences']]);
        $source = ResearchSubmission::where('title', 'Short sentences')->firstOrFail();
        $source->snapshots()->first()->update(['similarity_text' => 'These nine simple words describe classroom teaching and learning.']);
        $check = $this->runCheck($owner, $submission);
        $this->assertGreaterThan(0, $check->score);
    }

    public function test_legacy_pdf_is_extracted_locally_and_cached_encrypted(): void
    {
        $this->repositorySources([['title' => 'Legacy PDF']]);
        $source = ResearchSubmission::where('title', 'Legacy PDF')->firstOrFail();
        $snapshot = $source->snapshots()->first();
        $snapshot->update(['similarity_text' => null]);
        Storage::disk('local')->put($snapshot->path, Crypt::encrypt('%PDF-1.4 fixture'));
        Process::fake(['*' => Process::result(output: self::PASSAGE)]);
        $owner = User::factory()->create();
        $check = $this->runCheck($owner, $this->makeSubmission($owner));
        $this->assertCount(1, $check->matches);
        $this->assertSame(self::PASSAGE, $snapshot->fresh()->similarity_text);
        $this->assertStringNotContainsString(self::PASSAGE, DB::table('research_snapshots')->where('id', $snapshot->id)->value('similarity_text'));
        Http::assertNothingSent();
    }

    public function test_manuscript_repository_source_reads_the_approved_docx_not_the_working_copy(): void
    {
        $source = $this->makeSubmission(User::factory()->create(), ['title' => 'Approved manuscript',
            'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
            'status' => SubmissionStatus::APPROVED, 'classification' => 'completed', 'approved_at' => now(),
            'manuscript' => ['working_path' => 'missing-working.docx']]);
        $path = 'approved.docx';
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($path), \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.self::PASSAGE.'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $source->manuscriptVersions()->create(['attempt' => (string) Str::uuid(),
            'created_by' => $source->researcher_id, 'state' => 'ready', 'approved_at' => now(),
            'docx_path' => $path, 'docx_hash' => str_repeat('a', 64), 'template_path' => 'template.docx',
            'template_hash' => str_repeat('b', 64), 'metadata' => ['classification' => 'completed'], 'attachments' => []]);
        $owner = User::factory()->create();
        $check = $this->runCheck($owner, $this->makeSubmission($owner));
        $this->assertSame(['Approved manuscript (completed)'], $check->matches->pluck('title')->all());
    }

    private const PASSAGE = 'Formative assessment practices in multigrade classrooms significantly improved learners reading comprehension when teachers provided immediate corrective feedback during small group instruction across all participating elementary schools in the division';

    private const INTRO = 'This study investigates how classroom teachers in public elementary schools adapt their instructional routines whenever unexpected disruptions interrupt the regular school calendar.';

    private const CLOSING = 'The findings will inform future professional development programs designed for teachers who handle several grade levels within one shared classroom.';

    private const BLOG = 'https://blog.example.org/post-1';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Http::preventStrayRequests();

    }

    /**
     * @param  string|null  $chapterHtml  the chapter's content; by default INTRO, PASSAGE, CLOSING — 71 words, 29 of them the passage
     */
    private function makeSubmission(User $owner, array $attributes = [], ?string $chapterHtml = null): ResearchSubmission
    {
        $submission = ResearchSubmission::create($attributes + [
            'researcher_id' => $owner->id,
            'title' => 'Formative Assessment in Multigrade Classes',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $submission->sections()->create([
            'section_key' => 'context_and_rationale',
            'label' => 'Chapter I. Context and Rationale',
            'type' => 'rich_text',
            'content_html' => $chapterHtml ?? '<p>'.self::INTRO.'</p><p>'.self::PASSAGE.'.</p><p>'.self::CLOSING.'</p>',
            'sort_order' => 0,
        ]);

        return $submission;
    }

    private function repositorySources(array $results): void
    {
        Http::preventStrayRequests();
        foreach ($results as $result) {
            $source = $this->makeSubmission(User::factory()->create(), [
                'title' => $result['title'], 'status' => SubmissionStatus::APPROVED,
                'classification' => 'completed', 'approved_at' => now(),
            ]);
            $source->snapshots()->create([
                'version' => 1, 'path' => 'repository/source.pdf.enc',
                'generated_by' => $source->researcher_id, 'generated_at' => now()->subMinute(),
                'similarity_text' => self::PASSAGE,
            ]);
        }
    }

    private function runCheck(User $researcher, ResearchSubmission $submission): SimilarityCheck
    {
        $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission));

        return $submission->similarityChecks()->latest('id')->firstOrFail();
    }

    public function test_the_report_highlights_matched_text_and_lists_its_sources(): void
    {
        $this->repositorySources([['url' => self::BLOG, 'title' => 'A teacher blog post']]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $check = $this->runCheck($researcher, $submission);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Similarity Report')
            ->assertSee('Chapter I. Context and Rationale')
            // the highlighted passage, wrapped in a mark
            ->assertSee('<mark class="sim-mark"', false)
            ->assertSee('Formative assessment practices in multigrade classrooms', false)
            // and the side panel
            ->assertSee('1 source found')
            ->assertSee('A teacher blog post')
            ->assertDontSee('Open source');
    }

    public function test_a_source_title_is_escaped_in_the_report(): void
    {
        $this->repositorySources([['url' => self::BLOG, 'title' => '<script>alert(1)</script> Paper']]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $check = $this->runCheck($researcher, $submission);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_report_never_links_to_a_non_http_source_url(): void
    {
        $text = 'alpha beta gamma delta epsilon zeta eta theta iota kappa';
        $words = Tokenizer::tokens($text);
        $submission = $this->makeSubmission(User::factory()->create());
        $check = $submission->similarityChecks()->create([
            'requested_by' => $submission->researcher_id,
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'document' => [['section' => 'a', 'label' => 'Chapter I', 'text' => $text]],
            'score' => 100, 'word_count' => 10, 'matched_words' => 10, 'completed_at' => now(),
        ]);
        $check->matches()->create([
            'rank' => 1, 'source_type' => 'web', 'title' => 'Sneaky source', 'url' => 'javascript:alert(document.cookie)',
            'matched_words' => 10, 'percent' => 100,
            'spans' => [['para' => 0, 'start' => 0, 'end' => $words[9]['end'], 'words' => 10]],
        ]);

        // The source is still listed — it just isn't a link.
        $this->actingAs($submission->researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Sneaky source')
            ->assertDontSee('javascript:alert', false);
    }

    public function test_each_word_is_painted_for_its_best_source_but_every_covering_source_is_listed(): void
    {
        $text = 'alpha beta gamma delta epsilon zeta eta theta iota kappa';
        $words = Tokenizer::tokens($text);
        $span = fn (int $from, int $to) => [
            'para' => 0, 'start' => $words[$from]['start'], 'end' => $words[$to]['end'], 'words' => $to - $from + 1,
        ];

        $submission = $this->makeSubmission(User::factory()->create());
        $check = $submission->similarityChecks()->create([
            'requested_by' => $submission->researcher_id,
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'document' => [['section' => 'a', 'label' => 'Chapter I', 'text' => $text]],
            'score' => 100, 'word_count' => 10, 'matched_words' => 10, 'completed_at' => now(),
        ]);
        // Rank 1 matches words 0-5; rank 2 matches words 3-9 — it overlaps rank 1 on words 3-5.
        $best = $check->matches()->create(['rank' => 1, 'source_type' => 'web', 'title' => 'Best', 'matched_words' => 6, 'percent' => 60, 'spans' => [$span(0, 5)]]);
        $other = $check->matches()->create(['rank' => 2, 'source_type' => 'web', 'title' => 'Other', 'matched_words' => 7, 'percent' => 70, 'spans' => [$span(3, 9)]]);

        $segments = app(SimilarityReportBuilder::class)->build($check)['sections'][0]['paragraphs'][0];

        $this->assertSame(['alpha beta gamma delta epsilon zeta', ' ', 'eta theta iota kappa'], array_column($segments, 'text'));
        // The shared words are painted for rank 1 only...
        $this->assertSame([$best->id, null, $other->id], array_column($segments, 'source'));
        // ...but the highlight knows rank 2 overlaps it too, so selecting rank 2 can light it up.
        $this->assertSame([[$best->id, $other->id], [], [$other->id]], array_column($segments, 'sources'));
    }

    public function test_a_source_wholly_hidden_behind_a_better_match_still_covers_that_text(): void
    {
        $text = 'one two three four five six seven eight nine ten';
        $words = Tokenizer::tokens($text);
        $span = ['para' => 0, 'start' => $words[0]['start'], 'end' => $words[9]['end'], 'words' => 10];

        $submission = $this->makeSubmission(User::factory()->create());
        $check = $submission->similarityChecks()->create([
            'requested_by' => $submission->researcher_id,
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'document' => [['section' => 'a', 'label' => 'Chapter I', 'text' => $text]],
            'score' => 100, 'word_count' => 10, 'matched_words' => 10, 'completed_at' => now(),
        ]);
        $winner = $check->matches()->create(['rank' => 1, 'source_type' => 'web', 'title' => 'Winner', 'matched_words' => 10, 'percent' => 100, 'spans' => [$span]]);
        $hidden = $check->matches()->create(['rank' => 2, 'source_type' => 'web', 'title' => 'Hidden', 'matched_words' => 10, 'percent' => 100, 'spans' => [$span]]);

        $segments = app(SimilarityReportBuilder::class)->build($check)['sections'][0]['paragraphs'][0];

        $this->assertCount(1, $segments);
        $this->assertSame($winner->id, $segments[0]['source']);
        $this->assertSame([$winner->id, $hidden->id], $segments[0]['sources']);

        // ...and the page carries that through to the highlight, for the front end to use.
        $this->actingAs($submission->researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('data-sources="'.$winner->id.' '.$hidden->id.'"', false);
    }

    public function test_a_check_needs_enough_text_to_be_worth_running(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = ResearchSubmission::create([
            'researcher_id' => $researcher->id,
            'title' => 'Barely Started',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);
        $submission->sections()->create(['section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text', 'content_html' => '<p>Just a few words.</p>', 'sort_order' => 0]);

        $this->actingAs($researcher)->from(route('submissions.show', $submission))
            ->post(route('submissions.similarity.store', $submission))
            ->assertRedirect(route('submissions.show', $submission))
            ->assertSessionHasErrors('similarity');

        $this->assertSame(0, $submission->similarityChecks()->count());
        Queue::assertNothingPushed();
    }

    public function test_starting_a_check_queues_it_and_lands_on_a_progress_page(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $response = $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission));

        $check = $submission->similarityChecks()->firstOrFail();
        $this->assertSame(SimilarityCheck::STATUS_QUEUED, $check->status);
        $this->assertSame($researcher->id, $check->requested_by);
        Queue::assertPushed(RunSimilarityCheck::class, fn ($job) => $job->checkId === $check->id);

        $response->assertRedirect(route('submissions.similarity.show', [$submission, $check]));
        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Checking the chapters')
            // JSON-encoded into the page's polling script, so slashes are escaped.
            ->assertSee(str_replace('/', '\/', route('submissions.similarity.status', [$submission, $check])), false);

        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $check]))
            ->assertOk()
            ->assertJson(['status' => 'queued']);
    }

    public function test_a_second_check_is_not_started_while_one_is_running(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $running = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);

        $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission))
            ->assertRedirect(route('submissions.similarity.show', [$submission, $running]));

        $this->assertSame(1, $submission->similarityChecks()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_check_whose_worker_died_stops_blocking_new_checks(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $dead = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);
        $dead->forceFill(['created_at' => now()->subHour()])->save();

        $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission));

        $this->assertSame(SimilarityCheck::STATUS_FAILED, $dead->fresh()->status);
        $this->assertSame(2, $submission->similarityChecks()->count());
        Queue::assertPushed(RunSimilarityCheck::class);
    }

    public function test_a_check_the_queue_delivers_twice_only_runs_once(): void
    {
        $this->repositorySources([['url' => self::BLOG, 'title' => 'A teacher blog post']]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $runner = app(SimilarityCheckRunner::class);

        // A second worker picking the job up while the first is still mid-run (retry_after elapsed).
        $running = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);
        $runner->run($running);
        Http::assertNothingSent();
        $this->assertSame(SimilarityCheck::STATUS_RUNNING, $running->fresh()->status);

        // ...and one arriving after the check already finished.
        $done = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_COMPLETED, 'score' => 12.5, 'completed_at' => now()]);
        $runner->run($done);
        Http::assertNothingSent();
        $this->assertEquals(12.5, $done->fresh()->score);

        // A genuinely queued check still runs, exactly once.
        $queued = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_QUEUED]);
        $runner->run($queued);
        $runner->run($queued->fresh());
        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $queued->fresh()->status);
        $this->assertSame(1, $queued->matches()->count());
    }

    public function test_a_check_nobody_ever_picked_up_fails_with_a_message_pointing_at_the_worker(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        // Queued 10 minutes ago and never started: no queue worker is running.
        $orphan = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_QUEUED]);
        $orphan->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $orphan]))
            ->assertOk()
            ->assertSee('never started')
            ->assertSee('may not be running');

        $this->assertSame(SimilarityCheck::STATUS_FAILED, $orphan->fresh()->status);

        // A merely-running check isn't held to that short limit.
        $running = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);
        $running->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $this->assertFalse($running->fresh()->isStale());
    }

    public function test_the_progress_page_warns_when_a_queued_check_is_not_being_picked_up(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $fresh = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_QUEUED]);

        // Just queued: the warning is present in the page but starts hidden (Alpine flag false).
        $page = $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $fresh]));
        $page->assertOk()->assertSee("This check hasn't started yet.", false)->assertSee('notStarted: false', false);

        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $fresh]))
            ->assertOk()
            ->assertJsonPath('queued_for', fn ($seconds) => is_int($seconds) && $seconds < 20);

        // Sat queued for a minute: the page opens with the warning already showing.
        $fresh->forceFill(['created_at' => now()->subSeconds(60)])->save();
        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $fresh]))
            ->assertOk()
            ->assertSee('notStarted: true', false);

        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $fresh]))
            ->assertJsonPath('queued_for', fn ($seconds) => $seconds >= 60);

        // Once it's actually running, there's nothing to warn about.
        $fresh->update(['status' => SimilarityCheck::STATUS_RUNNING]);
        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $fresh]))
            ->assertJsonPath('queued_for', null)
            ->assertJsonPath('status', 'running');
    }

    public function test_a_failed_check_shows_its_reason_and_a_way_to_retry(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $check = $submission->similarityChecks()->create([
            'requested_by' => $researcher->id,
            'status' => SimilarityCheck::STATUS_FAILED,
            'error' => 'The similarity check could not be completed. Please try again in a moment.',
        ]);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee("The similarity check didn't finish", false)
            ->assertSee('Please try again in a moment.')
            ->assertSee('Run again');
    }

    public function test_the_submission_page_shows_the_panel_with_the_latest_result(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Similarity Check')
            ->assertSee('Run similarity check')
            ->assertSee('System repository')
            ->assertSee('Ready');

        $check = $submission->similarityChecks()->create([
            'requested_by' => $researcher->id,
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'score' => 37.5,
            'word_count' => 100,
            'matched_words' => 38,
            'completed_at' => now(),
        ]);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Ready')
            ->assertSee('38%')
            ->assertSee('Run again')
            ->assertSee(route('submissions.similarity.show', [$submission, $check]), false);
    }

    public function test_only_the_owner_an_assigned_reviewer_or_an_admin_can_start_or_view_a_check(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $submission = $this->makeSubmission($owner);
        $check = $submission->similarityChecks()->create(['requested_by' => $owner->id, 'status' => SimilarityCheck::STATUS_FAILED]);

        // Another researcher, and a reviewer who isn't assigned to this submission.
        foreach ([User::factory()->create(), User::factory()->reviewer()->create()] as $intruder) {
            $this->actingAs($intruder)->post(route('submissions.similarity.store', $submission))->assertForbidden();
            $this->actingAs($intruder)->get(route('submissions.similarity.show', [$submission, $check]))->assertForbidden();
            $this->actingAs($intruder)->getJson(route('submissions.similarity.status', [$submission, $check]))->assertForbidden();
        }

        Queue::assertNothingPushed();
    }

    public function test_a_check_cannot_be_viewed_through_a_different_submission(): void
    {
        $researcher = User::factory()->create();
        $mine = $this->makeSubmission($researcher);
        $theirs = $this->makeSubmission(User::factory()->create());
        $check = $theirs->similarityChecks()->create(['requested_by' => $theirs->researcher_id, 'status' => SimilarityCheck::STATUS_FAILED]);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$mine, $check]))->assertNotFound();
        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$mine, $check]))->assertNotFound();
    }

    public function test_the_stored_document_is_encrypted_at_rest(): void
    {
        $researcher = User::factory()->create();
        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $raw = DB::table('similarity_checks')->where('id', $check->id)->value('document');

        $this->assertNotEmpty($raw);
        $this->assertStringNotContainsString('Formative assessment', $raw);
        $this->assertSame(self::PASSAGE.'.', $check->document[1]['text'] ?? null);
    }
}
