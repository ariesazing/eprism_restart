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
use App\Services\ReviewerWorkload;
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

    public function test_uneven_projected_load_requires_reason_and_hard_limit_cannot_be_overridden(): void
    {
        Notification::fake();
        $reviewers = User::factory()->count(3)->create(['role' => UserRole::REVIEWER, 'status' => AccountStatus::ACTIVE]);
        $busy = $reviewers->first();
        $this->createSubmission()->reviewers()->attach($busy);
        $second = $this->createSubmission();
        $url = route('admin.submissions.assign-reviewer', $second);
        $this->actingAs($this->admin)->patch($url, ['reviewer_ids' => [$busy->id]])->assertSessionHasErrors('load_override_reason');
        $this->assertCount(0, $second->reviewers);
        $this->patch($url, ['reviewer_ids' => [$busy->id], 'load_override_reason' => 'Specialist expertise needed'])->assertSessionHasNoErrors();
        $this->assertCount(1, $second->fresh()->reviewers);
        $this->assertDatabaseHas('activity_logs', ['action' => 'submission.workload_override']);

        $third = $this->createSubmission();
        $this->patch(route('admin.submissions.assign-reviewer', $third), ['reviewer_ids' => [$busy->id], 'load_override_reason' => 'Specialist expertise needed'])->assertSessionHasErrors('reviewer_ids');
        $this->assertCount(0, $third->fresh()->reviewers);
        $fourth = $this->createSubmission();
        $this->patch(route('admin.submissions.assign-reviewer', $fourth), ['reviewer_ids' => [$busy->id], 'load_override_reason' => 'Even admins cannot bypass this'])->assertSessionHasErrors('reviewer_ids');
        $this->assertCount(0, $fourth->fresh()->reviewers);
    }

    public function test_projection_does_not_double_count_retained_reviewers_and_accounts_for_removal(): void
    {
        $reviewers = User::factory()->count(3)->create(['role' => UserRole::REVIEWER, 'status' => AccountStatus::ACTIVE]);
        $submission = $this->createSubmission();
        $submission->reviewers()->attach($reviewers[0]);
        $workload = app(ReviewerWorkload::class);
        $retained = $workload->project($workload->reviewers(), $submission, [$reviewers[0]->id]);
        $replaced = $workload->project($workload->reviewers(), $submission, [$reviewers[1]->id]);
        $this->assertEqualsWithDelta(1 / 3, $retained['mean'], 0.001);
        $this->assertEqualsWithDelta($retained['deviation'], $replaced['deviation'], 0.001);
        $this->assertFalse($replaced['warning']);
    }

    public function test_monitoring_distinguishes_saved_and_overdue_evaluations(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER, 'status' => AccountStatus::ACTIVE]);
        $submission = $this->createSubmission(['status' => SubmissionStatus::UNDER_REVIEW]);
        $submission->reviewers()->attach($reviewer, ['deadline_at' => now()->subDay()]);
        Review::create(['research_submission_id' => $submission->id, 'reviewer_id' => $reviewer->id, 'criteria_scores' => [], 'comments' => '', 'recommendation' => 'approve']);
        $this->actingAs($this->admin)->get(route('admin.submissions.index'))->assertOk()
            ->assertSee('Overdue')->assertSee('0 / 1 complete')->assertSee('saved an evaluation')->assertSee('1 active');
    }

    public function test_simplified_load_thresholds_use_unrounded_projected_average(): void
    {
        $submission = $this->createSubmission();
        $workload = app(ReviewerWorkload::class);
        foreach ([
            [[1, 1, 0], true, false, false],
            [[2, 0], false, false, false], // Exactly one above average does not warn.
            [[2, 0, 0], false, true, false],
            [[3, 0, 0], false, false, true], // Exactly two above average blocks.
            [[4, 0, 0], false, false, true],
            [[0, 2, 2], false, false, false], // Spread alone no longer triggers a warning.
        ] as [$loads, $balanced, $warning, $blocked]) {
            $pool = collect($loads)->map(fn ($load, $id) => (object) ['id' => $id + 100, 'name' => 'Reviewer '.$id, 'assigned_submissions_count' => $load]);
            $result = $workload->project($pool, $submission, []);
            $this->assertSame($balanced, $result['balanced']);
            $this->assertSame($warning, $result['warning']);
            $this->assertSame($blocked, $result['blocked']);
        }
        $this->actingAs($this->admin)->get(route('admin.submissions.index'))->assertOk()
            ->assertDontSee('Projected average:')->assertDontSee('Variance:');
    }

    public function test_improving_an_overload_still_blocks_if_any_reviewer_remains_two_above_average(): void
    {
        Notification::fake();
        $reviewers = User::factory()->count(3)->create(['role' => UserRole::REVIEWER, 'status' => AccountStatus::ACTIVE]);
        for ($i = 0; $i < 6; $i++) {
            $submission = $this->createSubmission();
            $submission->reviewers()->attach($reviewers[0]);
        }
        $this->actingAs($this->admin)->patch(route('admin.submissions.assign-reviewer', $submission), [
            'reviewer_ids' => [$reviewers[1]->id], 'load_override_reason' => 'Moving one assignment to a less busy reviewer',
        ])->assertSessionHasErrors('reviewer_ids');
        $this->assertEquals([$reviewers[0]->id], $submission->reviewers()->pluck('users.id')->all());
    }
}
