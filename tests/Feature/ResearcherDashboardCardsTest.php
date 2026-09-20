<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearcherDashboardCardsTest extends TestCase
{
    use RefreshDatabase;

    private function submission(User $user, SubmissionStatus $status, array $attributes = []): ResearchSubmission
    {
        return $user->submissions()->create($attributes + [
            'title' => 'Study '.$status->value, 'research_type' => 'basic', 'classification' => 'proposal', 'status' => $status,
        ]);
    }

    public function test_drafts_card_counts_only_this_researchers_unsubmitted_drafts(): void
    {
        $user = User::factory()->create();
        $this->submission($user, SubmissionStatus::DRAFT);
        $this->submission($user, SubmissionStatus::DRAFT);
        $this->submission($user, SubmissionStatus::SUBMITTED);
        $this->submission($user, SubmissionStatus::REVISIONS_REQUIRED);
        // Someone else's drafts are not mine.
        $this->submission(User::factory()->create(), SubmissionStatus::DRAFT);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('data', fn ($data) => (int) $data['statusCounts']['draft'] === 2)
            ->assertSeeInOrder(['Total Submissions', 'Drafts', 'Under Review', 'Approved', 'Revision Required'])
            ->assertSee('Not yet submitted for review');
    }

    public function test_approved_card_counts_every_approval_whether_proposal_or_completed(): void
    {
        $user = User::factory()->create();

        // A proposal approved and now being worked on as completed research — its status was reset
        // to draft on promotion, but the approval still happened.
        $this->submission($user, SubmissionStatus::DRAFT, ['proposal_approved_at' => now()->subMonth()]);
        // Approved as a proposal, then again as completed research: two approvals.
        $this->submission($user, SubmissionStatus::APPROVED, ['classification' => 'completed', 'proposal_approved_at' => now()->subMonths(2), 'approved_at' => now()]);
        // A research approved with no proposal stage on record.
        $this->submission($user, SubmissionStatus::APPROVED, ['classification' => 'completed', 'approved_at' => now()]);
        // Not approved: in review / needs revision / plain draft.
        $this->submission($user, SubmissionStatus::UNDER_REVIEW);
        $this->submission($user, SubmissionStatus::REVISIONS_REQUIRED);
        $this->submission($user, SubmissionStatus::DRAFT);
        // Another researcher's approvals are not mine.
        $this->submission(User::factory()->create(), SubmissionStatus::APPROVED, ['proposal_approved_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('data', fn ($data) => $data['approvedTotal'] === 4)
            ->assertSee('Approved proposals and completed research');
    }

    public function test_a_researcher_with_nothing_yet_sees_zeros(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
            ->assertViewHas('data', fn ($data) => $data['approvedTotal'] === 0 && ($data['statusCounts']['draft'] ?? 0) === 0)
            ->assertSee('Drafts');
    }

    public function test_the_drafts_card_is_researcher_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertOk()
            ->assertDontSee('Not yet submitted for review');
        $this->actingAs(User::factory()->reviewer()->create())->get(route('dashboard'))->assertOk()
            ->assertDontSee('Not yet submitted for review');
    }
}
