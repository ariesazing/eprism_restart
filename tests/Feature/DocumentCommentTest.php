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
