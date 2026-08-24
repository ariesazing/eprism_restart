<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\DocumentComment;
use App\Models\User;
use App\Notifications\SubmissionDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_researcher_topbar_merges_feedback_and_notifications_into_one_bell(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Merged Bell Study',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::UNDER_REVIEW,
            'reference_code' => 'REF-BELL-1',
        ]);

        $review = $submission->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'criteria_scores' => ['originality' => 4, 'methodology' => 4, 'clarity' => 4, 'compliance' => 4],
            'comments' => 'See inline notes.',
            'recommendation' => 'minor_revision',
            'submitted_at' => now(),
        ]);

        DocumentComment::create([
            'research_submission_id' => $submission->id,
            'review_id' => $review->id,
            'author_id' => $reviewer->id,
            'page_number' => 1,
            'anchor' => [],
            'body' => 'Please expand the methodology section.',
        ]);

        $researcher->notify(new SubmissionDecisionNotification($submission, 'Revisions requested'));

        $response = $this->actingAs($researcher)->get(route('dashboard'));

        $response->assertOk();
        // Only one bell button now (title="Notifications"), not a second "Reviewer feedback" one.
        $response->assertSeeInOrder(['title="Notifications"']);
        $this->assertSame(1, substr_count($response->getContent(), 'title="Notifications"'));
        $response->assertDontSee('title="Reviewer feedback"', false);
        $response->assertDontSee('Reviewer Feedback</p>', false);

        $response->assertSee('Please expand the methodology section.');
        $response->assertSee('Revisions requested');
    }
}
