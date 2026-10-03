<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_download_includes_all_filtered_rows_and_escapes_formulas(): void
    {
        $researcher = User::factory()->create();
        for ($i = 0; $i < 8; $i++) {
            $researcher->submissions()->create([
                'title' => '=HYPERLINK("example") Study '.$i,
                'research_type' => 'basic', 'classification' => 'proposal',
                'status' => SubmissionStatus::APPROVED, 'approved_at' => now(),
            ]);
        }
        $researcher->submissions()->create([
            'title' => 'Excluded action study', 'research_type' => 'action',
            'classification' => 'proposal', 'status' => SubmissionStatus::APPROVED,
        ]);
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports', ['download' => 'csv', 'research_type' => 'basic', 'approved_page' => 2]));
        $response->assertOk()->assertDownload('research-reports-'.now()->format('Y-m-d').'.csv');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Schools Division of Santiago City-Research Unit', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        for ($i = 0; $i < 8; $i++) {
            $this->assertStringContainsString('Study '.$i, $content);
        }
        $this->assertStringNotContainsString('Excluded action study', $content);
    }

    public function test_report_download_requires_an_administrator(): void
    {
        $this->get(route('admin.reports', ['download' => 'csv']))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('admin.reports', ['download' => 'csv']))->assertForbidden();
    }

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
                ->assertViewHas('totalSubmissions', $status === SubmissionStatus::DRAFT ? 0 : 1);
        }
    }

    public function test_reports_exclude_drafts_except_tracking_and_group_all_evaluation_statuses(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        foreach (SubmissionStatus::cases() as $status) {
            $submission = $researcher->submissions()->create([
                'title' => $status->value, 'research_type' => 'basic', 'classification' => 'proposal',
                'status' => $status, 'organizational_unit' => $status === SubmissionStatus::DRAFT ? 'Draft school' : 'Submitted school',
                'submitted_at' => now(),
            ]);
            $submission->reviewers()->attach($reviewer);
            $submission->reviews()->create(['reviewer_id' => $reviewer->id, 'criteria_scores' => [], 'comments' => '', 'recommendation' => 'approve', 'submitted_at' => now()]);
        }
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports'))
            ->assertOk()
            ->assertViewHas('totalSubmissions', 6)
            ->assertViewHas('categorization', fn ($counts) => $counts->sum() === 6)
            ->assertViewHas('stages', ['drafts' => 1, 'on_evaluation' => 3, 'approved' => 1, 'on_revision' => 1, 'rejected' => 1])
            ->assertViewHas('submissionTrend', fn ($months) => $months->sum('count') === 6)
            ->assertViewHas('byOrganizationalUnit', fn ($units) => $units->sum('total') === 6)
            ->assertViewHas('recommendationCounts', fn ($counts) => $counts->sum() === 6)
            ->assertViewHas('reviewerLoads', fn ($users) => $users->first()->assigned_submissions_count === 6)
            ->assertDontSee('Draft school')
            ->assertSee('Drafts')->assertSee('On Evaluation')->assertSee('Approved');
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
