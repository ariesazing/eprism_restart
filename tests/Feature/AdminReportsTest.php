<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('Research by Organizational Unit')
            ->assertSee('Santiago City NHS')
            ->assertSee('Reviewer Recommendations')
            ->assertSee('Average Time to Approval');
    }
}
