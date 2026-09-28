<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\ApprovedDocumentRetention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovedDocumentRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function submission(array $attributes = []): ResearchSubmission
    {
        Storage::fake('local');

        return User::factory()->create()->submissions()->create($attributes + [
            'title' => 'Research', 'research_type' => 'basic', 'classification' => 'completed',
            'status' => SubmissionStatus::APPROVED, 'approved_at' => now(),
            'proposal_approved_at' => now()->subDay(),
        ]);
    }

    private function snapshot(ResearchSubmission $submission, int $number, $date)
    {
        $path = "research-snapshots/{$submission->id}/v{$number}.pdf.enc";
        Storage::disk('local')->put($path, 'retained document');

        return $submission->snapshots()->create(['version' => $number, 'path' => $path,
            'generated_by' => $submission->researcher_id, 'generated_at' => $date]);
    }

    public function test_legacy_cleanup_keeps_approved_proposal_and_final_files_and_all_history(): void
    {
        $submission = $this->submission();
        $old = $this->snapshot($submission, 1, now()->subDays(3));
        $proposal = $this->snapshot($submission, 2, now()->subDays(2));
        $final = $this->snapshot($submission, 3, now()->subHour());
        app(ApprovedDocumentRetention::class)->prune($submission);
        app(ApprovedDocumentRetention::class)->prune($submission);
        Storage::disk('local')->assertMissing($old->path);
        Storage::disk('local')->assertExists([$proposal->path, $final->path]);
        $this->assertNotNull($old->fresh()->files_pruned_at);
        $this->assertSame(3, $submission->snapshots()->count());
        $this->actingAs($submission->researcher)->get(route('submissions.manuscript.version', [$submission, $old]))->assertGone();
    }

    public function test_cleanup_waits_for_final_manuscript_and_preserves_current_and_other_submission_files(): void
    {
        $submission = $this->submission(['editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT]);
        $old = $this->snapshot($submission, 1, now()->subDays(3));
        $final = $this->snapshot($submission, 2, now()->subHour());
        $versions = [];
        foreach ([$old, $final] as $index => $snapshot) {
            $folder = "manuscripts/{$submission->id}/versions/".Str::uuid();
            foreach (['source.docx', 'template.docx', 'attachment.pdf'] as $file) {
                Storage::disk('local')->put($folder.'/'.$file, $file);
            }
            $versions[] = $submission->manuscriptVersions()->create([
                'attempt' => (string) Str::uuid(), 'created_by' => $submission->researcher_id,
                'state' => 'ready', 'research_snapshot_id' => $snapshot->id,
                'docx_path' => $folder.'/source.docx', 'docx_hash' => str_repeat('a', 64),
                'template_path' => $folder.'/template.docx', 'template_hash' => str_repeat('b', 64),
                'metadata' => ['classification' => 'completed'], 'attachments' => [['path' => $folder.'/attachment.pdf']],
                'approved_at' => $index === 1 ? now() : null,
            ]);
        }
        $other = 'research-snapshots/999/v1.pdf.enc';
        Storage::disk('local')->put($other, 'other');
        app(ApprovedDocumentRetention::class)->prune($submission);
        Storage::disk('local')->assertExists($old->path);
        $finalPath = dirname($versions[1]->docx_path).'/final.pdf.enc';
        $versions[1]->update(['final_pdf_path' => $finalPath]);
        app(ApprovedDocumentRetention::class)->prune($submission);
        Storage::disk('local')->assertExists($old->path);
        Storage::disk('local')->put($finalPath, 'final');
        app(ApprovedDocumentRetention::class)->prune($submission);
        Storage::disk('local')->assertMissing([$old->path, $versions[0]->docx_path, $versions[0]->template_path, $versions[0]->attachments[0]['path']]);
        Storage::disk('local')->assertExists([$final->path, $finalPath, $versions[1]->docx_path, $other]);
        $this->assertNotNull($versions[0]->fresh()->files_pruned_at);
        $this->assertSame(2, $submission->manuscriptVersions()->count());
    }

    public function test_unapproved_research_is_never_pruned(): void
    {
        $submission = $this->submission(['status' => SubmissionStatus::UNDER_REVIEW]);
        $old = $this->snapshot($submission, 1, now()->subDays(3));
        $this->snapshot($submission, 2, now()->subHour());
        app(ApprovedDocumentRetention::class)->prune($submission);
        Storage::disk('local')->assertExists($old->path);
    }
}
