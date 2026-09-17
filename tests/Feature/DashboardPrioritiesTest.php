<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPrioritiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assignment_count_excludes_drafts_and_assigned_submissions(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        foreach ([SubmissionStatus::DRAFT, SubmissionStatus::SUBMITTED, SubmissionStatus::UNDER_REVIEW] as $status) {
            $submission = $researcher->submissions()->create([
                'title' => $status->value, 'research_type' => 'basic', 'classification' => 'proposal', 'status' => $status,
            ]);
            if ($status === SubmissionStatus::UNDER_REVIEW) {
                $submission->reviewers()->attach($reviewer);
            }
        }

        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
            ->assertOk()->assertViewHas('data', fn ($data) => $data['unassignedCount'] === 1)
            ->assertViewHas('data', fn ($data) => $data['summaryTotal'] === 2
                && (int) $data['summaryStatusCounts']['submitted'] === 1
                && (int) $data['summaryStatusCounts']['under_review'] === 1)
            ->assertSee('images/eprism-prism.png')
            ->assertSeeInOrder(['Workflow priorities', 'Operational Oversight', 'Researches', 'Recent Activity']);
    }

    public function test_researcher_revision_is_prioritized_over_a_newer_draft(): void
    {
        $user = User::factory()->create();
        foreach ([SubmissionStatus::REVISIONS_REQUIRED, SubmissionStatus::DRAFT] as $status) {
            $user->submissions()->create([
                'title' => $status->value, 'research_type' => 'basic', 'classification' => 'proposal', 'status' => $status,
            ]);
        }

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Pick up where you left off: revisions_required')
            ->assertSeeInOrder(['Your next step', 'Submission Readiness', 'My Submissions']);
    }

    public function test_reviewer_totals_are_not_limited_to_recent_evaluations(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $submission = $researcher->submissions()->create([
                'title' => 'Study '.$i, 'research_type' => 'basic', 'classification' => 'proposal',
                'status' => $i === 0 ? SubmissionStatus::UNDER_REVIEW : SubmissionStatus::APPROVED,
            ]);
            $submission->reviewers()->attach($reviewer);
            $submission->reviews()->create([
                'reviewer_id' => $reviewer->id, 'recommendation' => 'approve', 'submitted_at' => now(),
                'criteria_scores' => [], 'comments' => 'Reviewed.',
            ]);
        }

        $this->actingAs($reviewer)->get(route('dashboard'))->assertOk()
            ->assertViewHas('data', fn ($data) => $data['submittedReviewCount'] === 12
                && $data['reviews']->count() === 10 && $data['activeAssignmentCount'] === 1
                && $data['assignedSubmissions']->first()->title === 'Study 0');
    }
}
