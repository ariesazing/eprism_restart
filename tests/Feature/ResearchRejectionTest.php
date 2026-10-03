<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Mail\SubmissionRejectedMail;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Notifications\SubmissionDecisionNotification;
use App\Services\SubmissionDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResearchRejectionTest extends TestCase
{
    use RefreshDatabase;

    private function submission(string $classification = 'proposal'): ResearchSubmission
    {
        return User::factory()->create()->submissions()->create([
            'title' => 'Research with an unsupported methodology',
            'research_type' => 'basic',
            'classification' => $classification,
            'status' => SubmissionStatus::UNDER_REVIEW,
            'submitted_at' => now(),
        ]);
    }

    private function payload(ResearchSubmission $submission, string $reason = 'The methodology cannot answer the research question.'): array
    {
        $rubric = ResearchEvaluationRubric::for($submission->research_type, $submission->classification);
        return array_merge(array_fill_keys($rubric->leafKeys(), 0), [
            'recommendation' => 'reject',
            'comments' => $reason,
        ]);
    }

    public function test_rejection_is_final_for_both_phases_and_notifies_the_researcher_once(): void
    {
        Mail::fake();
        Notification::fake();
        foreach (['proposal', 'completed'] as $classification) {
            $submission = $this->submission($classification);
            $reviewers = User::factory()->reviewer()->count(2)->create();
            $submission->reviewers()->attach($reviewers->modelKeys());
            $reviewer = $reviewers->first();

            $this->actingAs($reviewer)->postJson(route('reviewer.submissions.review', $submission), $this->payload($submission))->assertOk();
            $submission->refresh();
            $this->assertSame(SubmissionStatus::REJECTED, $submission->status);
            $this->assertSame($classification, $submission->classification);
            $this->assertTrue($submission->isLocked());
            $this->assertStringContainsString('Reviewer 1:', $submission->admin_notes);
            $this->assertStringNotContainsString($reviewer->name, $submission->admin_notes);
            $this->assertNotNull($submission->reviewed_at);
            $this->assertNull($submission->approved_at);
            $this->assertSame(2, $submission->reviewers()->count());
            $this->assertStringContainsString('The methodology cannot answer', (new SubmissionRejectedMail($submission))->render());

            Mail::assertQueued(SubmissionRejectedMail::class, fn ($mail) => $mail->hasTo($submission->researcher->email) && $mail->submission->id === $submission->id);
            Notification::assertSentTo($submission->researcher, SubmissionDecisionNotification::class, function ($notification) use ($submission) {
                $data = $notification->toArray($submission->researcher);
                return $data['title'] === 'Research rejected'
                    && $data['reason'] === $submission->admin_notes
                    && $data['url'] === route('submissions.show', $submission);
            });

            $this->actingAs($reviewers->last())->postJson(route('reviewer.submissions.review', $submission), array_replace($this->payload($submission), ['recommendation' => 'revision']))->assertForbidden();
            app(SubmissionDecisionService::class)->evaluate($submission, $reviewer);
            $this->assertSame(SubmissionStatus::REJECTED, $submission->fresh()->status);
            $this->assertSame(1, $submission->reviews()->count());
        }
        Mail::assertQueued(SubmissionRejectedMail::class, 2);
        Notification::assertCount(2);
    }

    public function test_rejection_requires_a_reason_and_an_assigned_reviewer(): void
    {
        $submission = $this->submission();
        $reviewer = User::factory()->reviewer()->create();
        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.review', $submission), $this->payload($submission))->assertForbidden();
        $submission->reviewers()->attach($reviewer);
        $this->postJson(route('reviewer.submissions.review', $submission), $this->payload($submission, '   '))->assertUnprocessable()->assertJsonValidationErrors('comments');
        $this->assertSame(SubmissionStatus::UNDER_REVIEW, $submission->fresh()->status);
        $this->assertSame(0, $submission->reviews()->count());
    }

    public function test_rejected_record_is_visible_but_cannot_be_edited_or_resubmitted(): void
    {
        $submission = $this->submission();
        $submission->update(['status' => SubmissionStatus::REJECTED, 'admin_notes' => 'Reviewer 1: Unsupported methodology.']);
        $reviewer = User::factory()->reviewer()->create();
        $submission->reviewers()->attach($reviewer);
        $this->actingAs($submission->researcher)->get(route('submissions.show', $submission))
            ->assertOk()->assertSee('Unsupported methodology.')->assertSee('cannot be edited or resubmitted');
        $this->putJson(route('submissions.update', $submission), ['title' => 'Changed'])->assertForbidden();
        $this->patchJson(route('submissions.autosave', $submission), [])->assertForbidden();
        $this->postJson(route('submissions.submit', $submission))->assertForbidden();
        $this->postJson(route('submissions.resubmit', $submission))->assertForbidden();
        $this->get(route('repository.index'))->assertDontSee($submission->title);

        $this->actingAs($reviewer)->get(route('reviewer.submissions.show', $submission))
            ->assertOk()->assertSee('Evaluations are closed.')->assertDontSee('Start Evaluation')->assertDontSee('data-discussion-form', false);
        $this->postJson(route('reviewer.submissions.comments.store', $submission), [])->assertForbidden();
        $this->postJson(route('reviewer.submissions.discussion.store', $submission), ['body' => 'New message'])->assertForbidden();
        $this->getJson(route('reviewer.submissions.discussion.index', $submission))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('submissions.show', $submission))->assertForbidden();
    }

    public function test_reports_and_downloads_include_rejections_and_filter_all_rows(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $submission = $this->submission();
            $submission->update(['title' => 'Rejected study '.$i, 'status' => SubmissionStatus::REJECTED, 'admin_notes' => '=Unsafe formula reason '.$i, 'reviewed_at' => now()]);
        }
        $excluded = $this->submission();
        $excluded->update(['title' => 'Excluded action study', 'research_type' => 'action', 'status' => SubmissionStatus::REJECTED]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.reports', ['research_type' => 'basic']))
            ->assertOk()->assertSee('Rejected Research')->assertSee('Reason for rejection')
            ->assertViewHas('stages', fn ($stages) => $stages['rejected'] === 9)
            ->assertViewHas('rejectedResearch', fn ($records) => $records->total() === 8)
            ->assertDontSee('Excluded action study');
        $download = $this->get(route('admin.reports', ['download' => 'csv', 'research_type' => 'basic', 'rejected_page' => 2]));
        $download->assertOk()->assertDownload();
        $csv = $download->streamedContent();
        for ($i = 0; $i < 8; $i++) {
            $this->assertStringContainsString('Rejected study '.$i, $csv);
            $this->assertStringContainsString("'=Unsafe formula reason ".$i, $csv);
        }
        $this->assertStringNotContainsString('Excluded action study', $csv);
        $this->assertStringContainsString('rejection_reason', $csv);
    }
}
