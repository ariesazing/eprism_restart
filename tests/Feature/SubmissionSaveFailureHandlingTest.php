<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use App\Services\SubmissionSectionService;
use App\Services\SubmissionSnapshotService;
use Database\Seeders\SubmissionDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the try/catch wrapping added around autosave()/submit()/resubmit()
 * — previously a save-path exception (HTMLPurifier choking on malformed input, a transient
 * storage error, or anything else besides the already-handled out-of-memory case) surfaced
 * as a raw, unhandled 500 with a leaked stack trace instead of the app's own established
 * clean-error pattern (see streamManuscript()'s existing try/catch for the precedent).
 */
class SubmissionSaveFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubmissionDocumentTemplateSeeder::class);
    }

    private function makeDraft(User $researcher)
    {
        return $researcher->submissions()->create([
            'title' => 'Failure Handling Test',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
            'organizational_unit' => 'Santiago City NHS',
            'school_id' => '123456',
        ]);
    }

    public function test_autosave_returns_a_clean_error_instead_of_a_raw_500_when_saving_fails(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeDraft($researcher);

        $this->mock(SubmissionSectionService::class, function ($mock) {
            $mock->shouldReceive('saveOne')->once()->andThrow(new \RuntimeException('sanitizer exploded'));
        });

        $response = $this->actingAs($researcher)
            ->patchJson(route('submissions.autosave', $submission), [
                'section' => 'context_and_rationale',
                'value' => ['content' => '{}', 'html' => '<p>x</p>'],
            ]);

        $response->assertStatus(500);
        $response->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
        $this->assertStringNotContainsString('sanitizer exploded', $response->getContent());
    }

    public function test_submit_returns_a_clean_error_and_does_not_change_status_when_snapshot_generation_fails(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeDraft($researcher);

        // Fill every required section/attachment so submit() reaches generate() at all.
        app(SubmissionSectionService::class)->save($submission, $submission->template(), collect($submission->template()->sections)
            ->mapWithKeys(fn ($definition) => [$definition->key => $definition->type === 'table'
                ? [['activity' => 'x']]
                : ['content' => '{}', 'html' => '<p>Filled in.</p>'],
            ])->all());

        foreach ($submission->template()->attachments as $attachment) {
            $submission->documents()->create([
                'uploaded_by' => $researcher->id,
                'document_type' => $attachment->key,
                'original_name' => "{$attachment->key}.pdf",
                'path' => "submissions/{$submission->id}/{$attachment->key}.pdf",
                'mime_type' => 'application/pdf',
            ]);
        }

        $this->mock(SubmissionSnapshotService::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andThrow(new \RuntimeException('compose exploded'));
        });

        $response = $this->actingAs($researcher)
            ->from(route('submissions.show', $submission))
            ->post(route('submissions.submit', $submission));

        $response->assertSessionHasErrors('submission');
        $this->assertStringNotContainsString('compose exploded', $response->getContent() ?? '');

        $this->assertSame(SubmissionStatus::DRAFT, $submission->fresh()->status);
    }
}
