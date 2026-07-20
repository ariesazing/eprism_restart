<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_page_is_publicly_accessible(): void
    {
        $response = $this->get(route('repository.index'));

        $response->assertOk();
    }

    public function test_registered_users_start_pending_and_cannot_open_submission_module(): void
    {
        $this->post(route('register'), [
            'name' => 'Pending Researcher',
            'email' => 'pending@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'pending@example.com')->firstOrFail();

        $this->assertSame(ApprovalStatus::PENDING, $user->approval_status);

        $this->actingAs($user)
            ->get(route('submissions.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_assign_reviewer_and_approve_completed_workflow(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'course' => 'BSIT',
            'authors' => 'Researcher One',
            'abstract' => 'A workflow validation abstract.',
            'keywords' => 'ai, farming',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.submissions.assign-reviewer', $submission), [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertRedirect();

        $submission->refresh();

        $this->assertSame($reviewer->id, $submission->assigned_reviewer_id);
        $this->assertSame(SubmissionStatus::UNDER_REVIEW, $submission->status);

        $this->actingAs($reviewer)
            ->post(route('reviewer.submissions.review', $submission), [
                'originality' => 4,
                'methodology' => 5,
                'clarity' => 4,
                'compliance' => 5,
                'comments' => 'Ready for administrative evaluation.',
                'recommendation' => 'approve',
            ])
            ->assertRedirect();

        $review = Review::query()->where('research_submission_id', $submission->id)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.reviews.approve', $review), [
                'approval_notes' => 'Evaluation accepted.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.submissions.approve', $submission), [
                'admin_notes' => 'Approved for repository release.',
            ])
            ->assertRedirect();

        $submission->refresh();
        $review->refresh();

        $this->assertSame(SubmissionStatus::APPROVED, $submission->status);
        $this->assertNotNull($submission->approved_at);
        $this->assertSame($admin->id, $submission->approved_by);
        $this->assertNotNull($review->approved_at);
        $this->assertSame($admin->id, $review->approved_by);
    }
}