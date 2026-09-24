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

    public function test_active_ordered_lists_units_alphabetically_by_name(): void
    {
        OrganizationalUnit::query()->delete();
        OrganizationalUnit::forgetCache();

        OrganizationalUnit::create(['name' => 'Zamora Elementary School', 'organizational_unit_type' => 'school', 'is_active' => true, 'sort_order' => 1]);
        OrganizationalUnit::create(['name' => 'Abella Elementary School', 'organizational_unit_type' => 'school', 'is_active' => true, 'sort_order' => 2]);
        OrganizationalUnit::create(['name' => 'mabini High School', 'organizational_unit_type' => 'school', 'is_active' => true, 'sort_order' => 3]);
        OrganizationalUnit::create(['name' => 'Balite Elementary School', 'organizational_unit_type' => 'school', 'is_active' => false, 'sort_order' => 4]);

        $names = OrganizationalUnit::activeOrdered()->pluck('name')->all();

        $this->assertSame(
            ['Abella Elementary School', 'mabini High School', 'Zamora Elementary School'],
            $names,
            'activeOrdered() should sort alphabetically (case-insensitively) rather than by insertion order, and inactive units should still be excluded.'
        );
    }

    public function test_admin_can_view_and_edit_an_organizational_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.organizational-units.index', ['search' => $unit->name]))
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

    public function test_an_admin_can_soft_delete_a_unit_and_it_leaves_every_dropdown(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $this->assertTrue(OrganizationalUnit::activeOrdered()->contains('id', $unit->id));

        $this->actingAs($admin)->delete(route('admin.organizational-units.destroy', $unit))->assertRedirect();

        $this->assertSoftDeleted('organizational_units', ['id' => $unit->id]);
        $this->assertFalse(OrganizationalUnit::activeOrdered()->contains('id', $unit->id));
        $this->assertArrayNotHasKey($unit->name, OrganizationalUnit::typeMap());

        $this->actingAs($admin)->get(route('admin.organizational-units.index', ['search' => $unit->name]))
            ->assertOk()
            ->assertDontSee("units[{$unit->id}][name]", false);

        $this->actingAs($admin)->get(route('admin.organizational-units.index', ['status' => 'deleted']))
            ->assertOk()
            ->assertSee($unit->name);
    }

    public function test_a_soft_deleted_unit_can_be_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $unit->delete();
        OrganizationalUnit::forgetCache();

        $this->actingAs($admin)->post(route('admin.organizational-units.restore', $unit->id))->assertRedirect();

        $this->assertNotSoftDeleted('organizational_units', ['id' => $unit->id]);
        $this->assertTrue(OrganizationalUnit::activeOrdered()->contains('id', $unit->id));
    }

    public function test_a_unit_cannot_be_restored_once_a_live_unit_has_taken_its_name(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $name = $unit->name;
        $unit->forceFill(['school_id' => null])->save();
        $unit->delete();

        $this->actingAs($admin)->post(route('admin.organizational-units.store'), [
            'name' => $name,
            'organizational_unit_type' => 'school',
        ])->assertSessionHasNoErrors();

        // Adding it back under the same name replaced the trashed copy outright, so there is
        // nothing left to restore (and, crucially, no unique-index collision on the way).
        $this->assertSame(1, OrganizationalUnit::withTrashed()->where('name', $name)->count());
        $this->actingAs($admin)->post(route('admin.organizational-units.restore', $unit->id))->assertNotFound();
    }

    public function test_a_deleted_unit_can_be_re_added_without_a_name_or_school_id_collision(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = OrganizationalUnit::query()->whereNotNull('school_id')->firstOrFail();
        [$name, $schoolId] = [$unit->name, $unit->school_id];
        $unit->delete();

        $this->actingAs($admin)->post(route('admin.organizational-units.store'), [
            'name' => $name,
            'school_id' => $schoolId,
            'organizational_unit_type' => 'school',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, OrganizationalUnit::where('school_id', $schoolId)->count());
    }

    public function test_reseeding_keeps_an_admin_deleted_unit_deleted(): void
    {
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $unit->delete();

        $this->seed(OrganizationalUnitSeeder::class);

        $this->assertSoftDeleted('organizational_units', ['id' => $unit->id]);
        $this->assertSame(1, OrganizationalUnit::withTrashed()->where('name', $unit->name)->count());
    }

    public function test_a_researcher_cannot_delete_or_restore_units(): void
    {
        $unit = OrganizationalUnit::query()->firstOrFail();

        $this->actingAs(User::factory()->create())->delete(route('admin.organizational-units.destroy', $unit))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.organizational-units.restore', $unit->id))->assertForbidden();
    }
}
