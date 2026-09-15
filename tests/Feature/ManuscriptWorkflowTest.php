<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Jobs\EncryptApprovedManuscript;
use App\Jobs\ProcessManuscriptVersion;
use App\Models\ManuscriptVersion;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Services\ManuscriptProcessor;
use App\Services\ManuscriptService;
use App\Services\OnlyOfficeService;
use App\Services\SubmissionReadinessService;
use App\Services\SubmissionSnapshotService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManuscriptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $researcher;

    private ResearchSubmission $submission;

    private bool $valid = true;

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
        foreach ($this->submission->template()->attachments as $attachment) {
            if ($attachment->required) {
                $path = 'attachments/'.$attachment->key.'.pdf';
                Storage::disk('local')->put($path, '%PDF-1.4 attachment');
                $this->submission->documents()->create(['uploaded_by' => $this->researcher->id, 'document_type' => $attachment->key,
                    'original_name' => 'attachment.pdf', 'mime_type' => 'application/pdf', 'path' => $path]);
            }
        }
        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldReceive('run')->andReturnUsing(function ($operation, $args) {
            if ($operation === 'validate') {
                return ['valid' => $this->valid, 'errors' => $this->valid ? [] : ['References are empty.'],
                    'warnings' => [], 'sections' => [], 'text' => 'Research text.'];
            }
            copy($args['input'], $args['output']);

            return ['ok' => true];
        });
        $this->app->instance(ManuscriptProcessor::class, $processor);
    }

    private function initialize(): array
    {
        app(ManuscriptService::class)->ensure($this->submission);

        return $this->submission->fresh()->manuscript;
    }

    private function sendCallback(array $payload, ?string $key = null, array $unsigned = [])
    {
        $key ??= $payload['key'];
        $url = URL::temporarySignedRoute('onlyoffice.manuscripts.callback', now()->addHour(),
            ['submission' => $this->submission->id, 'key' => $key], absolute: false);
        $token = JWT::encode($payload, config('services.onlyoffice.jwt_secret'), 'HS256');

        return $this->postJson($url, array_merge($payload, $unsigned), ['Authorization' => 'Bearer '.$token]);
    }

    private function freeze(): ManuscriptVersion
    {
        $this->initialize();
        app(ManuscriptService::class)->requestSubmission($this->submission, $this->researcher);

        return $this->submission->manuscriptVersions()->firstOrFail();
    }

    public function test_config_opens_one_full_document_with_editing_tools_and_owner_authorization(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('submissions.manuscript.config', $this->submission))->assertForbidden();
        $response = $this->actingAs($this->researcher)->getJson(route('submissions.manuscript.config', $this->submission))->assertOk();
        $response->assertJsonPath('document.fileType', 'docx')->assertJsonPath('editorConfig.customization.forcesave', true);
        $this->assertArrayNotHasKey('layout', $response->json('editorConfig.customization'));
        $this->assertTrue($this->submission->fresh()->manuscript['session_open']);
    }

    public function test_force_save_keeps_session_key_and_final_save_freezes_only_after_editor_closes(): void
    {
        $state = $this->initialize();
        $this->submission->update(['manuscript' => array_merge($state, ['session_open' => true])]);
        app(ManuscriptService::class)->requestSubmission($this->submission, $this->researcher);
        $this->assertDatabaseCount('manuscript_versions', 0);
        Http::fake(fn () => Http::response(file_get_contents(resource_path('onlyoffice/blank-chapter.docx'))));
        $this->sendCallback(['key' => $state['key'], 'status' => 6, 'url' => 'https://office.test/saved.docx'])->assertJson(['error' => 0]);
        $this->assertSame($state['key'], $this->submission->fresh()->manuscript['key']);
        $this->assertDatabaseCount('manuscript_versions', 0);
        $this->sendCallback(['key' => $state['key'], 'status' => 2, 'url' => 'https://office.test/saved.docx'])->assertJson(['error' => 0]);
        $this->assertDatabaseCount('manuscript_versions', 1);
        $this->assertSame('processing', $this->submission->fresh()->manuscript['state']);
        Queue::assertPushed(ProcessManuscriptVersion::class);
    }

    public function test_stale_callback_cannot_replace_new_working_copy_or_frozen_version(): void
    {
        $version = $this->freeze();
        $state = $this->submission->fresh()->manuscript;
        $before = Storage::disk('local')->get($version->docx_path);
        Http::preventStrayRequests();
        $this->sendCallback(['key' => 'retired-session', 'status' => 2, 'url' => 'https://office.test/late.docx'])->assertJson(['error' => 0]);
        $this->assertSame($before, Storage::disk('local')->get($version->docx_path));
        $this->assertSame($state['working_path'], $this->submission->fresh()->manuscript['working_path']);
        Http::assertNothingSent();
    }

    public function test_callback_uses_signed_payload_and_rejects_untrusted_file_urls(): void
    {
        $state = $this->initialize();
        Http::preventStrayRequests();
        $this->sendCallback(['key' => $state['key'], 'status' => 6, 'url' => 'http://127.0.0.1/private'])->assertStatus(422);
        $this->sendCallback(['key' => $state['key'], 'status' => 1], unsigned: ['status' => 2, 'url' => 'http://127.0.0.1/private'])->assertJson(['error' => 0]);
        Http::assertNothingSent();
    }

    public function test_callback_rejects_a_saved_document_over_the_configured_size_limit(): void
    {
        $state = $this->initialize();
        config(['manuscripts.max_docx_bytes' => 10]);
        Http::fake(fn () => Http::response(str_repeat('x', 1000)));

        $response = $this->sendCallback(['key' => $state['key'], 'status' => 2, 'url' => 'https://office.test/saved.docx']);

        $response->assertJson(['error' => 1]);
        // Rejected — the working copy must be exactly what it was before this callback.
        $this->assertSame($state['working_path'], $this->submission->fresh()->manuscript['working_path']);
    }

    public function test_duplicate_submission_does_not_allocate_another_version(): void
    {
        $this->freeze();
        $this->actingAs($this->researcher)->postJson(route('submissions.resubmit', $this->submission))->assertForbidden();
        $this->expectException(HttpException::class);
        try {
            app(ManuscriptService::class)->requestSubmission($this->submission->fresh(), $this->researcher);
        } finally {
            $this->assertDatabaseCount('manuscript_versions', 1);
        }
    }

    public function test_failed_validation_retains_docx_and_does_not_publish_a_snapshot(): void
    {
        $version = $this->freeze();
        $this->valid = false;
        (new ProcessManuscriptVersion($version->id))->handle(app(ManuscriptProcessor::class), app(OnlyOfficeService::class), app(ManuscriptService::class));
        $this->assertSame('failed', $version->fresh()->state);
        $this->assertDatabaseCount('research_snapshots', 0);
        $this->assertSame(SubmissionStatus::DRAFT, $this->submission->fresh()->status);
        Storage::disk('local')->assertExists($version->docx_path);
    }

    public function test_successful_processing_pins_the_review_pdf_and_is_idempotent(): void
    {
        $version = $this->freeze();
        $office = Mockery::mock(OnlyOfficeService::class);
        $office->shouldReceive('convertFilledDocxToPdf')->once()->andReturn('%PDF-1.4 retained review fixture');
        $job = new ProcessManuscriptVersion($version->id);
        $job->handle(app(ManuscriptProcessor::class), $office, app(ManuscriptService::class));
        $job->handle(app(ManuscriptProcessor::class), $office, app(ManuscriptService::class));
        $this->assertSame('ready', $version->fresh()->state);
        $this->assertDatabaseCount('research_snapshots', 1);
        $this->assertSame(SubmissionStatus::SUBMITTED, $this->submission->fresh()->status);
    }

    public function test_source_metadata_and_docx_paths_are_immutable(): void
    {
        $version = $this->freeze();
        $this->expectException(\LogicException::class);
        $version->update(['docx_path' => 'replacement.docx']);
    }

    public function test_version_download_is_private(): void
    {
        $version = $this->freeze();
        $this->actingAs(User::factory()->create())->get(route('manuscript-versions.docx', $version))->assertForbidden();
        $this->actingAs($this->researcher)->get(route('manuscript-versions.docx', $version))->assertOk();
    }

    /**
     * Covers the "unrecoverable session flag" gap: config() optimistically sets
     * session_open=true before the editor has actually finished loading, so if it never does
     * (a script/network failure, or the tab closing early) no close callback ever arrives to
     * clear it, and a plain "give up after N minutes" recovery can't tell that apart from a
     * genuinely still-open session it would be unsafe to freeze past. These three tests drive
     * ManuscriptService::recoverStuckSessions() directly against a faked CommandService.ashx.
     */
    private function stickSession(): array
    {
        $state = $this->initialize();
        $this->submission->update(['manuscript' => array_merge($state, ['session_open' => true])]);
        app(ManuscriptService::class)->requestSubmission($this->submission, $this->researcher);

        return $this->submission->fresh()->manuscript;
    }

    public function test_recover_stuck_sessions_freezes_immediately_once_document_server_confirms_the_session_is_gone(): void
    {
        $state = $this->stickSession();
        Http::fake(['*/coauthoring/CommandService.ashx' => Http::response(['key' => $state['key'], 'error' => 1])]);

        app(ManuscriptService::class)->recoverStuckSessions();

        $this->assertDatabaseCount('manuscript_versions', 1);
        $this->assertSame('processing', $this->submission->fresh()->manuscript['state']);
        Http::assertSent(fn ($request) => $request['c'] === 'info' && $request['key'] === $state['key']);
        Queue::assertPushed(ProcessManuscriptVersion::class);
    }

    public function test_recover_stuck_sessions_force_saves_and_keeps_waiting_while_document_server_still_shows_it_open(): void
    {
        $state = $this->stickSession();
        Http::fake(['*/coauthoring/CommandService.ashx' => Http::response(['error' => 0])]);

        app(ManuscriptService::class)->recoverStuckSessions();

        $this->assertDatabaseCount('manuscript_versions', 0);
        $fresh = $this->submission->fresh()->manuscript;
        $this->assertSame('waiting_for_save', $fresh['state']);
        $this->assertTrue($fresh['session_open']);
        Http::assertSent(fn ($request) => $request['c'] === 'forcesave' && $request['key'] === $state['key']);
    }

    public function test_recover_stuck_sessions_gives_up_after_the_timeout_without_resetting_the_flag_it_cannot_confirm(): void
    {
        $state = $this->stickSession();
        $state['requested_at'] = now()->subMinutes(config('manuscripts.save_timeout_minutes') + 5)->toIso8601String();
        $this->submission->update(['manuscript' => $state]);
        Http::fake(['*/coauthoring/CommandService.ashx' => Http::response(['error' => 0])]);

        app(ManuscriptService::class)->recoverStuckSessions();

        $this->assertDatabaseCount('manuscript_versions', 0);
        $fresh = $this->submission->fresh()->manuscript;
        $this->assertSame('failed', $fresh['state']);
        $this->assertNotEmpty($fresh['error']);
        // Still true — we never confirmed the session is actually gone, so it would be unsafe
        // to clear this and let a later retry freeze a working copy DS might still be holding
        // newer edits for.
        $this->assertTrue($fresh['session_open']);
    }

    /**
     * Covers the "final encryption has no explicit failure/retry UI" gap: EncryptApprovedManuscript
     * previously had queue retries but no failed() handler, so a version that exhausted them just
     * stayed "being prepared" forever with nothing recorded and no way to retry. failed() now
     * records final_pdf_error, and ManuscriptController::retryFinalPdf() re-dispatches safely
     * (the job only ever reads the already-approved, immutable review PDF).
     */
    private function approvedVersion(): ManuscriptVersion
    {
        $version = $this->freeze();
        $office = Mockery::mock(OnlyOfficeService::class);
        $office->shouldReceive('convertFilledDocxToPdf')->andReturn('%PDF-1.4 retained review fixture');
        (new ProcessManuscriptVersion($version->id))->handle(app(ManuscriptProcessor::class), $office, app(ManuscriptService::class));
        $version->refresh()->update(['approved_at' => now()]);

        return $version;
    }

    public function test_failed_final_pdf_encryption_records_a_retryable_error(): void
    {
        $version = $this->approvedVersion();

        (new EncryptApprovedManuscript($version->id))->failed(new \RuntimeException('pikepdf exploded'));

        $fresh = $version->fresh();
        $this->assertNull($fresh->final_pdf_path);
        $this->assertNotEmpty($fresh->final_pdf_error);
        // The approved review PDF itself must be untouched by a final-PDF failure.
        $this->assertNotNull($fresh->snapshot);
    }

    public function test_retry_final_pdf_redispatches_and_a_subsequent_success_clears_the_error(): void
    {
        $version = $this->approvedVersion();
        (new EncryptApprovedManuscript($version->id))->failed(new \RuntimeException('pikepdf exploded'));
        $this->assertNotEmpty($version->fresh()->final_pdf_error);

        $this->actingAs($this->researcher)
            ->post(route('manuscript-versions.retry-final', $version))
            ->assertRedirect();
        Queue::assertPushed(EncryptApprovedManuscript::class);

        (new EncryptApprovedManuscript($version->id))
            ->handle(app(ManuscriptProcessor::class), app(ManuscriptService::class), app(SubmissionSnapshotService::class));

        $fresh = $version->fresh();
        $this->assertNotNull($fresh->final_pdf_path);
        $this->assertNull($fresh->final_pdf_error);
    }

    public function test_retry_final_pdf_is_forbidden_for_an_unrelated_user(): void
    {
        $version = $this->approvedVersion();
        (new EncryptApprovedManuscript($version->id))->failed(new \RuntimeException('pikepdf exploded'));

        $this->actingAs(User::factory()->create())
            ->post(route('manuscript-versions.retry-final', $version))
            ->assertForbidden();
    }

    public function test_retry_final_pdf_is_rejected_once_it_has_already_succeeded(): void
    {
        $version = $this->approvedVersion();
        $version->update(['final_pdf_path' => 'somewhere.enc', 'final_pdf_hash' => str_repeat('a', 64)]);

        $this->actingAs($this->researcher)
            ->post(route('manuscript-versions.retry-final', $version))
            ->assertStatus(409);
    }

    /**
     * Covers the "readiness inspection can break normal page requests" gap: report() used to
     * let a Python worker/storage failure propagate straight out of it, and it runs on every
     * ordinary page view that shows readiness — including the researcher's own dashboard, which
     * calls it once per manuscript-engine submission they own. A single broken Python install
     * could previously 500 that whole page, not just this one submission's own view.
     */
    public function test_report_does_not_throw_when_the_python_worker_fails_and_readiness_reflects_that_without_lying(): void
    {
        $this->initialize();

        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldReceive('run')->with('validate', Mockery::any())->andThrow(new \RuntimeException('python exploded'));
        $this->app->instance(ManuscriptProcessor::class, $processor);

        $report = app(ManuscriptService::class)->report($this->submission);
        $this->assertFalse($report['available']);
        $this->assertNotEmpty($report['error']);

        $readiness = app(SubmissionReadinessService::class)->assess($this->submission->fresh());
        $this->assertFalse($readiness['ready']);
        $this->assertTrue($readiness['unavailable']);
        // Must not read as "every required section is missing" — that's a lie about the
        // researcher's own content, not an honest "couldn't check right now".
        $this->assertSame([], $readiness['sections']['missing']);
        $this->assertSame(0, $readiness['sections']['done']);
    }

    public function test_viewing_the_submission_or_dashboard_does_not_500_when_the_python_worker_is_broken(): void
    {
        $this->initialize();

        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldReceive('run')->andThrow(new \RuntimeException('python exploded'));
        $this->app->instance(ManuscriptProcessor::class, $processor);

        $this->actingAs($this->researcher)->get(route('submissions.show', $this->submission))->assertOk();
        $this->actingAs($this->researcher)->get(route('dashboard'))->assertOk();
    }

    public function test_recover_stuck_sessions_leaves_the_flag_alone_when_document_server_is_unreachable(): void
    {
        $state = $this->stickSession();
        Http::fake(['*/coauthoring/CommandService.ashx' => Http::response(null, 500)]);

        app(ManuscriptService::class)->recoverStuckSessions();

        $this->assertDatabaseCount('manuscript_versions', 0);
        $fresh = $this->submission->fresh()->manuscript;
        $this->assertSame('waiting_for_save', $fresh['state']);
        $this->assertTrue($fresh['session_open']);
        $this->assertNull($fresh['error']);
    }
}
