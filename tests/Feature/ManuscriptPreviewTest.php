<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Jobs\ComposeManuscriptPreview;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Services\ManuscriptPreviewService;
use App\Services\ManuscriptProcessor;
use App\Services\ManuscriptService;
use App\Services\OnlyOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Covers the "draft PDF conversion still runs in the web request" gap: previewing a complete
 * manuscript used to call Document Server and the Python merge step synchronously inside the
 * request that served the PDF. ManuscriptPreviewService now only captures the source and queues
 * ComposeManuscriptPreview; reviewManuscript() shows a polling "preparing" page until it's ready,
 * and the actual PDF bytes route (ResearchSubmissionController::manuscript()) only ever serves
 * the persisted, already-finished result.
 */
class ManuscriptPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $researcher;

    private ResearchSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        config(['services.onlyoffice.enabled' => true, 'services.onlyoffice.url' => 'https://office.test',
            'services.onlyoffice.jwt_secret' => str_repeat('s', 40)]);
        $this->researcher = User::factory()->create();
        $this->submission = $this->researcher->submissions()->create([
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
        ]);
        Storage::disk('local')->put('templates/basic.docx', file_get_contents(resource_path('onlyoffice/blank-chapter.docx')));
        SubmissionDocumentTemplate::create(['template_key' => 'basic_proposal', 'docx_path' => 'templates/basic.docx', 'content' => '{}']);

        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldReceive('run')->andReturnUsing(function ($operation, $args) {
            if ($operation === 'seed' || $operation === 'migrate') {
                copy($args['input'], $args['output']);

                return ['ok' => true];
            }
            if ($operation === 'merge_pdf') {
                file_put_contents($args['output'], '%PDF-1.4 merged preview fixture');

                return ['ok' => true];
            }

            return ['ok' => true];
        });
        $this->app->instance(ManuscriptProcessor::class, $processor);

        app(ManuscriptService::class)->ensure($this->submission);
    }

    private function pushedPreviewJob(): ComposeManuscriptPreview
    {
        return Queue::pushed(ComposeManuscriptPreview::class)->firstOrFail();
    }

    public function test_viewing_the_manuscript_queues_a_preview_and_shows_a_pending_page_until_ready(): void
    {
        $response = $this->actingAs($this->researcher)->get(route('submissions.manuscript.review', $this->submission));

        $response->assertOk()->assertViewIs('researcher.submissions.manuscript-preview-pending');
        $this->assertSame('queued', $this->submission->fresh()->manuscript['preview']['state']);
        Queue::assertPushed(ComposeManuscriptPreview::class);

        // The bytes route must not fall back to composing anything itself while still queued.
        $this->actingAs($this->researcher)->get(route('submissions.manuscript', $this->submission))->assertStatus(409);
    }

    public function test_manuscript_route_serves_the_persisted_preview_once_the_job_finishes(): void
    {
        $this->actingAs($this->researcher)->get(route('submissions.manuscript.review', $this->submission));
        $job = $this->pushedPreviewJob();

        $office = Mockery::mock(OnlyOfficeService::class);
        $office->shouldReceive('convertFilledDocxToPdf')->once()->andReturn('%PDF-1.4 converted source');
        $job->handle($office, app(ManuscriptProcessor::class), app(ManuscriptService::class));

        $this->assertSame('ready', $this->submission->fresh()->manuscript['preview']['state']);

        $review = $this->actingAs($this->researcher)->get(route('submissions.manuscript.review', $this->submission));
        $review->assertOk()->assertViewIs('submissions.document-review');

        $bytes = $this->actingAs($this->researcher)->get(route('submissions.manuscript', $this->submission));
        $bytes->assertOk();
        $this->assertSame('%PDF-1.4 merged preview fixture', $bytes->getContent());
    }

    public function test_requesting_a_preview_twice_without_changes_does_not_queue_a_second_job(): void
    {
        app(ManuscriptPreviewService::class)->requestPreview($this->submission);
        app(ManuscriptPreviewService::class)->requestPreview($this->submission);

        Queue::assertPushedTimes(ComposeManuscriptPreview::class, 1);
    }

    public function test_a_stale_job_finishing_late_does_not_overwrite_a_newer_preview_request(): void
    {
        app(ManuscriptPreviewService::class)->requestPreview($this->submission);
        $staleJob = $this->pushedPreviewJob();

        // Something changes the working copy, so the researcher (or the system) requests a
        // fresh preview before the stale job has actually run.
        $state = $this->submission->fresh()->manuscript;
        Storage::disk('local')->put($state['working_path'], file_get_contents(resource_path('onlyoffice/blank-chapter.docx')));
        app(ManuscriptPreviewService::class)->requestPreview($this->submission);
        $freshHash = $this->submission->fresh()->manuscript['preview']['source_hash'];
        $this->assertNotSame($staleJob->sourceHash, $freshHash);

        $office = Mockery::mock(OnlyOfficeService::class);
        $office->shouldReceive('convertFilledDocxToPdf')->once()->andReturn('%PDF-1.4 stale conversion');
        $staleJob->handle($office, app(ManuscriptProcessor::class), app(ManuscriptService::class));

        // The stale job's own result must not have clobbered the newer request's queued state.
        $current = $this->submission->fresh()->manuscript['preview'];
        $this->assertSame($freshHash, $current['source_hash']);
        $this->assertSame('queued', $current['state']);
    }

    public function test_a_failed_preview_job_surfaces_a_clear_error_and_does_not_touch_the_working_copy(): void
    {
        app(ManuscriptPreviewService::class)->requestPreview($this->submission);
        $job = $this->pushedPreviewJob();
        $workingPathBefore = $this->submission->fresh()->manuscript['working_path'];

        $job->failed(new \RuntimeException('conversion exploded'));

        $state = $this->submission->fresh()->manuscript;
        $this->assertSame('failed', $state['preview']['state']);
        $this->assertNotEmpty($state['preview']['error']);
        $this->assertSame($workingPathBefore, $state['working_path']);
    }
}
