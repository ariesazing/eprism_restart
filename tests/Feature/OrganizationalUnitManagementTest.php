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

        $this->actingAs($admin)->patch(route('admin.organizational-units.batch-update'), [
            'units' => [
                $unit->id => [
                    'name' => 'Renamed School',
                    'is_active' => '0',
                ],
            ],
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
        $this->actingAs($researcher)->patch(route('admin.organizational-units.batch-update'), [
            'units' => [
                $unit->id => [
                    'name' => 'Hacked',
                    'is_active' => '1',
                ],
            ],
        ])->assertForbidden();
    }

    public function test_admin_can_add_a_new_organizational_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $maxSortOrder = OrganizationalUnit::max('sort_order');

        $this->actingAs($admin)->post(route('admin.organizational-units.store'), [
            'name' => 'Brand New Elementary School',
            'school_id' => 'SCH-999',
            'organizational_unit_type' => 'school',
            'is_active' => '1',
        ])->assertRedirect();

        $unit = OrganizationalUnit::query()->where('name', 'Brand New Elementary School')->firstOrFail();
        $this->assertSame('SCH-999', $unit->school_id);
        $this->assertSame('school', $unit->organizational_unit_type);
        $this->assertTrue($unit->is_active);
        $this->assertGreaterThan($maxSortOrder, $unit->sort_order);

        // Immediately usable — the cached ordered()/typeMap() lists must not be stale.
        $this->actingAs($researcher)->get(route('submissions.create'))
            ->assertOk()
            ->assertSee('value="Brand New Elementary School"', false);
    }

    public function test_a_new_organizational_unit_cannot_reuse_an_existing_name(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::query()->first();

        $this->actingAs($admin)->post(route('admin.organizational-units.store'), [
            'name' => $unit->name,
            'organizational_unit_type' => 'school',
        ])->assertSessionHasErrors('name');
    }

    public function test_non_admin_cannot_add_an_organizational_unit(): void
    {
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('admin.organizational-units.store'), [
            'name' => 'Sneaky New Unit',
            'organizational_unit_type' => 'school',
        ])->assertForbidden();
    }

    public function test_inactive_units_are_not_offered_on_the_create_submission_page(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.organizational-units.batch-update'), [
            'units' => [
                $unit->id => [
                    'name' => $unit->name,
                    'is_active' => '0',
                ],
            ],
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

        $this->actingAs($admin)->patch(route('admin.organizational-units.batch-update'), [
            'units' => [
                $unit->id => [
                    'name' => $unit->name,
                    'is_active' => '0',
                ],
            ],
        ]);

        $response = $this->actingAs($researcher)->get(route('submissions.show', $submission));

        $response->assertOk();
        $response->assertSee('value="'.$unit->name.'"', false);
    }
}
