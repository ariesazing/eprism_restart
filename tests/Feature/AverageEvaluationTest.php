<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Mail\EvaluationRejectedMail;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\SubmissionDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AverageEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function research(): array
    {
        Mail::fake();
        Notification::fake();
        $researcher = User::factory()->create();
        $reviewers = User::factory()->reviewer()->count(2)->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Averaged evaluation', 'research_type' => 'basic',
            'classification' => 'completed', 'status' => SubmissionStatus::UNDER_REVIEW,
        ]);
        $submission->reviewers()->attach($reviewers->modelKeys());
        return [$submission, $reviewers];
    }

    private function score(ResearchSubmission $submission, int $total): array
    {
        $payload = ['comments' => 'Feedback for score '.$total, 'recommendation' => 'approve'];
        foreach (ResearchEvaluationRubric::for($submission->research_type, $submission->classification)->leaves() as $leaf) {
            $payload[$leaf->key] = min($total, $leaf->max);
            $total -= $payload[$leaf->key];
        }
        return $payload;
    }

    public function test_low_score_is_flagged_but_waits_for_every_assigned_reviewer(): void
    {
        [$submission, $reviewers] = $this->research();
        $submission->reviews()->create(['reviewer_id' => $reviewers[1]->id, 'criteria_scores' => [], 'comments' => '', 'recommendation' => 'revision']);
        $this->actingAs($reviewers[0])->postJson(route('reviewer.submissions.review', $submission), $this->score($submission, 40))->assertOk();
        $this->assertSame('reject', $submission->reviews()->where('reviewer_id', $reviewers[0]->id)->first()->recommendation);
        $this->assertSame(SubmissionStatus::UNDER_REVIEW, $submission->fresh()->status);
        $this->assertNull($submission->fresh()->evaluation_results);
        $this->assertTrue($submission->fresh()->isLocked());
        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_average_of_exactly_seventy_passes_despite_one_low_score(): void
    {
        [$submission, $reviewers] = $this->research();
        foreach ([40, 100] as $i => $score) {
            $this->actingAs($reviewers[$i])->postJson(route('reviewer.submissions.review', $submission), $this->score($submission, $score))->assertOk();
        }
        $result = $submission->fresh()->evaluation_results[0];
        $this->assertSame(SubmissionStatus::APPROVED, $submission->fresh()->status);
        $this->assertEquals(70, $result['average']);
        $this->assertSame('approved', $result['outcome']);
        $this->assertSame('rejected', $result['reviews'][0]['result']);
    }

    public function test_average_below_seventy_returns_complete_feedback_and_unlocks_research(): void
    {
        [$submission, $reviewers] = $this->research();
        foreach ([69, 70] as $i => $score) {
            $this->actingAs($reviewers[$i])->postJson(route('reviewer.submissions.review', $submission), $this->score($submission, $score))->assertOk();
        }
        $submission->refresh();
        $this->assertSame(SubmissionStatus::REVISIONS_REQUIRED, $submission->status);
        $this->assertFalse($submission->isLocked());
        $this->assertSame('rejected', $submission->evaluation_results[0]['outcome']);
        $this->assertEquals(69.5, $submission->evaluation_results[0]['average']);
        $this->assertCount(2, $submission->evaluation_results[0]['reviews']);
        Mail::assertQueued(EvaluationRejectedMail::class, 1);
        $this->actingAs($submission->researcher)->get(route('submissions.show', $submission))
            ->assertOk()->assertSee('69.50%')->assertSee('Your research is now editable')->assertSee('Feedback for score 69');
        $this->actingAs($reviewers[0])->postJson(route('reviewer.submissions.review', $submission), $this->score($submission, 100))->assertForbidden();
        app(SubmissionDecisionService::class)->evaluate($submission);
        $this->assertCount(1, $submission->fresh()->evaluation_results);
        Mail::assertQueued(EvaluationRejectedMail::class, 1);
    }

    public function test_removed_reviewers_scores_do_not_affect_average(): void
    {
        [$submission, $reviewers] = $this->research();
        $former = User::factory()->reviewer()->create();
        $submission->reviews()->create(['reviewer_id' => $former->id, 'criteria_scores' => [], 'comments' => 'Old score', 'recommendation' => 'reject', 'submitted_at' => now()]);
        foreach ($reviewers as $reviewer) {
            $this->actingAs($reviewer)->postJson(route('reviewer.submissions.review', $submission), $this->score($submission, 70))->assertOk();
        }
        $this->assertSame(SubmissionStatus::APPROVED, $submission->fresh()->status);
        $this->assertCount(2, $submission->fresh()->evaluation_results[0]['reviews']);
    }

    public function test_unread_discussion_is_personal_persistent_and_only_clears_seen_messages(): void
    {
        [$submission, $reviewers] = $this->research();
        $message = $submission->discussionMessages()->create(['author_id' => $reviewers[0]->id, 'body' => 'New discussion']);
        $this->actingAs($reviewers[0])->getJson(route('reviewer.submissions.discussion.unread', $submission))->assertJson(['unread' => false]);
        $this->actingAs($reviewers[1])->getJson(route('reviewer.submissions.discussion.unread', $submission))->assertJson(['unread' => true]);
        $this->getJson(route('reviewer.submissions.discussion.index', $submission))->assertOk();
        $this->getJson(route('reviewer.submissions.discussion.unread', $submission))->assertJson(['unread' => true]);
        $this->postJson(route('reviewer.submissions.discussion.read', $submission), ['last_message_id' => $message->id])->assertJson(['unread' => false]);
        $next = $submission->discussionMessages()->create(['author_id' => $reviewers[0]->id, 'body' => 'Arrived during reading']);
        $this->postJson(route('reviewer.submissions.discussion.read', $submission), ['last_message_id' => $message->id])->assertJson(['unread' => true]);
        $this->postJson(route('reviewer.submissions.discussion.read', $submission), ['last_message_id' => $next->id])->assertJson(['unread' => false]);
        $this->postJson(route('reviewer.submissions.discussion.read', $submission), ['last_message_id' => $message->id])->assertJson(['unread' => false]);
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.submissions.discussion.unread', $submission))->assertJson(['unread' => true]);
        $this->actingAs(User::factory()->reviewer()->create())->getJson(route('reviewer.submissions.discussion.unread', $submission))->assertForbidden();
        $this->postJson(route('reviewer.submissions.discussion.read', $submission), ['last_message_id' => $next->id])->assertForbidden();
    }
}
