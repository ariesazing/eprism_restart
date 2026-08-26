<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every save (including one that only touches an attachment or a chapter) resubmits the
 * whole header — title, organizational unit, and every proponent's position — because
 * they all live in one <form>. If the organizational unit or a position that was valid
 * when the submission was created later falls out of the live roster (a school renamed,
 * merged, or removed — see OrganizationalUnitSeeder), the header re-validation must not
 * reject the entire request and silently block unrelated edits.
 */
class SubmissionHeaderValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
    }

    private function makeOrphanedSubmission(User $researcher): \App\Models\ResearchSubmission
    {
        $submission = $researcher->submissions()->create([
            'title' => 'Pre-existing Draft',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
            // A school that no longer exists in the roster — simulates a submission
            // created before an admin renamed/removed it (e.g. the reseed that replaced
            // the old "Santiago National High School" placeholder).
            'organizational_unit' => 'A School That No Longer Exists',
            'organizational_unit_type' => 'school',
            'school_id' => 'OLD-001',
        ]);

        $submission->proponents()->create([
            'last_name' => 'Cruz', 'first_name' => 'Ana', 'position' => 'Teacher I',
            'is_lead' => true, 'sort_order' => 10,
        ]);

        return $submission->fresh();
    }

    public function test_saving_an_orphaned_submission_no_longer_fails_validation(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeOrphanedSubmission($researcher);
        $proponent = $submission->proponents()->firstOrFail();

        $response = $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => $submission->research_type,
            'classification' => $submission->classification,
            'organizational_unit' => $submission->organizational_unit,
            'school_id' => $submission->school_id,
            'proponents' => [
                [
                    'id' => $proponent->id,
                    'last_name' => $proponent->last_name,
                    'first_name' => $proponent->first_name,
                    'position' => $proponent->position,
                ],
            ],
            'sections' => [],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('A School That No Longer Exists', $submission->fresh()->organizational_unit);
        $this->assertSame('school', $submission->fresh()->organizational_unit_type);
    }

    public function test_attachments_and_chapters_actually_save_on_an_orphaned_submission(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeOrphanedSubmission($researcher);
        $proponent = $submission->proponents()->firstOrFail();

        $response = $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => $submission->research_type,
            'classification' => $submission->classification,
            'organizational_unit' => $submission->organizational_unit,
            'school_id' => $submission->school_id,
            'proponents' => [
                [
                    'id' => $proponent->id,
                    'last_name' => $proponent->last_name,
                    'first_name' => $proponent->first_name,
                    'position' => $proponent->position,
                ],
            ],
            'sections' => [
                'context_and_rationale' => ['content' => '{}', 'html' => '<p>New chapter content.</p>'],
            ],
            'attachments' => [
                'research_instrument' => [
                    UploadedFile::fake()->create('instrument.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $submission->documents()->count());
        $this->assertStringContainsString('New chapter content.', $submission->sections()->where('section_key', 'context_and_rationale')->first()->content_html);
    }

    public function test_actually_choosing_a_school_no_longer_in_the_roster_is_still_rejected(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeOrphanedSubmission($researcher);
        $proponent = $submission->proponents()->firstOrFail();

        // The researcher deliberately picks a *different*, still-invalid value — this
        // must still be rejected; the fallback only protects an unchanged, already-stored
        // value from a stale roster, not a fresh selection of garbage.
        $response = $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => $submission->research_type,
            'classification' => $submission->classification,
            'organizational_unit' => 'Some Made Up School',
            'school_id' => 'X',
            'proponents' => [
                [
                    'id' => $proponent->id,
                    'last_name' => $proponent->last_name,
                    'first_name' => $proponent->first_name,
                    'position' => $proponent->position,
                ],
            ],
            'sections' => [],
        ]);

        $response->assertSessionHasErrors('organizational_unit');
    }

    public function test_switching_to_a_real_current_school_still_requires_a_valid_position(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeOrphanedSubmission($researcher);
        $proponent = $submission->proponents()->firstOrFail();
        $newSchool = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $response = $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => $submission->research_type,
            'classification' => $submission->classification,
            'organizational_unit' => $newSchool->name,
            'school_id' => $newSchool->school_id,
            'proponents' => [
                [
                    'id' => $proponent->id,
                    'last_name' => $proponent->last_name,
                    'first_name' => $proponent->first_name,
                    'position' => 'Not A Real Position',
                ],
            ],
            'sections' => [],
        ]);

        $response->assertSessionHasErrors('proponents.0.position');
    }
}
