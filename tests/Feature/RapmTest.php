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
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), [
                'originality' => 4,
                'methodology' => 5,
                'clarity' => 4,
                'compliance' => 5,
                'comments' => 'Looks good.',
                'recommendation' => 'approve',
            ])->assertRedirect();

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

        $this->actingAs($reviewers[0])->post(route('reviewer.submissions.review', $submission), [
            'originality' => 2, 'methodology' => 2, 'clarity' => 2, 'compliance' => 2,
            'comments' => 'Needs more data.', 'recommendation' => 'minor_revision',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::REVISIONS_REQUIRED, $submission->status);
        // Only 1 of 3 reviewers has reviewed so far — no Review Summary yet.
        $this->assertNull($submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY));

        $this->actingAs($reviewers[1])->post(route('reviewer.submissions.review', $submission), [
            'originality' => 4, 'methodology' => 4, 'clarity' => 4, 'compliance' => 4,
            'comments' => 'Fine.', 'recommendation' => 'approve',
        ])->assertRedirect();

        $this->actingAs($reviewers[2])->post(route('reviewer.submissions.review', $submission), [
            'originality' => 4, 'methodology' => 4, 'clarity' => 4, 'compliance' => 4,
            'comments' => 'Fine.', 'recommendation' => 'approve',
        ])->assertRedirect();

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
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), [
                'originality' => 5, 'methodology' => 5, 'clarity' => 5, 'compliance' => 5,
                'comments' => 'Great.', 'recommendation' => 'approve',
            ])->assertRedirect();
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
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), [
                'originality' => 4, 'methodology' => 4, 'clarity' => 4, 'compliance' => 4,
                'comments' => 'Fine.', 'recommendation' => 'approve',
            ])->assertRedirect();
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
}
