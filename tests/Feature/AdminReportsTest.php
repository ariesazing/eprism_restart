<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_totals_count_each_submitted_phase_once_on_both_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Two phase study',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);
        $researcher->submissions()->create([
            'title' => 'Unsubmitted completed research',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $phases = [
            ['proposal', SubmissionStatus::DRAFT, null, 0],
            ['proposal', SubmissionStatus::SUBMITTED, null, 1],
            ['proposal', SubmissionStatus::RESUBMITTED, null, 1],
            ['completed', SubmissionStatus::DRAFT, now(), 1],
            ['completed', SubmissionStatus::SUBMITTED, now(), 2],
            ['completed', SubmissionStatus::REVISIONS_REQUIRED, now(), 2],
            ['completed', SubmissionStatus::RESUBMITTED, now(), 2],
            ['completed', SubmissionStatus::APPROVED, now(), 2],
        ];

        foreach ($phases as [$classification, $status, $proposalApprovedAt, $expected]) {
            $submission->update([
                'classification' => $classification,
                'status' => $status,
                'proposal_approved_at' => $proposalApprovedAt,
            ]);
            $this->actingAs($admin)->get(route('dashboard'))
                ->assertOk()
                ->assertViewHas('data', fn ($data) => $data['summaryTotal'] === $expected);
            $this->get(route('admin.reports'))
                ->assertOk()
                ->assertViewHas('totalSubmissions', $expected)
                ->assertSeeInOrder(['Total Submissions', (string) $expected, 'Approved']);
        }
    }

    public function test_reports_page_renders_categorization_organizational_unit_and_recommendation_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Stat Study',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::APPROVED,
            'organizational_unit' => 'Santiago City NHS',
            'approved_at' => now(),
        ]);
        $submission->reviewers()->attach($reviewer->id);
        $submission->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'criteria_scores' => ['originality' => 4, 'methodology' => 4, 'clarity' => 4, 'compliance' => 4],
            'comments' => 'Good.',
            'recommendation' => 'approve',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports'));

        $response->assertOk()
            ->assertSee('Categorization Metrics')
            ->assertSee('Research Tracking')
            ->assertSee('Research by Office / School Unit')
            ->assertSee('Santiago City NHS')
            ->assertSee('Reviewer Recommendations')
            ->assertSee('Submission Trend')
            ->assertSee('Avg. Time to Approval');
    }
}
