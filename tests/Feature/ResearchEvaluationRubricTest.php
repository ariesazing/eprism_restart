<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Evaluation\RubricItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four official DepEd scoring templates (see App\Evaluation\ResearchEvaluationRubric's own
 * class doc) — one per (research_type, classification), each totaling 100 points with no
 * excellent/good/fair tiers and no passing cutoff. Structural self-checks first (guarding
 * against a transcription mistake from the source .docx forms), then the reviewer-facing flow.
 */
class ResearchEvaluationRubricTest extends TestCase
{
    use RefreshDatabase;

    public static function templateKeys(): array
    {
        return [
            'basic_proposal' => ['basic', 'proposal'],
            'action_proposal' => ['action', 'proposal'],
            'basic_completed' => ['basic', 'completed'],
            'action_completed' => ['action', 'completed'],
        ];
    }

    /**
     * @dataProvider templateKeys
     */
    public function test_every_template_totals_exactly_100_points(string $researchType, string $classification): void
    {
        $template = ResearchEvaluationRubric::for($researchType, $classification);

        $this->assertSame(ResearchEvaluationRubric::MAX_SCORE, $template->max());
        $this->assertSame("{$researchType}_{$classification}", $template->key);
    }

    /**
     * @dataProvider templateKeys
     */
    public function test_every_parents_max_equals_the_sum_of_its_childrens_max(string $researchType, string $classification): void
    {
        $assertItem = function (RubricItem $item) use (&$assertItem) {
            if (! $item->isLeaf()) {
                $this->assertSame(
                    $item->max,
                    array_sum(array_map(fn (RubricItem $child) => $child->max, $item->children)),
                    "Item '{$item->code}' ({$item->label}) max does not match the sum of its children."
                );
            }

            array_map($assertItem, $item->children);
        };

        foreach (ResearchEvaluationRubric::for($researchType, $classification)->leaves() as $leaf) {
            $this->assertTrue($leaf->isLeaf());
        }

        foreach (ResearchEvaluationRubric::for($researchType, $classification)->sections as $section) {
            array_map($assertItem, $section->items);
        }
    }

    /**
     * @dataProvider templateKeys
     */
    public function test_every_leaf_key_is_unique_within_its_own_template(string $researchType, string $classification): void
    {
        $keys = ResearchEvaluationRubric::for($researchType, $classification)->leafKeys();

        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_the_two_completed_templates_have_two_named_fifty_point_sections_and_proposals_have_one(): void
    {
        foreach (['basic_completed', 'action_completed'] as $key) {
            $sections = ResearchEvaluationRubric::forKey($key)->sections;
            $this->assertCount(2, $sections);
            $this->assertSame('Manuscript', $sections[0]->label);
            $this->assertSame('Utilization and Dissemination', $sections[1]->label);
            $this->assertSame(50, $sections[0]->max());
            $this->assertSame(50, $sections[1]->max());
        }

        foreach (['basic_proposal', 'action_proposal'] as $key) {
            $sections = ResearchEvaluationRubric::forKey($key)->sections;
            $this->assertCount(1, $sections);
            $this->assertNull($sections[0]->label);
        }
    }

    public function test_an_unknown_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ResearchEvaluationRubric::forKey('completed_basic');
    }

    public function test_total_score_sums_whatever_leaf_scores_are_given(): void
    {
        $this->assertSame(0, ResearchEvaluationRubric::totalScore([]));
        $this->assertSame(15, ResearchEvaluationRubric::totalScore(['a' => 10, 'b' => '5']));
    }

    public function test_breakdown_computes_a_parents_score_as_its_childrens_sum_never_from_stored_data(): void
    {
        $template = ResearchEvaluationRubric::for('basic', 'proposal');
        // A stray value under the parent's own key ('research_methods') must be ignored.
        $scores = ['rationale' => 10, 'participants_sources' => 10, 'data_gathering_methods' => 20, 'data_analysis_plan' => 10, 'research_methods' => 999];

        $breakdown = ResearchEvaluationRubric::breakdown($template, $scores);
        $researchMethods = collect($breakdown[0]['items'])->firstWhere('code', 'D');

        $this->assertSame(40, $researchMethods['score']);
        $this->assertFalse($researchMethods['leaf']);
        $this->assertNull($researchMethods['key']);
        $this->assertCount(3, $researchMethods['children']);
    }

