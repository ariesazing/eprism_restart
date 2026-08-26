<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\SubmissionWindow;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
    }

    private function submissionPayload(): array
    {
        $schoolPosition = OrganizationalUnitPosition::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $schoolUnit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        return [
            'title' => 'A Timeline-Gated Study',
            'research_type' => 'action',
            'classification' => 'proposal',
            'organizational_unit' => $schoolUnit->name,
            'school_id' => 'SCH-001',
            'proponents' => [
                [
                    'last_name' => 'Delacruz',
                    'first_name' => 'Ana',
                    'email' => 'ana@example.com',
                    'contact_number' => '09171234567',
                    'position' => $schoolPosition->label,
                ],
            ],
        ];
    }

    private function setOpenClassification(User $admin, string $openClassification, array $windows = []): void
    {
        $this->actingAs($admin)->patch(route('admin.submission-timeline.update'), [
            'open_classification' => $openClassification,
            'windows' => [
                'proposal' => $windows['proposal'] ?? ['opens_at' => null, 'closes_at' => null],
                'completed' => $windows['completed'] ?? ['opens_at' => null, 'closes_at' => null],
            ],
        ]);
    }

    public function test_admin_can_view_and_update_the_submission_timeline(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.submission-timeline.index'))
            ->assertOk()
            ->assertSee('Proposal Research')
            ->assertSee('Completed Research');

        $this->setOpenClassification($admin, 'completed');

        $proposal = SubmissionWindow::forClassification('proposal');
        $completed = SubmissionWindow::forClassification('completed');
        $this->assertFalse($proposal->is_open);
        $this->assertTrue($completed->is_open);
        $this->assertSame($admin->id, $proposal->updated_by);
    }

    public function test_opening_one_classification_automatically_closes_the_other(): void
    {
        $admin = User::factory()->admin()->create();

        $this->setOpenClassification($admin, 'proposal');
        $this->assertTrue(SubmissionWindow::forClassification('proposal')->is_open);
        $this->assertFalse(SubmissionWindow::forClassification('completed')->is_open);

        $this->setOpenClassification($admin, 'completed');
        $this->assertFalse(SubmissionWindow::forClassification('proposal')->is_open);
        $this->assertTrue(SubmissionWindow::forClassification('completed')->is_open);

        $this->setOpenClassification($admin, 'none');
        $this->assertFalse(SubmissionWindow::forClassification('proposal')->is_open);
        $this->assertFalse(SubmissionWindow::forClassification('completed')->is_open);
    }

    public function test_non_admin_cannot_manage_the_submission_timeline(): void
    {
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->get(route('admin.submission-timeline.index'))->assertForbidden();
        $this->actingAs($researcher)->patch(route('admin.submission-timeline.update'), [
            'open_classification' => 'proposal',
            'windows' => ['proposal' => [], 'completed' => []],
        ])->assertForbidden();
    }

    public function test_researcher_cannot_create_a_submission_while_the_proposal_window_is_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();

        $this->setOpenClassification($admin, 'completed');

        $response = $this->actingAs($researcher)->post(route('submissions.store'), $this->submissionPayload());

        $response->assertSessionHasErrors('classification');
        $this->assertSame(0, $researcher->submissions()->count());
    }

    public function test_researcher_can_create_a_submission_while_the_proposal_window_is_open(): void
    {
        $researcher = User::factory()->create();

        $response = $this->actingAs($researcher)->post(route('submissions.store'), $this->submissionPayload());

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $researcher->submissions()->count());
    }

    public function test_researcher_cannot_submit_a_draft_once_its_window_closes_after_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();

        $create = $this->actingAs($researcher)->post(route('submissions.store'), $this->submissionPayload());
        $create->assertSessionDoesntHaveErrors();
        $submission = $researcher->submissions()->firstOrFail();

        $this->setOpenClassification($admin, 'completed');

        $this->actingAs($researcher)->post(route('submissions.submit', $submission))
            ->assertSessionHasErrors('submission');

        $this->assertSame(SubmissionStatus::DRAFT, $submission->fresh()->status);
    }

    public function test_a_closed_date_in_the_past_closes_the_window_even_though_it_is_the_open_classification(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();

        $this->setOpenClassification($admin, 'proposal', [
            'proposal' => ['opens_at' => now()->subDays(10)->format('Y-m-d'), 'closes_at' => now()->subDay()->format('Y-m-d')],
        ]);

        $this->assertFalse(SubmissionWindow::isOpenFor('proposal'));

        $this->actingAs($researcher)->post(route('submissions.store'), $this->submissionPayload())
            ->assertSessionHasErrors('classification');
    }

    public function test_an_opens_date_in_the_future_keeps_the_window_closed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->setOpenClassification($admin, 'proposal', [
            'proposal' => ['opens_at' => now()->addDays(3)->format('Y-m-d'), 'closes_at' => null],
        ]);

        $this->assertFalse(SubmissionWindow::isOpenFor('proposal'));
    }
}
