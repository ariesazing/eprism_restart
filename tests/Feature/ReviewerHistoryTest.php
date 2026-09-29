<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewerHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function submission(User $owner, string $title): ResearchSubmission
    {
        return ResearchSubmission::create([
            'researcher_id' => $owner->id, 'title' => $title, 'research_type' => 'basic',
            'classification' => 'proposal', 'status' => SubmissionStatus::UNDER_REVIEW,
            'organizational_unit' => 'SDO Test',
        ]);
    }

    public function test_admin_can_see_current_assignments_and_evaluations_after_unassignment(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER]);
        $other = User::factory()->create(['role' => UserRole::REVIEWER]);
        $current = $this->submission($admin, 'Current assigned research');
        $current->reviewers()->attach($reviewer, ['deadline_at' => now()->subDay()]);
        $past = $this->submission($admin, 'Previously reviewed research');
        $past->reviewers()->attach($reviewer);
        Review::create(['research_submission_id' => $past->id, 'reviewer_id' => $reviewer->id, 'criteria_scores' => [], 'recommendation' => 'approve', 'comments' => 'Historical reviewer comments', 'submitted_at' => now()->subMonth()]);
        $past->reviewers()->detach($reviewer);
        Review::create(['research_submission_id' => $past->id, 'reviewer_id' => $other->id, 'criteria_scores' => [], 'recommendation' => 'approve', 'comments' => 'Another reviewer private draft']);

        $this->actingAs($admin)->get(route('admin.reviewers.history', $reviewer))->assertOk()
            ->assertSee('Current assigned research')->assertSee('Previously reviewed research')->assertSee('Overdue')
            ->assertSee('Historical reviewer comments')->assertDontSee('Another reviewer private draft')
            ->assertViewHas('activeCount', 1)->assertViewHas('completedCount', 1);
        $this->get(route('admin.users.index'))->assertOk()->assertSee(route('admin.reviewers.history', $reviewer), false);
        $this->get(route('admin.reports'))->assertOk()->assertSee(route('admin.reviewers.history', $reviewer), false);
        $this->get(route('admin.submissions.index'))->assertOk()->assertSee(route('admin.reviewers.history', $reviewer), false);
    }

    public function test_history_is_admin_only_and_has_an_empty_state(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER]);
        $researcher = User::factory()->create(['role' => UserRole::RESEARCHER]);
        $url = route('admin.reviewers.history', $reviewer);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($reviewer)->get($url)->assertForbidden();
        $this->actingAs($researcher)->get($url)->assertForbidden();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('No evaluations have been saved');
        $this->get(route('admin.reviewers.history', $researcher))->assertNotFound();
    }

    public function test_evaluations_are_paginated_and_include_earlier_manuscript_versions(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER]);
        $submission = $this->submission($admin, 'Versioned study');
        for ($i = 1; $i <= 16; $i++) {
            $version = $submission->manuscriptVersions()->create([
                'attempt' => (string) Str::uuid(), 'created_by' => $admin->id,
                'docx_path' => 'test.docx', 'docx_hash' => str_repeat('a', 64),
                'template_path' => 'template.docx', 'template_hash' => str_repeat('b', 64),
                'metadata' => [], 'attachments' => [],
            ]);
            $review = Review::create(['research_submission_id' => $submission->id, 'reviewer_id' => $reviewer->id, 'criteria_scores' => [], 'recommendation' => 'approve', 'comments' => 'Version evaluation '.$i, 'submitted_at' => now()]);
            $review->forceFill(['manuscript_version_id' => $version->id])->save();
        }
        $this->actingAs($admin)->get(route('admin.reviewers.history', $reviewer))->assertOk()
            ->assertViewHas('evaluations', fn ($rows) => $rows->total() === 16 && $rows->count() === 15);
        $this->get(route('admin.reviewers.history', [$reviewer, 'evaluations_page' => 2]))->assertOk()->assertSee('Version evaluation 1');
    }
}
