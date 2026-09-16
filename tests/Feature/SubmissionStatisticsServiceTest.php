<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\ActivityLog;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Services\SubmissionStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Backdates a submission's own created_at (its "entered draft" checkpoint) without
     * going through the real create-a-submission flow, so each fixture below can pin exact,
     * predictable day gaps between lifecycle events instead of relying on real elapsed time.
     */
    private function backdate(ResearchSubmission $submission, \DateTimeInterface $at): void
    {
        $submission->created_at = $at;
        $submission->save();
    }

    /**
     * Logs a status-transition action at an exact timestamp — the same action names
     * SubmissionDecisionService/ResearchSubmissionController/AdminSubmissionController log in
     * production, just backdated so timeInStatus()'s day-gap math is exactly verifiable.
     */
    private function logAt(ResearchSubmission $submission, string $action, \DateTimeInterface $at): void
    {
        $log = ActivityLog::create([
            'action' => $action,
            'subject_type' => ResearchSubmission::class,
            'subject_id' => $submission->id,
            'description' => $action,
        ]);
        $log->created_at = $at;
        $log->save();
    }

    public function test_time_in_status_only_counts_closed_intervals_and_ignores_noisy_reassignment_events(): void
    {
        $researcher = User::factory()->create();
        $day0 = now()->subDays(30)->startOfDay();

        // Submission A: goes through one full revision cycle before approval.
        $a = $researcher->submissions()->create([
            'title' => 'A',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::APPROVED,
            'submitted_at' => $day0->copy()->addDay(),
        ]);
        $this->backdate($a, $day0);
        $this->logAt($a, 'submission.submitted', $day0->copy()->addDays(1)); // draft: 1 day
        $this->logAt($a, 'submission.reviewers_assigned', $day0->copy()->addDays(2)); // submitted: 1 day
        $this->logAt($a, 'submission.revisions_required', $day0->copy()->addDays(4)); // under_review: 2 days
        $this->logAt($a, 'submission.resubmitted', $day0->copy()->addDays(5)); // revisions_required: 1 day
        $this->logAt($a, 'submission.reviewers_assigned', $day0->copy()->addDays(6)); // resubmitted: 1 day
        // A reassignment while already under review must not be read as a second
        // submitted->under_review transition — it should be silently ignored.
        $this->logAt($a, 'submission.reviewers_assigned', $day0->copy()->addDays(7));
        $this->logAt($a, 'submission.approved', $day0->copy()->addDays(9)); // under_review: 3 days

        // Submission B: approved on the first pass, no revisions.
        $b = $researcher->submissions()->create([
            'title' => 'B',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::APPROVED,
            'submitted_at' => $day0->copy()->addDays(2),
        ]);
        $this->backdate($b, $day0);
        $this->logAt($b, 'submission.submitted', $day0->copy()->addDays(2)); // draft: 2 days
        $this->logAt($b, 'submission.reviewers_assigned', $day0->copy()->addDays(3)); // submitted: 1 day
        $this->logAt($b, 'submission.approved', $day0->copy()->addDays(5)); // under_review: 2 days

        // Submission C: still sitting untouched in draft — must not pollute the "draft"
        // average with an open-ended, still-ongoing wait.
        $c = $researcher->submissions()->create([
            'title' => 'C',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);
        $this->backdate($c, $day0);

        $result = (new SubmissionStatisticsService)->timeInStatus();

        $this->assertSame(['avg' => 1.5, 'median' => 1.5, 'count' => 2], $result['draft']);
        $this->assertSame(['avg' => 1.0, 'median' => 1.0, 'count' => 2], $result['submitted']);
        $this->assertSame(['avg' => 2.3, 'median' => 2.0, 'count' => 3], $result['under_review']);
        $this->assertSame(['avg' => 1.0, 'median' => 1.0, 'count' => 1], $result['revisions_required']);
        $this->assertSame(['avg' => 1.0, 'median' => 1.0, 'count' => 1], $result['resubmitted']);
    }

    public function test_time_in_status_returns_null_averages_for_a_status_nothing_has_passed_through_yet(): void
    {
        $researcher = User::factory()->create();
        $researcher->submissions()->create([
            'title' => 'Untouched draft',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $result = (new SubmissionStatisticsService)->timeInStatus();

        $this->assertSame(['avg' => null, 'median' => null, 'count' => 0], $result['submitted']);
    }

    public function test_revision_cycle_stats_counts_cycles_per_submission_and_excludes_unsubmitted_drafts(): void
    {
        $researcher = User::factory()->create();

        // One revision cycle before approval.
        $a = $researcher->submissions()->create([
            'title' => 'A', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::APPROVED, 'submitted_at' => now(),
        ]);
        $this->logAt($a, 'submission.revisions_required', now());

        // Approved first pass, zero revision cycles — still belongs in the denominator.
        $researcher->submissions()->create([
            'title' => 'B', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::APPROVED, 'submitted_at' => now(),
        ]);

        // Never submitted — must not count toward the average at all.
        $researcher->submissions()->create([
            'title' => 'C', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $result = (new SubmissionStatisticsService)->revisionCycleStats();

        $this->assertSame(0.5, $result['avg']);
        $this->assertSame(['0' => 1, '1' => 1, '2' => 0, '3+' => 0], $result['distribution']);
    }
}
