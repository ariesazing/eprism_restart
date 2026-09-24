<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Mail\ReviewSummaryReadyMail;
use App\Mail\RoutingSlipReadyMail;
use App\Mail\SubmissionApprovedMail;
use App\Mail\SubmissionRevisionsRequiredMail;
use App\Models\RapmDocument;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Notifications\SubmissionDecisionNotification;
use App\Services\RapmDataBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RapmTest extends TestCase
{
    use RefreshDatabase;

    /**
     * $submission's own current research_type/classification decides which of the four rubrics
     * applies (see App\Evaluation\ResearchEvaluationRubric) — always re-read here, never
     * hardcoded, since a promoted proposal switches to its completed-research rubric mid-test in
     * some of the cases below, reusing the same $submission across the switch. The exact score
     * doesn't matter to any of this file's assertions — there's no passing cutoff to worry about
     * hitting or missing.
     */
    private function approvingReviewPayload(ResearchSubmission $submission, string $comments): array
    {
        $rubric = ResearchEvaluationRubric::for($submission->research_type, $submission->classification);

        return array_merge(
            collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all(),
            ['comments' => $comments, 'recommendation' => 'approve'],
        );
    }

    private function revisionReviewPayload(ResearchSubmission $submission, string $comments, string $recommendation = 'minor_revision'): array
    {
        $rubric = ResearchEvaluationRubric::for($submission->research_type, $submission->classification);

        return array_merge(
            collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all(),
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
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Looks good.'))->assertRedirect();

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

        $this->actingAs($reviewers[0])->post(route('reviewer.submissions.review', $submission), $this->revisionReviewPayload($submission, 'Needs more data.'))->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::REVISIONS_REQUIRED, $submission->status);
        // Only 1 of 3 reviewers has reviewed so far — no Review Summary yet.
        $this->assertNull($submission->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY));

        $this->actingAs($reviewers[1])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Fine.'))->assertRedirect();

        $this->actingAs($reviewers[2])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Fine.'))->assertRedirect();

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
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Great.'))->assertRedirect();
        }

        $submission->refresh();

        $this->assertSame(SubmissionStatus::APPROVED, $submission->status);
        $this->assertNotNull($submission->latestRapmDocument(RapmDocument::KIND_ROUTING_SLIP));

        Mail::assertQueued(SubmissionApprovedMail::class);
        Mail::assertQueued(RoutingSlipReadyMail::class);
    }

    /**
     * Regression test: a submission approved before its routing_slip document template
     * existed (or before this fix) is left fully approved with no RapmDocument row at
     * all — visiting the repository used to 404 on that submission's routing-slip link
     * forever, since generate() only ever fires once, at the moment of approval, with no
     * retry. RapmRoutingSlipService::ensureGenerated() (called from RepositoryController)
     * should backfill it the next time the repository is viewed.
     */
    public function test_repository_backfills_a_missing_routing_slip_for_an_approved_submission(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seedTemplates();

        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Retroactively Approved Research',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $this->assertNull($submission->latestRapmDocument(RapmDocument::KIND_ROUTING_SLIP));

        $this->actingAs($admin)->get(route('repository.index'))->assertOk();

        $submission->refresh();
        $document = $submission->latestRapmDocument(RapmDocument::KIND_ROUTING_SLIP);
        $this->assertNotNull($document);

        $this->actingAs($researcher)->get(route('rapm-documents.show', $document))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
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
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Fine.'))->assertRedirect();
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

        $this->actingAs($reviewers[0])->post(route('reviewer.submissions.review', $submission), $this->revisionReviewPayload($submission, 'Needs work.'))->assertRedirect();
        $this->actingAs($reviewers[1])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Fine.'))->assertRedirect();
        $this->actingAs($reviewers[2])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Fine.'))->assertRedirect();

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
        $this->actingAs($reviewers[0])->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Now fine.'))->assertRedirect();

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

    public function test_review_summary_names_reviewers_only_in_the_admin_copy(): void
    {
        Mail::fake();
        Notification::fake();
        $this->seedTemplates();

        $admin = User::factory()->admin()->create();
        $reviewers = User::factory()->reviewer()->count(2)->create();
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

        foreach ($reviewers as $reviewer) {
            $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload($submission, 'Fine.'))->assertRedirect();
        }

        $document = $submission->fresh()->latestRapmDocument(RapmDocument::KIND_REVIEW_SUMMARY);
        $this->assertNotNull($document->admin_path);
        $this->assertNotSame($document->path, $document->admin_path);

        $decrypt = fn (string $path) => Crypt::decrypt(Storage::disk('local')->get($path));

        // Each viewer is served their own rendering of the document.
        $this->assertSame($decrypt($document->path), $this->actingAs($researcher)->get(route('rapm-documents.show', $document))->getContent());
        $this->assertSame($decrypt($document->admin_path), $this->actingAs($admin)->get(route('rapm-documents.show', $document))->getContent());

    }

    public function test_review_summary_data_labels_reviewers_by_number_and_only_reveals_names_on_request(): void
    {
        $researcher = User::factory()->create();
        $reviewers = User::factory()->reviewer()->count(2)->create();

        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::UNDER_REVIEW,
        ]);
        $submission->reviewers()->attach($reviewers->pluck('id'));

        $rubric = ResearchEvaluationRubric::for('basic', 'proposal');
        $reviews = $reviewers->map(fn (User $reviewer) => $submission->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'rubric_key' => $rubric->key,
            'criteria_scores' => array_fill_keys($rubric->leafKeys(), 1),
            'recommendation' => 'approve',
            'comments' => 'ok',
            'submitted_at' => now(),
        ]))->keyBy('reviewer_id');

        $builder = app(RapmDataBuilder::class);

        $blind = $builder->buildReviewSummaryData($submission, $reviews);
        $named = $builder->buildReviewSummaryData($submission, $reviews, revealReviewers: true);

        $this->assertSame(['Reviewer 1', 'Reviewer 2'], array_column($blind['each']['reviewers'], 'reviewer_name'));
        $this->assertSame(
            ["Reviewer 1 ({$reviewers[0]->name})", "Reviewer 2 ({$reviewers[1]->name})"],
            array_column($named['each']['reviewers'], 'reviewer_name'),
        );

        $this->assertNotContains($reviewers[0]->name, array_column($blind['each']['criteria'], 'reviewer_name'));
        $this->assertContains("Reviewer 1 ({$reviewers[0]->name})", array_column($named['each']['criteria'], 'reviewer_name'));
    }
}
