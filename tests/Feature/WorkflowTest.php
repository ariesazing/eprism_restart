<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
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

    public function test_researcher_can_save_a_draft_with_multiple_proponents_from_seeded_lookups(): void
    {
        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);

        $schoolPosition = OrganizationalUnitPosition::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $nonSchoolPosition = OrganizationalUnitPosition::query()->where('organizational_unit_type', 'non_school')->firstOrFail();
        $schoolUnit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $nonSchoolUnit = OrganizationalUnit::query()->where('organizational_unit_type', 'non_school')->firstOrFail();

        $researcher = User::factory()->create();

        $response = $this->actingAs($researcher)->post(route('submissions.store'), [
            'action' => 'draft',
            'title' => 'Community-Based Learning Interventions',
            'research_type' => 'action',
            'classification' => 'proposal',
            'proponents' => [
                [
                    'last_name' => 'Delacruz',
                    'first_name' => 'Ana',
                    'email' => $researcher->email,
                    'contact_number' => '09171234567',
                    'organizational_unit' => $schoolUnit->name,
                    'position' => $schoolPosition->label,
                    'school_id' => 'SCH-001',
                ],
                [
                    'last_name' => 'Santos',
                    'first_name' => 'Ben',
                    'email' => 'ben.santos@example.com',
                    'contact_number' => '09179876543',
                    'organizational_unit' => $nonSchoolUnit->name,
                    'position' => $nonSchoolPosition->label,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $submission = $researcher->submissions()->firstOrFail();

        $this->assertSame(2, $submission->proponents()->count());

        $lead = $submission->proponents()->where('is_lead', true)->firstOrFail();
        $this->assertSame('Delacruz', $lead->last_name);
        $this->assertSame('school', $lead->organizational_unit_type);
        $this->assertSame('SCH-001', $lead->school_id);

        $coProponent = $submission->proponents()->where('is_lead', false)->firstOrFail();
        $this->assertSame('Santos', $coProponent->last_name);
        $this->assertSame('non_school', $coProponent->organizational_unit_type);
        $this->assertNull($coProponent->school_id);
    }
}