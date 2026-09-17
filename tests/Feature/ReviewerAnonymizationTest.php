<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RapmDataBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_numbers_are_ordered_by_assignment_order_and_stable_when_a_reviewer_is_added_later(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Numbering Order Test',
            'research_type' => 'basic',
            'classification' => 'proposal',
        ]);

        $first = User::factory()->reviewer()->create();
        $second = User::factory()->reviewer()->create();
        $submission->reviewers()->attach([$first->id, $second->id]);

        $numbers = $submission->fresh()->reviewerNumbers();

        $this->assertSame(1, $numbers[$first->id]);
        $this->assertSame(2, $numbers[$second->id]);

        $third = User::factory()->reviewer()->create();
        $submission->reviewers()->attach($third->id);

        $numbers = $submission->fresh()->reviewerNumbers();

        // Adding a third reviewer later must not renumber the first two.
        $this->assertSame(1, $numbers[$first->id]);
        $this->assertSame(2, $numbers[$second->id]);
        $this->assertSame(3, $numbers[$third->id]);
    }

    public function test_review_summary_data_uses_reviewer_numbers_instead_of_names(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Blind Review Test',
            'research_type' => 'basic',
            'classification' => 'proposal',
        ]);

        $reviewers = User::factory()->reviewer()->count(2)->create();
        $submission->reviewers()->attach($reviewers->pluck('id'));

        $reviews = $reviewers->mapWithKeys(fn (User $reviewer) => [
            $reviewer->id => $submission->reviews()->create([
                'reviewer_id' => $reviewer->id,
                'criteria_scores' => [],
                'comments' => 'Fine.',
                'recommendation' => 'approve',
                'submitted_at' => now(),
            ]),
        ]);

        $data = (new RapmDataBuilder)->buildReviewSummaryData($submission, $reviews);

        $names = collect($data['each']['reviewers'])->pluck('reviewer_name');

        $this->assertSame(['Reviewer 1', 'Reviewer 2'], $names->all());
        $this->assertFalse($names->contains($reviewers->first()->name));
        $this->assertFalse($names->contains($reviewers->last()->name));
    }
}
