<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManuscriptResponseCachingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSnapshot($submission, int $version = 1): ResearchSnapshot
    {
        Storage::disk('local')->put(
            "research-snapshots/{$submission->id}/v{$version}.pdf.enc",
            Crypt::encrypt('%PDF-1.4 fake manuscript bytes')
        );

        return $submission->snapshots()->create([
            'version' => $version,
            'path' => "research-snapshots/{$submission->id}/v{$version}.pdf.enc",
            'generated_by' => $submission->researcher_id,
            'generated_at' => now(),
        ]);
    }

    public function test_admin_manuscript_repeat_request_returns_304_and_etag_changes_across_versions(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Cache Test',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]);
        $this->makeSnapshot($submission);

        $first = $this->actingAs($admin)->get(route('admin.submissions.manuscript', $submission));
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $second = $this->actingAs($admin)->get(route('admin.submissions.manuscript', $submission), [
            'If-None-Match' => $etag,
        ]);
        $second->assertStatus(304);

        $this->makeSnapshot($submission, 2);
        $submission->refresh();

        $third = $this->actingAs($admin)->get(route('admin.submissions.manuscript', $submission), [
            'If-None-Match' => $etag,
        ]);
        $third->assertOk();
        $this->assertNotSame($etag, $third->headers->get('ETag'));
    }
}
