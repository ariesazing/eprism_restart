<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationalUnitSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The Schools Division of Santiago City's official school roster (DepEd school IDs),
     * covering elementary, secondary, and integrated schools. Kept as the single
     * authoritative list: run() below deletes anything not in it, so removing a school
     * here (a closure/merger) also removes it from the app, not just skips re-adding it.
     *
     * This makes the seeder meant for initial setup, not a live production database:
     * re-running it later will restore any name/ID an admin has since corrected via
     * the admin Organizational Units page back to what's hardcoded here (`is_active`
     * is the one field intentionally left out of the upsert below, so toggling a unit
     * off does survive a re-seed).
     */
    private const SCHOOLS = [
        ['school_id' => '103811', 'name' => 'Baptista Village Elementary School'],
        ['school_id' => '103812', 'name' => 'Batal Elementary School'],
        ['school_id' => '103813', 'name' => 'Divisoria Elementary School'],
        ['school_id' => '103814', 'name' => 'Luna Elementary School'],
        ['school_id' => '103815', 'name' => 'Mabini Elementary School'],
        ['school_id' => '103816', 'name' => 'Malini Elementary School'],
        ['school_id' => '103818', 'name' => 'Naggasican Elementary School'],
        ['school_id' => '103819', 'name' => 'Sagana Elementary School'],
        ['school_id' => '103821', 'name' => 'San Andres Elementary School'],
        ['school_id' => '103822', 'name' => 'Santiago East Central School'],
        ['school_id' => '103823', 'name' => 'Abra Elementary School'],
        ['school_id' => '103824', 'name' => 'Ambalatungan Elementary School'],
        ['school_id' => '103825', 'name' => 'Buenavista Elementary School'],
        ['school_id' => '103826', 'name' => 'Cabulay Elementary School'],
        ['school_id' => '103827', 'name' => 'Dubinan Elementary School'],
        ['school_id' => '103828', 'name' => 'Patul Elementary School'],
        ['school_id' => '103829', 'name' => 'San Isidro Elementary School'],
        ['school_id' => '103832', 'name' => 'Sinsayon Elementary School'],
        ['school_id' => '103833', 'name' => 'Villa Gonzaga Elementary School'],
        ['school_id' => '103835', 'name' => 'Baluarte Elementary School'],
        ['school_id' => '103837', 'name' => 'Calaocan Elementary School'],
        ['school_id' => '103838', 'name' => 'Rosario Elementary School'],
        ['school_id' => '103840', 'name' => 'Santiago South Central School'],
        ['school_id' => '103841', 'name' => 'Sta. Rosa Elementary School'],
        ['school_id' => '103842', 'name' => 'Santiago West Central School - Special Science Elementary School'],
        ['school_id' => '300505', 'name' => 'Cabulay High School'],
        ['school_id' => '300528', 'name' => 'Divisoria High School'],
        ['school_id' => '300578', 'name' => 'Rizal National High School'],
        ['school_id' => '300599', 'name' => 'Santiago City National High School'],
        ['school_id' => '306124', 'name' => 'Patul National High School'],
        ['school_id' => '325201', 'name' => 'Sinsayon National High School'],
        ['school_id' => '325202', 'name' => 'Sagana National High School'],
        ['school_id' => '325203', 'name' => 'Rosario National High School'],
        ['school_id' => '325204', 'name' => 'Naggasican National High School'],
        ['school_id' => '500936', 'name' => 'Balintocatoc Integrated School'],
        ['school_id' => '500937', 'name' => 'Salvador Integrated School'],
        ['school_id' => '500938', 'name' => 'Sinili Integrated School'],
        ['school_id' => '501147', 'name' => 'San Jose Integrated School'],
        ['school_id' => '502348', 'name' => 'Nabbuan Integrated School'],
        ['school_id' => '502550', 'name' => 'Bannawag Norte Integrated School'],
        ['school_id' => '502696', 'name' => 'Santiago North Central School - Integrated SpEd Center'],
    ];

    public function run(): void
    {
        $units = [];

        foreach (self::SCHOOLS as $index => $school) {
            $units[] = [
                'name' => $school['name'],
                'school_id' => $school['school_id'],
                'organizational_unit_type' => 'school',
                'sort_order' => ($index + 1) * 10,
            ];
        }

        $units[] = [
            'name' => 'DepEd Schools Division Office Santiago City',
            'school_id' => null,
            'organizational_unit_type' => 'non_school',
            'sort_order' => (count(self::SCHOOLS) + 1) * 10,
        ];

        foreach ($units as $unit) {
            OrganizationalUnit::updateOrCreate(['name' => $unit['name']], $unit);
        }

        OrganizationalUnit::query()
            ->whereNotIn('name', array_column($units, 'name'))
            ->delete();

        OrganizationalUnit::forgetCache();
    }
}
