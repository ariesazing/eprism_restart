<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchEvaluationRubricTest extends TestCase
{
    use RefreshDatabase;

    /**
     * classification defaults to 'completed' (not 'proposal') so a lone unanimous
     * "approve" doesn't trigger SubmissionDecisionService's proposal-promotion branch,
     * which deletes the submission's reviews as part of resetting it for the next
     * round — that would wipe the very row these tests inspect afterward.
     */
    private function makeReviewedSubmission(User $researcher, User $reviewer, string $classification = 'completed')
    {
        $submission = $researcher->submissions()->create([
            'title' => 'Rubric Gated Study',
            'research_type' => 'basic',
            'classification' => $classification,
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $submission->reviewers()->attach($reviewer->id);

        return $submission;
    }

    public function test_all_excellent_tiers_score_a_perfect_100_and_allow_approval(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeReviewedSubmission($researcher, $reviewer);

        $payload = collect(ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'excellent'])->all();
        $payload['comments'] = 'Excellent work.';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $review = $submission->reviews()->firstOrFail();
        $this->assertSame(ResearchEvaluationRubric::MAX_SCORE, $review->totalScore());
        $this->assertTrue($review->passesRubric());
        $this->assertSame('approve', $review->recommendation);
    }

    public function test_a_below_threshold_score_cannot_be_approved(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeReviewedSubmission($researcher, $reviewer);

        // All "fair" tiers total 14+20+18+10+10 = 72, below the 85 passing score.
        $payload = collect(ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'fair'])->all();
        $payload['comments'] = 'Needs significant work.';
        $payload['recommendation'] = 'approve';

        $response = $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload);

        $response->assertSessionHasErrors('recommendation');
        $this->assertSame(0, $submission->reviews()->count());
    }

    public function test_a_below_threshold_score_can_still_request_revisions(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeReviewedSubmission($researcher, $reviewer);

        $payload = collect(ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'fair'])->all();
        $payload['comments'] = 'Needs significant work.';
        $payload['recommendation'] = 'major_revision';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $review = $submission->reviews()->firstOrFail();
        $this->assertSame(72, $review->totalScore());
        $this->assertFalse($review->passesRubric());
    }

    public function test_an_invalid_tier_value_is_rejected(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeReviewedSubmission($researcher, $reviewer);

        $payload = collect(ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'excellent'])->all();
        $payload['clear_focus'] = 'outstanding';
        $payload['comments'] = 'x';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertSessionHasErrors('clear_focus');
    }

    public function test_a_score_exactly_at_the_passing_threshold_can_be_approved(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeReviewedSubmission($researcher, $reviewer);

        // clear_focus excellent (20) + research good (22) + reasoning_organization good (21)
        // + documentation good (12) + writing_mechanics fair (10) = 85 exactly.
        $payload = [
            'clear_focus' => 'excellent',
            'research' => 'good',
            'reasoning_organization' => 'good',
            'documentation' => 'good',
            'writing_mechanics' => 'fair',
        ];
        $payload['comments'] = 'Right at the line.';
        $payload['recommendation'] = 'approve';

        $response = $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload);

        $response->assertRedirect()->assertSessionDoesntHaveErrors();
        $review = $submission->reviews()->firstOrFail();
        $this->assertSame(ResearchEvaluationRubric::PASSING_SCORE, $review->totalScore());
    }
}
