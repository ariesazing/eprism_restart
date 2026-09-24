<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Jobs\RefreshChapterHtml;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Services\OnlyOfficeService;
use App\Services\SubmissionSectionService;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * The 'onlyoffice' engine's per-chapter drafting model (the current default for new
 * submissions, see ResearchSubmissionController::store()): chapter tabs on the left,
 * ONLYOFFICE mounted only for the currently-selected rich_text chapter, saved through
 * ONLYOFFICE's own callback loop (OnlyOfficeDocumentController) — as opposed to the
 * 'canvas_editor' engine (in-browser editor) or 'onlyoffice_manuscript' (one whole-document
 * editor, ManuscriptWorkflowTest), neither of which this file touches.
 */
class OnlyOfficeChapterEditingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOnlyOfficeConfig(): void
    {
        config([
            'services.onlyoffice.enabled' => true,
            'services.onlyoffice.url' => 'https://office.test',
            'services.onlyoffice.jwt_secret' => str_repeat('s', 40),
        ]);
    }

    /**
     * @return array{0: OrganizationalUnit, 1: OrganizationalUnitPosition}
     */
    private function seedLookups(): array
    {
        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);

        return [
            OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail(),
            OrganizationalUnitPosition::query()->where('organizational_unit_type', 'school')->firstOrFail(),
        ];
    }

    /**
     * Every leaf of basic_proposal's own rubric filled with its own max — the exact score
     * doesn't matter to this file's one review-flow test, only that the payload is complete.
     */
    private function rubricPayload(): array
    {
        $rubric = ResearchEvaluationRubric::for('basic', 'proposal');

        return collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all();
    }

    private function storePayload(OrganizationalUnit $unit, OrganizationalUnitPosition $position): array
    {
        return [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => [[
                'last_name' => 'Delacruz', 'first_name' => 'Ana', 'position' => $position->label,
            ]],
        ];
    }

    public function test_new_submissions_default_to_the_onlyoffice_chapter_engine_once_it_is_enabled(): void
    {
        $this->fakeOnlyOfficeConfig();
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), $this->storePayload($unit, $position))
            ->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();
        $this->assertSame(EditorEngine::ONLYOFFICE, $submission->editor_engine);
        $this->assertTrue($submission->usesOnlyOffice());
        $this->assertFalse($submission->usesManuscript());
    }

    public function test_new_submissions_fall_back_to_canvas_editor_when_onlyoffice_is_disabled(): void
    {
        config(['services.onlyoffice.enabled' => false]);
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), $this->storePayload($unit, $position))
            ->assertRedirect();

        $this->assertSame(EditorEngine::CANVAS_EDITOR, $researcher->submissions()->firstOrFail()->editor_engine);
    }

    public function test_chapters_page_mounts_onlyoffice_for_a_richtext_chapter_and_never_shows_canvas_editor(): void
    {
        $this->fakeOnlyOfficeConfig();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);

        $response = $this->actingAs($researcher)->get(route('submissions.chapters', $submission))->assertOk();

        $section = $submission->sections()->where('section_key', 'context_and_rationale')->firstOrFail();
        $response->assertSee('data-onlyoffice-chapter', false);
        $response->assertSee(route('submissions.sections.onlyoffice-config', [$submission, $section]), false);
        $response->assertDontSee('data-canvas-editor="toolbar-inline"', false);
    }

    /**
     * A revision request never changes which editor a submission uses — editor_engine is set once,
     * in store(), and nothing on the reviewer/decision path touches it. This runs the real reviewer
     * flow into "revisions required" and checks the researcher still gets ONLYOFFICE afterwards. (A
     * researcher who lands in the canvas editor on a revision is on a submission that was *created*
     * as canvas_editor — i.e. before ONLYOFFICE was enabled — not one that switched.)
     */
    public function test_a_revision_request_keeps_an_onlyoffice_submission_on_onlyoffice(): void
    {
        $this->fakeOnlyOfficeConfig();
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);

        $this->actingAs($admin)->patch(route('admin.submissions.assign-reviewer', $submission), ['reviewer_ids' => [$reviewer->id]])->assertRedirect();
        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), array_merge(
            $this->rubricPayload(),
            ['comments' => 'Please expand the methods.', 'recommendation' => 'major_revision'],
        ))->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::REVISIONS_REQUIRED, $submission->status);
        $this->assertSame(EditorEngine::ONLYOFFICE, $submission->editor_engine);

        $this->actingAs($researcher)->get(route('submissions.chapters', $submission))
            ->assertOk()
            ->assertSee('data-onlyoffice-chapter', false)
            ->assertDontSee('data-canvas-editor="toolbar-inline"', false);
    }

    public function test_chapters_page_still_mounts_canvas_editor_for_the_canvas_engine(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::CANVAS_EDITOR,
        ]);

        $this->actingAs($researcher)->get(route('submissions.chapters', $submission))
            ->assertOk()
            ->assertSee('data-canvas-editor="toolbar-inline"', false)
            ->assertDontSee('data-onlyoffice-chapter', false);
    }

    public function test_a_locked_onlyoffice_chapters_page_shows_read_only_content_not_the_editor(): void
    {
        $this->fakeOnlyOfficeConfig();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::UNDER_REVIEW, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $submission->sections()->create([
            'section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text',
            'content_html' => '<p>Saved chapter text.</p>',
        ]);

        $this->actingAs($researcher)->get(route('submissions.chapters', $submission))
            ->assertOk()
            ->assertSee('Saved chapter text.', false)
            ->assertDontSee('data-onlyoffice-chapter', false)
            ->assertDontSee('data-canvas-editor="toolbar-inline"', false);
    }

    public function test_opening_a_chapter_seeds_a_blank_docx_with_no_title_and_returns_a_signed_editor_config(): void
    {
        $this->fakeOnlyOfficeConfig();
        Storage::fake('local');
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $section = $submission->sections()->create(['section_key' => 'context_and_rationale', 'label' => 'Chapter I. Context and Rationale', 'type' => 'rich_text']);

        $response = $this->actingAs($researcher)
            ->getJson(route('submissions.sections.onlyoffice-config', [$submission, $section]))
            ->assertOk();

        $section->refresh();
        $this->assertNotNull($section->onlyoffice_path);
        Storage::disk('local')->assertExists($section->onlyoffice_path);
        $response->assertJsonPath('document.fileType', 'docx');
        $response->assertJsonPath('document.key', $section->onlyoffice_key);

        // The chapter title lives in the admin's front-matter template, right alongside its
        // ${<key>} placeholder — not seeded into the researcher's own document, which would
        // duplicate it once assemble_chapters() splices this chapter in (see
        // SubmissionSectionService::ensureOnlyOfficeDocument()).
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($section->onlyoffice_path));
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringNotContainsString('Chapter I. Context and Rationale', $documentXml);
    }

    public function test_saving_a_chapter_stores_the_docx_and_refreshes_the_content_html_mirror(): void
    {
        Queue::fake();
        $this->fakeOnlyOfficeConfig();
        Storage::fake('local');
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $section = $submission->sections()->create(['section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text']);

        // Opening the chapter first is what actually seeds onlyoffice_path/onlyoffice_key.
        $this->actingAs($researcher)->getJson(route('submissions.sections.onlyoffice-config', [$submission, $section]))->assertOk();
        $section->refresh();

        Http::fake([
            'office.test/ConvertService.ashx' => Http::response(['fileUrl' => 'https://office.test/converted.html']),
            'office.test/converted.html' => Http::response('<p>Chapter body text.</p>'),
            '*/saved.docx' => Http::response('%PDF-not-really-a-docx-but-bytes-are-enough-here'),
        ]);

        $callbackUrl = URL::temporarySignedRoute('onlyoffice.sections.callback', now()->addHour(), ['section' => $section->id], absolute: false);
        $token = JWT::encode(['aud' => 'onlyoffice'], config('services.onlyoffice.jwt_secret'), 'HS256');

        $before = $section->onlyoffice_key;

        $this->postJson($callbackUrl, ['status' => 2, 'url' => 'https://office.test/saved.docx'], ['Authorization' => 'Bearer '.$token])
            ->assertJson(['error' => 0]);

        $section->refresh();
        $this->assertNotSame($before, $section->onlyoffice_key);
        Storage::disk('local')->assertExists($section->onlyoffice_path);
        $this->assertSame('%PDF-not-really-a-docx-but-bytes-are-enough-here', Storage::disk('local')->get($section->onlyoffice_path));
        Http::assertSentCount(1);
        Queue::assertPushed(RefreshChapterHtml::class, function ($job) use ($section) {
            $job->handle(app(OnlyOfficeService::class), app(SubmissionSectionService::class));

            return $job->sectionId === $section->id;
        });
        $this->assertSame('<p>Chapter body text.</p>', $section->fresh()->content_html);
    }

    public function test_callback_rejects_an_unsigned_or_wrongly_tokened_request(): void
    {
        $this->fakeOnlyOfficeConfig();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $section = $submission->sections()->create(['section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text']);

        $callbackUrl = URL::temporarySignedRoute('onlyoffice.sections.callback', now()->addHour(), ['section' => $section->id], absolute: false);

        $this->postJson($callbackUrl, ['status' => 2, 'url' => 'https://office.test/saved.docx'])
            ->assertStatus(403);
    }

    /**
     * Unlike the manuscript engine (its preview is queued off-request — see
     * ManuscriptPreviewService), an 'onlyoffice' draft's live preview composes inline, on this
     * request, with a real Document Server round trip. Must degrade to a clean 503 rather than
     * an unhandled 500 when that round trip fails — mirrors grammarCheck()'s own
     * GrammarCheckUnavailableException handling.
     */
    /**
     * A minimal but valid basic_proposal front-matter fixture: a ${title} scalar plus one
     * locator placeholder per each-block (proponents, and this template's three table
     * sections) — DocxTemplateFiller requires every each-block's own column placeholder to be
     * present, whether or not there's row data for it (see SubmissionDocxManuscriptTest's own
     * identical fixture).
     */
    private function makeFrontMatterFixtureDocxPath(): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('${title}');

        $proponents = $section->addTable();
        $proponents->addRow();
        $proponents->addCell()->addText('${proponent_name}');
        $proponents->addCell()->addText('${proponent_position}');
        $proponents->addCell()->addText('${proponent_photo}');

        foreach ([
            ['activity', 'timeline', 'person_responsible'],
            ['activity', 'item_description', 'cost', 'total_amount', 'source_of_fund'],
            ['strategy', 'activity', 'timeframe', 'resources_needed'],
        ] as $columns) {
            $table = $section->addTable();
            $table->addRow();
            foreach ($columns as $column) {
                $table->addCell()->addText('${'.$column.'}');
            }
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'front-matter-fixture-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        Storage::disk('local')->put('templates/basic.docx', file_get_contents($tmpPath));
        unlink($tmpPath);

        return 'templates/basic.docx';
    }

    /**
     * Unlike the manuscript engine (its preview is queued off-request — see
     * ManuscriptPreviewService), an 'onlyoffice' draft's live preview composes inline, on this
     * request, with a real Document Server round trip. Must degrade to a clean 503 rather than
     * an unhandled 500 when that round trip fails — mirrors grammarCheck()'s own
     * GrammarCheckUnavailableException handling.
     */
    public function test_viewing_the_manuscript_returns_a_clean_error_when_the_document_server_is_unreachable(): void
    {
        $this->fakeOnlyOfficeConfig();
        Storage::fake('local');
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        SubmissionDocumentTemplate::create(['template_key' => 'basic_proposal', 'docx_path' => $this->makeFrontMatterFixtureDocxPath(), 'docx_key' => 'seed']);

        Http::fake(['office.test/ConvertService.ashx' => Http::response(null, 500)]);

        $this->actingAs($researcher)->get(route('submissions.manuscript', $submission))
            ->assertStatus(503);
    }

    /**
     * Covers the "chapter tab stays mounted but never formally closes" gap: nothing else would
     * otherwise trigger a save until the whole page unloads (see requestChapterForceSave() in
     * submission-editor.js, called on tab-switch and beforeunload).
     */
    public function test_force_save_asks_document_server_to_save_the_chapter_now(): void
    {
        $this->fakeOnlyOfficeConfig();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $section = $submission->sections()->create([
            'section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text',
            'onlyoffice_path' => 'onlyoffice-documents/'.$submission->id.'/context_and_rationale.docx',
            'onlyoffice_key' => 'a-real-key',
        ]);

        Http::fake(['office.test/coauthoring/CommandService.ashx' => Http::response(['error' => 0])]);

        $this->actingAs($researcher)
            ->postJson(route('submissions.sections.onlyoffice-force-save', [$submission, $section]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) => $request['c'] === 'forcesave' && $request['key'] === 'a-real-key');
    }

    public function test_force_save_is_a_safe_no_op_when_the_chapter_was_never_opened(): void
    {
        $this->fakeOnlyOfficeConfig();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $section = $submission->sections()->create(['section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text']);

        Http::preventStrayRequests();

        $this->actingAs($researcher)
            ->postJson(route('submissions.sections.onlyoffice-force-save', [$submission, $section]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertNothingSent();
    }

    public function test_force_save_is_forbidden_for_an_unrelated_researcher(): void
    {
        $this->fakeOnlyOfficeConfig();
        $owner = User::factory()->create();
        $submission = $owner->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $section = $submission->sections()->create(['section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text', 'onlyoffice_key' => 'k']);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson(route('submissions.sections.onlyoffice-force-save', [$submission, $section]))
            ->assertForbidden();
    }
}
