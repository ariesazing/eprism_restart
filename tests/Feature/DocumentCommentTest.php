<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentCommentTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmission(User $researcher, User $reviewer): ResearchSubmission
    {
        $submission = $researcher->submissions()->create([
            'title' => 'Comment Workflow Research',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::UNDER_REVIEW,
        ]);

        $submission->reviewers()->attach($reviewer->id);

        return $submission;
    }

    public function test_reviewer_can_create_update_and_delete_own_comments(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $storeUrl = route('reviewer.submissions.comments.store', $submission);

        $response = $this->actingAs($reviewer)->postJson($storeUrl, [
            'page_number' => 1,
            'quote_text' => 'Some quoted text',
            'anchor' => ['rects' => [['top' => 10, 'left' => 10, 'width' => 20, 'height' => 5]]],
            'body' => 'Please clarify this claim.',
        ]);

        $response->assertCreated();
        $commentId = $response->json('id');

        $review = Review::query()->where('research_submission_id', $submission->id)->firstOrFail();
        $this->assertSame($reviewer->id, $review->reviewer_id);

        $updateUrl = route('reviewer.submissions.comments.update', [$submission, $commentId]);
        $this->actingAs($reviewer)->patchJson($updateUrl, ['body' => 'Updated comment body.'])
            ->assertOk()
            ->assertJsonPath('body', 'Updated comment body.');

        $destroyUrl = route('reviewer.submissions.comments.destroy', [$submission, $commentId]);
        $this->actingAs($reviewer)->deleteJson($destroyUrl)->assertOk();
    }

    public function test_admin_cannot_create_or_manage_comments(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $admin = User::factory()->admin()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $adminStoreUrl = route('admin.submissions.comments.store', $submission);
        $this->actingAs($admin)->postJson($adminStoreUrl, [
            'page_number' => 2,
            'anchor' => ['rects' => []],
            'body' => 'Admin annotation.',
        ])->assertForbidden();

        // Admin can still read comments regardless of the reviewer's submission state.
        $this->actingAs($admin)->getJson(route('admin.submissions.comments.index', $submission))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_researcher_only_sees_comments_once_the_reviewer_submits_their_evaluation(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $researcherIndexUrl = route('submissions.comments.index', $submission);

        $this->actingAs($researcher)->getJson($researcherIndexUrl)->assertOk()->assertJsonCount(0);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), array_merge(
            collect(\App\Evaluation\ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'fair'])->all(),
            ['comments' => 'Initial pass.', 'recommendation' => 'minor_revision'],
        ))->assertRedirect();

        $this->assertNotNull($submission->reviews()->first()->submitted_at);

        $this->actingAs($researcher)->getJson($researcherIndexUrl)->assertOk()->assertJsonCount(1);
    }

    /**
     * Regression test: reviewManuscriptVersion() built the comments URL for an old
     * version via route('...comments.index', $submission, ['snapshot' => $id]) — the
     * third argument to route() is $absolute, not more parameters, so 'snapshot' was
     * silently discarded and every version page (researcher, reviewer, and admin all
     * shared this bug) actually fetched the *current* snapshot's comments instead of
     * the one being viewed. See ResearchSubmissionController::reviewManuscriptVersion(),
     * ReviewerSubmissionController::reviewManuscriptVersion(), and
     * AdminSubmissionController::reviewManuscriptVersion() — all now merge it into the
     * single $parameters array instead.
     */
    public function test_researcher_still_sees_an_older_versions_comments_when_viewing_that_version(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $review = $submission->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'criteria_scores' => [],
            'comments' => 'Round 1 review.',
            'recommendation' => 'minor_revision',
            'submitted_at' => now(),
        ]);

        $oldSnapshot = $submission->snapshots()->create([
            'version' => 1,
            'path' => 'snapshots/old.bin',
            'generated_by' => $researcher->id,
            'generated_at' => now()->subDay(),
        ]);

        $submission->snapshots()->create([
            'version' => 2,
            'path' => 'snapshots/new.bin',
            'generated_by' => $researcher->id,
            'generated_at' => now(),
        ]);

        $oldComment = $submission->comments()->create([
            'research_snapshot_id' => $oldSnapshot->id,
            'review_id' => $review->id,
            'author_id' => $reviewer->id,
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Comment made on version 1.',
        ]);

        // The old version's review page must point its comments fetch at that same
        // old snapshot, not silently fall through to the current one.
        $this->actingAs($researcher)
            ->get(route('submissions.manuscript.version.review', [$submission, $oldSnapshot]))
            ->assertOk()
            ->assertSee("snapshot={$oldSnapshot->id}", false);

        $this->actingAs($researcher)
            ->getJson(route('submissions.comments.index', [$submission, 'snapshot' => $oldSnapshot->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $oldComment->id);

        // The current snapshot has no comments of its own yet — confirms the old
        // version's fetch above isn't just coincidentally hitting the same data.
        $this->actingAs($researcher)
            ->getJson(route('submissions.comments.index', $submission))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_comments_do_not_alter_the_submission_content(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $submission->sections()->create([
            'section_key' => 'context_and_rationale',
            'label' => 'Chapter I. Context and Rationale',
            'type' => 'rich_text',
            'content' => '<p>Original content.</p>',
            'sort_order' => 0,
        ]);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $this->assertSame('<p>Original content.</p>', $submission->sections()->first()->content);
    }
}
