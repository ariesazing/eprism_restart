<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\ResearchDocument;
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
        return $researcher->submissions()->create([
            'title' => 'Comment Workflow Research',
            'course' => 'BSIT',
            'authors' => 'Researcher',
            'abstract' => 'Abstract text.',
            'status' => SubmissionStatus::UNDER_REVIEW,
            'assigned_reviewer_id' => $reviewer->id,
        ]);
    }

    private function makeDocument(ResearchSubmission $submission, User $uploader): ResearchDocument
    {
        return $submission->documents()->create([
            'uploaded_by' => $uploader->id,
            'document_type' => 'manuscript',
            'original_name' => 'manuscript.pdf',
            'path' => 'research-documents/manuscript.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_reviewer_can_create_update_and_delete_own_comments_until_approved(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $admin = User::factory()->admin()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);
        $document = $this->makeDocument($submission, $researcher);

        $storeUrl = route('reviewer.submissions.documents.comments.store', [$submission, $document]);

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

        $updateUrl = route('reviewer.submissions.documents.comments.update', [$submission, $document, $commentId]);
        $this->actingAs($reviewer)->patchJson($updateUrl, ['body' => 'Updated comment body.'])
            ->assertOk()
            ->assertJsonPath('body', 'Updated comment body.');

        $this->actingAs($admin)->patch(route('admin.reviews.approve', $review), [])->assertRedirect();

        $this->actingAs($reviewer)->postJson($storeUrl, [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Should be blocked once approved.',
        ])->assertForbidden();

        $this->actingAs($reviewer)->patchJson($updateUrl, ['body' => 'Should fail.'])->assertForbidden();

        $destroyUrl = route('reviewer.submissions.documents.comments.destroy', [$submission, $document, $commentId]);
        $this->actingAs($reviewer)->deleteJson($destroyUrl)->assertForbidden();
    }

    public function test_admin_can_manage_comments_regardless_of_approval_state(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $admin = User::factory()->admin()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);
        $document = $this->makeDocument($submission, $researcher);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.documents.comments.store', [$submission, $document]), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $review = Review::query()->where('research_submission_id', $submission->id)->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.reviews.approve', $review), [])->assertRedirect();

        $adminStoreUrl = route('admin.submissions.documents.comments.store', [$submission, $document]);
        $response = $this->actingAs($admin)->postJson($adminStoreUrl, [
            'page_number' => 2,
            'anchor' => ['rects' => []],
            'body' => 'Admin annotation.',
        ]);
        $response->assertCreated();
        $adminCommentId = $response->json('id');

        $updateUrl = route('admin.submissions.documents.comments.update', [$submission, $document, $adminCommentId]);
        $this->actingAs($admin)->patchJson($updateUrl, ['body' => 'Admin edited.'])
            ->assertOk()
            ->assertJsonPath('body', 'Admin edited.');

        $destroyUrl = route('admin.submissions.documents.comments.destroy', [$submission, $document, $adminCommentId]);
        $this->actingAs($admin)->deleteJson($destroyUrl)->assertOk();
    }

    public function test_researcher_only_sees_comments_once_the_review_is_approved(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $admin = User::factory()->admin()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);
        $document = $this->makeDocument($submission, $researcher);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.documents.comments.store', [$submission, $document]), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $researcherIndexUrl = route('submissions.documents.comments.index', [$submission, $document]);

        $this->actingAs($researcher)->getJson($researcherIndexUrl)->assertOk()->assertJsonCount(0);

        $review = Review::query()->where('research_submission_id', $submission->id)->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.reviews.approve', $review), [])->assertRedirect();

        $this->actingAs($researcher)->getJson($researcherIndexUrl)->assertOk()->assertJsonCount(1);
    }

    public function test_admin_can_update_and_reopen_reviewer_evaluation(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $admin = User::factory()->admin()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), [
            'originality' => 3,
            'methodology' => 3,
            'clarity' => 3,
            'compliance' => 3,
            'comments' => 'Initial comments.',
            'recommendation' => 'minor_revision',
        ])->assertRedirect();

        $review = Review::query()->where('research_submission_id', $submission->id)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.reviews.update', $review), [
            'originality' => 5,
            'methodology' => 5,
            'clarity' => 5,
            'compliance' => 5,
            'comments' => 'Admin overwritten comments.',
            'recommendation' => 'approve',
        ])->assertRedirect();

        $review->refresh();
        $this->assertSame(5, $review->criteria_scores['originality']);
        $this->assertSame('Admin overwritten comments.', $review->comments);
        $this->assertSame('approve', $review->recommendation);

        $this->actingAs($admin)->patch(route('admin.reviews.approve', $review), [])->assertRedirect();
        $review->refresh();
        $this->assertNotNull($review->approved_at);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), [
            'originality' => 1,
            'methodology' => 1,
            'clarity' => 1,
            'compliance' => 1,
            'comments' => 'Should be blocked.',
            'recommendation' => 'reject',
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('admin.reviews.reopen', $review), [])->assertRedirect();
        $review->refresh();
        $this->assertNull($review->approved_at);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), [
            'originality' => 2,
            'methodology' => 2,
            'clarity' => 2,
            'compliance' => 2,
            'comments' => 'Now allowed again.',
            'recommendation' => 'minor_revision',
        ])->assertRedirect();

        $review->refresh();
        $this->assertSame('Now allowed again.', $review->comments);
    }
}
