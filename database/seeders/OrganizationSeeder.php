<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HqAddress;

class OrganizationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $descriptions = "TODA Primary short-distance transport association in GenSan operating motorized tricycles.";

        $organizations = [
            ['name' => 'Bulaong', 'barangay' => 'Bulaong'],
            ['name' => 'Calumpang', 'barangay' => 'Calumpang'],
            ['name' => 'City Heights', 'barangay' => 'City Heights'],
            ['name' => 'Dadiangas', 'barangay' => 'Dadiangas'],
            ['name' => 'Lagao', 'barangay' => 'Lagao'],
            ['name' => 'Labangal', 'barangay' => 'Labangal'],
            ['name' => 'Apopong', 'barangay' => 'Apopong'],
            ['name' => 'Bula', 'barangay' => 'Bula'],
            ['name' => 'San Isidro', 'barangay' => 'San Isidro'],
            ['name' => 'Fatima', 'barangay' => 'Fatima'],
            ['name' => 'Makar Wharf', 'barangay' => 'Makar Wharf'],
            ['name' => 'Fishport', 'barangay' => 'General Santos Fish Port Complex'],
        ];

        foreach ($organizations as $org) {

            $address = HqAddress::where('barangay', $org['barangay'])->first();

            Organization::updateOrCreate(
                ['name' => $org['name']],
                [
                    'description' => $descriptions,
                    'hq_address'  => $address?->id,
                    'status'      => 'active',
                ]
            );
        }
    }
}
