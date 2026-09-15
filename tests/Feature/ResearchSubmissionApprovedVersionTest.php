<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the "repository links are not tied to the approved manuscript version" gap directly
 * at the model level: ResearchSubmission::approvedManuscriptVersion()/snapshotApprovedAsOf()
 * are what let submission-card.blade.php resolve each card to the document that specific
 * classification stage was actually approved with, rather than whatever's current on the
 * submission today — which, once a proposal is promoted to completed research in place, is no
 * longer the same thing.
 */
class ResearchSubmissionApprovedVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_manuscript_version_resolves_independently_per_classification(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Promoted Study', 'research_type' => 'basic', 'classification' => 'completed',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
        ]);

        $proposalSnapshot = $submission->snapshots()->create(['version' => 1, 'path' => 'x', 'generated_by' => $researcher->id, 'generated_at' => now()->subDays(2)]);
        $proposalVersion = $submission->manuscriptVersions()->create([
            'attempt' => (string) Str::uuid(), 'research_snapshot_id' => $proposalSnapshot->id, 'created_by' => $researcher->id,
            'state' => 'ready', 'docx_path' => 'a', 'docx_hash' => str_repeat('a', 64), 'template_path' => 'b', 'template_hash' => str_repeat('b', 64),
            'metadata' => ['classification' => 'proposal'], 'attachments' => [], 'approved_at' => now()->subDay(),
        ]);

        $completedSnapshot = $submission->snapshots()->create(['version' => 2, 'path' => 'y', 'generated_by' => $researcher->id, 'generated_at' => now()]);
        $completedVersion = $submission->manuscriptVersions()->create([
            'attempt' => (string) Str::uuid(), 'research_snapshot_id' => $completedSnapshot->id, 'created_by' => $researcher->id,
            'state' => 'ready', 'docx_path' => 'c', 'docx_hash' => str_repeat('c', 64), 'template_path' => 'd', 'template_hash' => str_repeat('d', 64),
            'metadata' => ['classification' => 'completed'], 'attachments' => [], 'approved_at' => now(),
        ]);

        $this->assertTrue($submission->approvedManuscriptVersion('proposal')->is($proposalVersion));
        $this->assertTrue($submission->approvedManuscriptVersion('completed')->is($completedVersion));
        // The old bug this fixes: naively reading "the current/latest manuscript version"
        // regardless of stage would have returned the completed one here for both.
        $this->assertNotSame(
            $submission->approvedManuscriptVersion('proposal')->id,
            $submission->approvedManuscriptVersion('completed')->id,
        );
    }

    public function test_approved_manuscript_version_ignores_a_not_yet_approved_candidate(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'In Progress', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
        ]);

        $submission->manuscriptVersions()->create([
            'attempt' => (string) Str::uuid(), 'created_by' => $researcher->id,
            'state' => 'processing', 'docx_path' => 'a', 'docx_hash' => str_repeat('a', 64), 'template_path' => 'b', 'template_hash' => str_repeat('b', 64),
            'metadata' => ['classification' => 'proposal'], 'attachments' => [], 'approved_at' => null,
        ]);

        $this->assertNull($submission->approvedManuscriptVersion('proposal'));
    }

    public function test_snapshot_approved_as_of_finds_the_snapshot_that_existed_at_approval_time_not_a_later_one(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Legacy Engine Study', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $atApproval = now()->subDays(3);
        $before = $submission->snapshots()->create(['version' => 1, 'path' => 'x', 'generated_by' => $researcher->id, 'generated_at' => $atApproval->copy()->subHour()]);
        $submission->snapshots()->create(['version' => 2, 'path' => 'y', 'generated_by' => $researcher->id, 'generated_at' => $atApproval->copy()->addDay()]);

        $resolved = $submission->snapshotApprovedAsOf($atApproval);

        $this->assertTrue($resolved->is($before));
    }

    public function test_snapshot_approved_as_of_returns_null_without_an_approval_timestamp(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Draft Study', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);
        $submission->snapshots()->create(['version' => 1, 'path' => 'x', 'generated_by' => $researcher->id, 'generated_at' => now()]);

        $this->assertNull($submission->snapshotApprovedAsOf(null));
    }
}
