<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Mail\ReviewSummaryReadyMail;
use App\Mail\RoutingSlipReadyMail;
use App\Mail\SubmissionApprovedMail;
use App\Mail\SubmissionRevisionsRequiredMail;
use App\Models\RapmDocument;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Notifications\SubmissionDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RapmTest extends TestCase
{
    use RefreshDatabase;

    private function approvingReviewPayload(string $comments): array
    {
        return array_merge(
            collect(\App\Evaluation\ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'excellent'])->all(),
            ['comments' => $comments, 'recommendation' => 'approve'],
        );
    }

    private function revisionReviewPayload(string $comments, string $recommendation = 'minor_revision'): array
    {
        return array_merge(
            collect(\App\Evaluation\ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'fair'])->all(),
            ['comments' => $comments, 'recommendation' => $recommendation],
        );
    }

    private function seedTemplates(): void
    {
        SubmissionDocumentTemplate::create([
            'template_key' => RapmDocument::KIND_REVIEW_SUMMARY,
            'body_html' => '<p>${title} - ${overall_recommendation_label}</p>{{#each reviewers}}<p>${reviewer_name}: ${recommendation_label}</p>{{/each}}',
        ]);

        SubmissionDocumentTemplate::create([
            'template_key' => RapmDocument::KIND_ROUTING_SLIP,
            'body_html' => '<p>${title} - ${current_status_label}</p>{{#each routing_steps}}<p>${action_label}</p>{{/each}}',
        ]);
    }

    public function test_review_summary_is_generated_and_emailed_once_all_reviewers_finish(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seedTemplates();

        $admin = User::factory()->admin()->create();
        $reviewers = User::factory()->reviewer()->count(3)->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $this->actingAs($admin)->patch(route('admin.submissions.assign-reviewer', $submission), [
            'reviewer_ids' => $reviewers->pluck('id')->all(),
        ])->assertRedirect();

        foreach ($reviewers as $index => $reviewer) {
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Looks good.'))->assertRedirect();

            $submission->refresh();

            if ($index < 2) {
                $this->assertNull($submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY));
            }
        }

        $submission->refresh();

        $document = $submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY);
        $this->assertNotNull($document);
        $this->assertSame(1, $document->version);

        Mail::assertQueued(ReviewSummaryReadyMail::class);
        Notification::assertSentTo($researcher->fresh(), SubmissionDecisionNotification::class);
    }

    public function test_revision_request_still_generates_review_summary_and_sends_revision_notice(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seedTemplates();

        $admin = User::factory()->admin()->create();
        $reviewers = User::factory()->reviewer()->count(3)->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $this->actingAs($admin)->patch(route('admin.submissions.assign-reviewer', $submission), [
            'reviewer_ids' => $reviewers->pluck('id')->all(),
        ])->assertRedirect();

        $this->actingAs($reviewers[0])->post(route('reviewer.submissions.review', $submission), $this->revisionReviewPayload('Needs more data.'))->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::REVISIONS_REQUIRED, $submission->status);
        // Only 1 of 3 reviewers has reviewed so far — no Review Summary yet.
        $this->assertNull($submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY));

        $this->actingAs($reviewers[1])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Fine.'))->assertRedirect();

        $this->actingAs($reviewers[2])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Fine.'))->assertRedirect();

        $submission->refresh();

        $this->assertNotNull($submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY));
        Mail::assertQueued(SubmissionRevisionsRequiredMail::class);
        Mail::assertQueued(ReviewSummaryReadyMail::class);
    }

    public function test_final_approval_generates_routing_slip_and_sends_approval_notice(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seedTemplates();

        $admin = User::factory()->admin()->create();
        $reviewers = User::factory()->reviewer()->count(3)->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $this->actingAs($admin)->patch(route('admin.submissions.assign-reviewer', $submission), [
            'reviewer_ids' => $reviewers->pluck('id')->all(),
        ])->assertRedirect();

        foreach ($reviewers as $reviewer) {
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Great.'))->assertRedirect();
        }

        $submission->refresh();

        $this->assertSame(SubmissionStatus::APPROVED, $submission->status);
        $this->assertNotNull($submission->latestRapmDocument(RapmDocument::KIND_ROUTING_SLIP));

        Mail::assertQueued(SubmissionApprovedMail::class);
        Mail::assertQueued(RoutingSlipReadyMail::class);
    }

    public function test_researcher_can_download_their_own_review_summary(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seedTemplates();

        $admin = User::factory()->admin()->create();
        $reviewers = User::factory()->reviewer()->count(3)->create();
        $researcher = User::factory()->create();
        $other = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $this->actingAs($admin)->patch(route('admin.submissions.assign-reviewer', $submission), [
            'reviewer_ids' => $reviewers->pluck('id')->all(),
        ])->assertRedirect();

        foreach ($reviewers as $reviewer) {
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Fine.'))->assertRedirect();
        }

        $submission->refresh();
        $document = $submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY);

        $this->actingAs($researcher)->get(route('rapm-documents.show', $document))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($other)->get(route('rapm-documents.show', $document))
            ->assertForbidden();

        $this->actingAs($admin)->get(route('rapm-documents.show', $document))
            ->assertOk();
    }

    public function test_assigned_reviewer_can_preview_an_approved_review_summary_but_not_a_revisions_required_one(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seedTemplates();

        $admin = User::factory()->admin()->create();
        $reviewers = User::factory()->reviewer()->count(3)->create();
        $unrelatedReviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        // Completed classification so a unanimous approve doesn't promote-and-wipe reviews.
        $submission = $researcher->submissions()->create([
            'title' => 'Community Health Literacy',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $this->actingAs($admin)->patch(route('admin.submissions.assign-reviewer', $submission), [
            'reviewer_ids' => $reviewers->pluck('id')->all(),
        ])->assertRedirect();

        $this->actingAs($reviewers[0])->post(route('reviewer.submissions.review', $submission), $this->revisionReviewPayload('Needs work.'))->assertRedirect();
        $this->actingAs($reviewers[1])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Fine.'))->assertRedirect();
        $this->actingAs($reviewers[2])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Fine.'))->assertRedirect();

        $submission->refresh();
        $revisionRoundDocument = $submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY);
        $this->assertSame(SubmissionStatus::REVISIONS_REQUIRED, $submission->status);

        // A reviewer cannot preview a review-summary whose round ended in revisions.
        $this->actingAs($reviewers[0])->get(route('rapm-documents.show', $revisionRoundDocument))
            ->assertForbidden();

        // Everyone resubmits approve on the next round — travel forward first so the
        // review's updated_at (and therefore the review-summary fingerprint) actually
        // differs from the revision round; both happening within the same second would
        // otherwise make maybeGenerate() see a matching fingerprint and skip regenerating.
        $this->travel(1)->seconds();
        $this->actingAs($reviewers[0])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Now fine.'))->assertRedirect();

        $submission->refresh();
        $approvedRoundDocument = $submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY);
        $this->assertSame(SubmissionStatus::APPROVED, $submission->status);
        $this->assertNotSame($revisionRoundDocument->id, $approvedRoundDocument->id);

        foreach ($reviewers as $reviewer) {
            $this->actingAs($reviewer)->get(route('rapm-documents.show', $approvedRoundDocument))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }

        // A reviewer never assigned to this submission still can't preview it.
        $this->actingAs($unrelatedReviewer)->get(route('rapm-documents.show', $approvedRoundDocument))
            ->assertForbidden();
    }
}
