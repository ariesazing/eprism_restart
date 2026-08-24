<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationalUnitSchoolIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
    }

    public function test_the_full_santiago_city_school_roster_is_seeded_with_no_abbreviations(): void
    {
        $this->assertSame(41, OrganizationalUnit::query()->where('organizational_unit_type', 'school')->count());
        $this->assertSame(1, OrganizationalUnit::query()->where('organizational_unit_type', 'non_school')->count());

        $school = OrganizationalUnit::query()->where('school_id', '103811')->firstOrFail();
        $this->assertSame('Baptista Village Elementary School', $school->name);

        // No lingering "ES" abbreviation on any seeded school name.
        $this->assertSame(0, OrganizationalUnit::query()->where('organizational_unit_type', 'school')->where('name', 'like', '% ES')->count());

        $office = OrganizationalUnit::query()->where('organizational_unit_type', 'non_school')->firstOrFail();
        $this->assertNull($office->school_id);
    }

    public function test_create_form_has_no_editable_school_id_input(): void
    {
        $researcher = User::factory()->create();

        $response = $this->actingAs($researcher)->get(route('submissions.create'));

        $response->assertOk();
        $response->assertDontSee('<input type="text" name="school_id"', false);
        $response->assertSee('<input type="hidden" name="school_id"', false);

        $school = OrganizationalUnit::query()->where('school_id', '103822')->firstOrFail();
        $response->assertSee('data-school-id="103822"', false);
        $response->assertSee($school->name, false);
    }

    public function test_submitting_with_a_seeded_schools_own_id_is_accepted(): void
    {
        $researcher = User::factory()->create();
        $school = OrganizationalUnit::query()->where('school_id', '300599')->firstOrFail();

        $response = $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Reading Comprehension Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $school->name,
            'school_id' => $school->school_id,
            'proponents' => [
                [
                    'last_name' => 'Reyes',
                    'first_name' => 'Liza',
                    'email' => $researcher->email,
                    'contact_number' => '09171234567',
                    'position' => 'Teacher I',
                ],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $submission = $researcher->submissions()->firstOrFail();
        $this->assertSame('300599', $submission->school_id);
        $this->assertSame('school', $submission->organizational_unit_type);
    }
}
