<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnitPosition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationalUnitPositionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $positions = [
            ['organizational_unit_type' => 'school', 'label' => 'Teacher I', 'sort_order' => 10],
            ['organizational_unit_type' => 'school', 'label' => 'Teacher II', 'sort_order' => 20],
            ['organizational_unit_type' => 'school', 'label' => 'Teacher III', 'sort_order' => 30],
            ['organizational_unit_type' => 'school', 'label' => 'Master Teacher I', 'sort_order' => 40],
            ['organizational_unit_type' => 'school', 'label' => 'Master Teacher II', 'sort_order' => 50],
            ['organizational_unit_type' => 'school', 'label' => 'Master Teacher III', 'sort_order' => 60],
            ['organizational_unit_type' => 'school', 'label' => 'Master Teacher IV', 'sort_order' => 70],
            ['organizational_unit_type' => 'school', 'label' => 'Master Teacher V', 'sort_order' => 80],
            ['organizational_unit_type' => 'school', 'label' => 'Head Teacher I', 'sort_order' => 90],
            ['organizational_unit_type' => 'school', 'label' => 'Head Teacher II', 'sort_order' => 100],
            ['organizational_unit_type' => 'school', 'label' => 'Head Teacher III', 'sort_order' => 110],
            ['organizational_unit_type' => 'school', 'label' => 'Head Teacher IV', 'sort_order' => 120],
            ['organizational_unit_type' => 'school', 'label' => 'Head Teacher V', 'sort_order' => 130],
            ['organizational_unit_type' => 'school', 'label' => 'Head Teacher VI', 'sort_order' => 140],
            ['organizational_unit_type' => 'school', 'label' => 'Assistant School Principal I', 'sort_order' => 150],
            ['organizational_unit_type' => 'school', 'label' => 'Assistant School Principal II', 'sort_order' => 160],
            ['organizational_unit_type' => 'school', 'label' => 'Assistant School Principal III', 'sort_order' => 170],
            ['organizational_unit_type' => 'school', 'label' => 'School Principal I', 'sort_order' => 180],
            ['organizational_unit_type' => 'school', 'label' => 'School Principal II', 'sort_order' => 190],
            ['organizational_unit_type' => 'school', 'label' => 'School Principal III', 'sort_order' => 200],
            ['organizational_unit_type' => 'school', 'label' => 'School Principal IV', 'sort_order' => 210],
            ['organizational_unit_type' => 'non_school', 'label' => 'Project Development Officer I', 'sort_order' => 10],
            ['organizational_unit_type' => 'non_school', 'label' => 'Project Development Officer II', 'sort_order' => 20],
            ['organizational_unit_type' => 'non_school', 'label' => 'Senior Education Program Specialist (SEPS)', 'sort_order' => 30],
            ['organizational_unit_type' => 'non_school', 'label' => 'Education Program Supervisor (EPS)', 'sort_order' => 40],
            ['organizational_unit_type' => 'non_school', 'label' => 'Public Schools District Supervisor (PSDS)', 'sort_order' => 50],
        ];

        foreach ($positions as $position) {
            OrganizationalUnitPosition::updateOrCreate([
                'organizational_unit_type' => $position['organizational_unit_type'],
                'label' => $position['label'],
            ], $position);
        }
    }
}
