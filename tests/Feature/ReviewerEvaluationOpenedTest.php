<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerEvaluationOpenedTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_evaluation_tracks_only_that_reviewer_and_preserves_first_visit(): void
    {
        $this->freezeTime();
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER]);
        $other = User::factory()->create(['role' => UserRole::REVIEWER]);
        $submission = ResearchSubmission::create([
            'researcher_id' => User::factory()->create()->id,
            'title' => 'Opening evaluation tracking', 'research_type' => 'basic',
            'classification' => 'proposal', 'organizational_unit' => 'Test school',
            'status' => SubmissionStatus::UNDER_REVIEW, 'submitted_at' => now()->subDay(),
        ]);
        $submission->reviewers()->attach([$reviewer->id, $other->id]);
        $url = route('reviewer.submissions.show', $submission);
        $this->actingAs($reviewer)->get($url)->assertOk();
        $opened = $submission->reviewers()->find($reviewer->id)->pivot->evaluation_opened_at;
        $this->assertNotNull($opened);
        $this->assertNull($submission->reviewers()->find($other->id)->pivot->evaluation_opened_at);
        $this->assertDatabaseCount('reviews', 0);
        $this->travel(5)->minutes();
        $this->get($url)->assertOk();
        $this->assertSame($opened, $submission->reviewers()->find($reviewer->id)->pivot->evaluation_opened_at);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.submissions.index'))->assertOk()->assertSee('First opened:')->assertSee('In progress');
        $this->get(route('admin.reviewers.history', $reviewer))->assertOk()->assertSee('In progress');
        $submission->update(['submitted_at' => now(), 'status' => SubmissionStatus::RESUBMITTED]);
        $this->assertNull($submission->evaluationOpenedAt($submission->reviewers()->find($reviewer->id)->pivot));
        $this->actingAs($reviewer)->get($url)->assertOk();
        $this->assertNotNull($submission->evaluationOpenedAt($submission->reviewers()->find($reviewer->id)->pivot));
    }

    public function test_unauthorized_and_draft_visits_do_not_record_activity(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER]);
        $submission = ResearchSubmission::create([
            'researcher_id' => User::factory()->create()->id,
            'title' => 'Private draft', 'research_type' => 'basic',
            'classification' => 'proposal', 'organizational_unit' => 'Test school',
            'status' => SubmissionStatus::DRAFT,
        ]);
        $url = route('reviewer.submissions.show', $submission);
        $this->actingAs($reviewer)->get($url)->assertForbidden();
        $submission->reviewers()->attach($reviewer);
        $this->get($url)->assertForbidden();
        $this->assertNull($submission->reviewers()->find($reviewer->id)->pivot->evaluation_opened_at);
    }
}
