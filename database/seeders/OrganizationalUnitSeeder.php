<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationalUnitSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $units = [
            ['name' => 'Santiago National High School', 'organizational_unit_type' => 'school', 'sort_order' => 10],
            ['name' => 'DepEd Schools Division Office Santiago City', 'organizational_unit_type' => 'non_school', 'sort_order' => 20],
        ];

        foreach ($units as $unit) {
            OrganizationalUnit::updateOrCreate(['name' => $unit['name']], $unit);
        }

        OrganizationalUnit::query()
            ->whereNotIn('name', array_column($units, 'name'))
            ->delete();
    }
}
