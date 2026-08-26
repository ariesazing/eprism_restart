<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationalUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
    }

    public function test_admin_can_view_and_edit_an_organizational_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.organizational-units.index'))
            ->assertOk()
            ->assertSee($unit->name);

        $this->actingAs($admin)->patch(route('admin.organizational-units.update', $unit), [
            'name' => 'Renamed School',
            'is_active' => '0',
        ])->assertRedirect();

        $unit->refresh();
        $this->assertSame('Renamed School', $unit->name);
        $this->assertFalse($unit->is_active);
    }

    public function test_non_admin_cannot_manage_organizational_units(): void
    {
        $researcher = User::factory()->create();
        $unit = OrganizationalUnit::query()->first();

        $this->actingAs($researcher)->get(route('admin.organizational-units.index'))->assertForbidden();
        $this->actingAs($researcher)->patch(route('admin.organizational-units.update', $unit), [
            'name' => 'Hacked',
            'is_active' => '1',
        ])->assertForbidden();
    }

    public function test_inactive_units_are_not_offered_on_the_create_submission_page(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.organizational-units.update', $unit), [
            'name' => $unit->name,
            'is_active' => '0',
        ]);

        $response = $this->actingAs($researcher)->get(route('submissions.create'));

        $response->assertOk();
        $response->assertDontSee('value="'.$unit->name.'"', false);
    }

    public function test_an_existing_submissions_now_inactive_unit_still_appears_on_its_own_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $submission = $researcher->submissions()->create([
            'title' => 'Study Referencing a Unit',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'organizational_unit_type' => 'school',
            'school_id' => $unit->school_id,
        ]);

        $this->actingAs($admin)->patch(route('admin.organizational-units.update', $unit), [
            'name' => $unit->name,
            'is_active' => '0',
        ]);

        $response = $this->actingAs($researcher)->get(route('submissions.show', $submission));

        $response->assertOk();
        $response->assertSee('value="'.$unit->name.'"', false);
    }
}