    /**
     * Two reviewers, not one — a lone reviewer's "approve" on a 'proposal' classification is a
     * unanimous approval, which SubmissionDecisionService promotes immediately (deleting every
     * Review row as part of resetting the submission for its next round). A second, silent
     * reviewer keeps every test below able to inspect the review it just created regardless of
     * classification or recommendation, without that being what each test is actually about.
     */
    private function submissionFor(string $researchType, string $classification): array
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Rubric Test Study',
            'research_type' => $researchType,
            'classification' => $classification,
            'status' => SubmissionStatus::SUBMITTED,
        ]);
        $submission->reviewers()->attach([$reviewer->id, User::factory()->reviewer()->create()->id]);

        return [$submission, $reviewer];
    }

    /**
     * @dataProvider templateKeys
     */
    public function test_a_reviewer_can_score_every_criterion_of_each_templates_own_rubric(string $researchType, string $classification): void
    {
        [$submission, $reviewer] = $this->submissionFor($researchType, $classification);
        $rubric = ResearchEvaluationRubric::for($researchType, $classification);

        $payload = collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all();
        $payload['comments'] = 'Scored against the real template.';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $review = $submission->reviews()->firstOrFail();
        $this->assertSame("{$researchType}_{$classification}", $review->rubric_key);
        $this->assertSame(100, $review->totalScore());
        $this->assertSame('approve', $review->recommendation);
    }

    public function test_a_missing_criterion_is_rejected(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');
        $rubric = ResearchEvaluationRubric::for('basic', 'proposal');

        $payload = collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all();
        unset($payload['rationale']);
        $payload['comments'] = 'x';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertSessionHasErrors('rationale');

        $this->assertSame(0, $submission->reviews()->count());
    }

    public function test_a_score_above_a_criterions_own_max_is_rejected(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');
        $rubric = ResearchEvaluationRubric::for('basic', 'proposal');

        $payload = collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all();
        $payload['rationale'] = 11; // max is 10
        $payload['comments'] = 'x';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertSessionHasErrors('rationale');
    }

    public function test_a_negative_score_is_rejected(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');
        $rubric = ResearchEvaluationRubric::for('basic', 'proposal');

        $payload = collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all();
        $payload['rationale'] = -1;
        $payload['comments'] = 'x';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertSessionHasErrors('rationale');
    }

    /**
     * There is no passing cutoff anymore — none of the four source templates states one.
     * A reviewer's recommendation is entirely their own call, independent of the score.
     */
    public function test_a_zero_score_can_still_be_approved(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');
        $rubric = ResearchEvaluationRubric::for('basic', 'proposal');

        $payload = array_fill_keys($rubric->leafKeys(), 0);
        $payload['comments'] = 'Approved despite a zero score — scoring is informational only.';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $review = $submission->reviews()->firstOrFail();
        $this->assertSame(0, $review->totalScore());
        $this->assertSame('approve', $review->recommendation);
    }

    public function test_a_promoted_proposal_is_scored_against_the_completed_rubric_afterwards(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');
        $submission->update(['classification' => 'completed']); // as SubmissionDecisionService does on promotion

        $rubric = ResearchEvaluationRubric::for('basic', 'completed');
        $payload = array_fill_keys($rubric->leafKeys(), 1);
        $payload['comments'] = 'Now scored as completed research.';
        $payload['recommendation'] = 'approve';

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('basic_completed', $submission->reviews()->firstOrFail()->rubric_key);
    }

    public function test_an_existing_reviews_rubric_is_read_from_its_own_stored_key_not_the_submissions_current_one(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');
        $review = $submission->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'rubric_key' => 'basic_proposal',
            'criteria_scores' => ['rationale' => 10],
            'comments' => 'x',
            'recommendation' => 'approve',
            'submitted_at' => now(),
        ]);

        // The submission has since moved on to 'completed' (promotion overwrites classification
        // in place — see SubmissionDecisionService), but this review's own rubric_key still says
        // what it was actually scored against.
        $submission->update(['classification' => 'completed']);

        $this->assertSame('basic_proposal', $review->fresh()->rubric()->key);
        $this->assertSame(10, $review->fresh()->totalScore());
    }

    public function test_a_review_with_no_rubric_key_reports_an_empty_breakdown_rather_than_erroring(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');
        $review = $submission->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'criteria_scores' => ['whatever' => 4],
            'comments' => 'x',
            'recommendation' => 'approve',
            'submitted_at' => now(),
        ]);

        $this->assertNull($review->rubric());
        $this->assertSame([], $review->breakdown());
        $this->assertSame(4, $review->totalScore());
    }

    public function test_score_inputs_are_typed_centered_text_fields_not_number_spinners(): void
    {
        [$submission, $reviewer] = $this->submissionFor('basic', 'proposal');

        $html = $this->actingAs($reviewer)->get(route('reviewer.submissions.show', $submission))->assertOk()->getContent();

        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('@input="setScore(item.key, item.max, $event)"', $html);
        $this->assertStringContainsString('text-center', $html);
        $this->assertStringNotContainsString('x-model.number="scores[', $html);
    }
}
