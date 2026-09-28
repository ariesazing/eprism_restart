<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchConcern;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchConcernTest extends TestCase
{
    use RefreshDatabase;

    private User $researcher;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->researcher = User::factory()->create([
            'role' => UserRole::RESEARCHER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->admin = User::factory()->admin()->create([
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function createSubmission(User $owner, array $attributes = []): ResearchSubmission
    {
        return ResearchSubmission::create(array_merge([
            'researcher_id' => $owner->id,
            'title' => 'Sample Research Study',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
            'organizational_unit' => 'SDO Test',
        ], $attributes));
    }

    public function test_researcher_can_submit_concern_for_own_submission(): void
    {
        $submission = $this->createSubmission($this->researcher);

        $response = $this->actingAs($this->researcher)->post(route('submissions.concerns.store', $submission), [
            'category' => 'technical',
            'subject' => 'Issue saving attachment',
            'message' => 'Whenever I upload the instrument PDF, it takes too long to load.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('research_concerns', [
            'research_submission_id' => $submission->id,
            'user_id' => $this->researcher->id,
            'category' => 'technical',
            'subject' => 'Issue saving attachment',
            'status' => 'open',
        ]);
    }

    public function test_researcher_cannot_submit_concern_for_another_users_submission(): void
    {
        $otherUser = User::factory()->create([
            'role' => UserRole::RESEARCHER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $submission = $this->createSubmission($otherUser);

        $response = $this->actingAs($this->researcher)->post(route('submissions.concerns.store', $submission), [
            'category' => 'general',
            'subject' => 'Intruder subject',
            'message' => 'Should not work.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('research_concerns', [
            'research_submission_id' => $submission->id,
            'user_id' => $this->researcher->id,
        ]);
    }

    public function test_admin_can_view_and_respond_to_concern(): void
    {
        $submission = $this->createSubmission($this->researcher);
        $concern = ResearchConcern::create([
            'research_submission_id' => $submission->id,
            'user_id' => $this->researcher->id,
            'category' => 'deadline',
            'subject' => 'Deadline clarification',
            'message' => 'When is the deadline for revisions?',
            'status' => 'open',
        ]);

        // Admin can view concerns index
        $indexResponse = $this->actingAs($this->admin)->get(route('admin.concerns.index'));
        $indexResponse->assertOk()
            ->assertSee('Deadline clarification')
            ->assertSee($this->researcher->name);

        // Admin responds
        $updateResponse = $this->actingAs($this->admin)->patch(route('admin.concerns.update', $concern), [
            'status' => 'resolved',
            'admin_response' => 'Revisions are due within 14 calendar days from notification.',
        ]);

        $updateResponse->assertRedirect();
        $concern->refresh();
        $this->assertEquals('resolved', $concern->status);
        $this->assertEquals('Revisions are due within 14 calendar days from notification.', $concern->admin_response);
        $this->assertEquals($this->admin->id, $concern->responded_by);
        $this->assertNotNull($concern->responded_at);
    }
}
