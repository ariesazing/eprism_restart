<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewerAssignedNotification;
use App\Notifications\ReviewerDeadlineReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReviewerLoadAndDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $researcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->researcher = User::factory()->create([
            'role' => UserRole::RESEARCHER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function createSubmission(array $attributes = []): ResearchSubmission
    {
        return ResearchSubmission::create(array_merge([
            'researcher_id' => $this->researcher->id,
            'title' => 'Sample Research Study',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
            'organizational_unit' => 'SDO Test',
        ], $attributes));
    }

    public function test_assign_reviewer_with_deadline_notifies_reviewer(): void
    {
        Notification::fake();

        $reviewer = User::factory()->create([
            'role' => UserRole::REVIEWER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $submission = $this->createSubmission();
        $deadline = now()->addDays(7);

        $response = $this->actingAs($this->admin)->patch(route('admin.submissions.assign-reviewer', $submission), [
            'reviewer_ids' => [$reviewer->id],
            'deadline_at' => $deadline->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $submission->refresh();

        $this->assertTrue($submission->reviewers->contains('id', $reviewer->id));
        $this->assertNotNull($submission->reviewers->first()->pivot->deadline_at);
        $this->assertEquals(SubmissionStatus::UNDER_REVIEW, $submission->status);

        Notification::assertSentTo($reviewer, ReviewerAssignedNotification::class, function ($notification) use ($submission) {
            return $notification->submission->id === $submission->id;
        });
    }

    public function test_unassign_reviewer_detaches_and_resets_status_when_no_reviewers_remain(): void
    {
        $reviewer = User::factory()->create([
            'role' => UserRole::REVIEWER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $submission = $this->createSubmission(['status' => SubmissionStatus::UNDER_REVIEW]);
        $submission->reviewers()->attach($reviewer->id);

        $response = $this->actingAs($this->admin)->delete(route('admin.submissions.unassign-reviewer', [$submission, $reviewer]));

        $response->assertRedirect();
        $submission->refresh();

        $this->assertFalse($submission->reviewers->contains('id', $reviewer->id));
        $this->assertEquals(SubmissionStatus::SUBMITTED, $submission->status);
    }

    public function test_deadline_reminder_command_notifies_reviewer_for_upcoming_deadline(): void
    {
        Notification::fake();

        $reviewer = User::factory()->create([
            'role' => UserRole::REVIEWER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $submission = $this->createSubmission(['status' => SubmissionStatus::UNDER_REVIEW]);

        // Set deadline in 18 hours (under 24h threshold)
        $submission->reviewers()->attach($reviewer->id, [
            'deadline_at' => now()->addHours(18),
        ]);

        $this->artisan('app:send-reviewer-deadline-reminders')
            ->assertSuccessful();

        Notification::assertSentTo($reviewer, ReviewerDeadlineReminderNotification::class, function ($notification) {
            return $notification->hoursLeft <= 24;
        });
    }

    public function test_admin_reports_displays_reviewer_workload_metrics(): void
    {
        $reviewer1 = User::factory()->create([
            'name' => 'Reviewer One',
            'role' => UserRole::REVIEWER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $reviewer2 = User::factory()->create([
            'name' => 'Reviewer Two',
            'role' => UserRole::REVIEWER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $submission = $this->createSubmission(['status' => SubmissionStatus::UNDER_REVIEW]);
        $submission->reviewers()->attach($reviewer1->id, ['deadline_at' => now()->addDays(3)]);
        $submission->reviewers()->attach($reviewer2->id, ['deadline_at' => now()->subDay()]); // overdue

        Review::create([
            'research_submission_id' => $submission->id,
            'reviewer_id' => $reviewer1->id,
            'recommendation' => 'approve',
            'criteria_scores' => [],
            'comments' => 'Looks good.',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertOk()
            ->assertSee('Reviewer Workload &amp; Evaluation Tracking', false)
            ->assertSee('Reviewer One')
            ->assertSee('Reviewer Two');
    }
}
