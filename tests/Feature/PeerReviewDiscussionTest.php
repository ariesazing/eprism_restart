<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PeerReviewDiscussionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_reviewers_and_admin_can_use_the_discussion_thread_but_no_one_else_can(): void
    {
        $admin = User::factory()->admin()->create();
        [$reviewerA, $reviewerB, $unassignedReviewer] = User::factory()->reviewer()->count(3)->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $submission->reviewers()->attach([$reviewerA->id, $reviewerB->id]);

        $response = $this->actingAs($reviewerA)
            ->postJson(route('reviewer.submissions.discussion.store', $submission), ['body' => 'Section 3 seems light on methodology.']);

        $response->assertCreated();
        $messageId = $response->json('id');

        // The other assigned reviewer sees it.
        $this->actingAs($reviewerB)
            ->getJson(route('reviewer.submissions.discussion.index', $submission))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Section 3 seems light on methodology.']);

        // Admin sees it too and can moderate (delete) it.
        $this->actingAs($admin)
            ->getJson(route('admin.submissions.discussion.index', $submission))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Section 3 seems light on methodology.']);

        // A reviewer who was never assigned to this submission is denied.
        $this->actingAs($unassignedReviewer)
            ->getJson(route('reviewer.submissions.discussion.index', $submission))
            ->assertForbidden();

        $this->actingAs($unassignedReviewer)
            ->postJson(route('reviewer.submissions.discussion.store', $submission), ['body' => 'Sneaking in.'])
            ->assertForbidden();

        // The researcher who owns the submission can never reach the discussion at all —
        // there is no researcher-facing route for it.
        $this->assertFalse(Route::has('submissions.discussion.index'));

        // Admin can delete another reviewer's message; a non-author reviewer cannot.
        $this->actingAs($reviewerB)
            ->deleteJson(route('reviewer.submissions.discussion.destroy', [$submission, $messageId]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson(route('admin.submissions.discussion.destroy', [$submission, $messageId]))
            ->assertOk();

        $this->assertDatabaseMissing('submission_discussion_messages', ['id' => $messageId]);
    }

    public function test_discussion_is_blocked_while_the_submission_is_still_a_draft(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Draft Study',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $submission->reviewers()->attach($reviewer->id);

        $this->actingAs($reviewer)
            ->getJson(route('reviewer.submissions.discussion.index', $submission))
            ->assertForbidden();
    }

    public function test_reviewer_only_sees_peer_evaluations_after_submitting_their_own(): void
    {
        [$reviewerA, $reviewerB] = User::factory()->reviewer()->count(2)->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::UNDER_REVIEW,
        ]);

        $submission->reviewers()->attach([$reviewerA->id, $reviewerB->id]);

        $tierSelections = collect(ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'excellent'])->all();

        Review::create([
            'research_submission_id' => $submission->id,
            'reviewer_id' => $reviewerB->id,
            'criteria_scores' => ResearchEvaluationRubric::scoreFromTiers($tierSelections),
            'comments' => 'A distinctive peer comment from reviewer B.',
            'recommendation' => 'approve',
            'submitted_at' => now(),
        ]);

        // Reviewer A hasn't submitted their own evaluation yet — reviewer B's is hidden.
        $this->actingAs($reviewerA)
            ->get(route('reviewer.submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('A distinctive peer comment from reviewer B.');

        Review::create([
            'research_submission_id' => $submission->id,
            'reviewer_id' => $reviewerA->id,
            'criteria_scores' => ResearchEvaluationRubric::scoreFromTiers($tierSelections),
            'comments' => 'My own evaluation.',
            'recommendation' => 'approve',
            'submitted_at' => now(),
        ]);

        // Now that reviewer A has submitted, reviewer B's evaluation becomes visible.
        $this->actingAs($reviewerA)
            ->get(route('reviewer.submissions.show', $submission))
            ->assertOk()
            ->assertSee('A distinctive peer comment from reviewer B.');
    }
}
