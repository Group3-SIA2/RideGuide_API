<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OrganizationAddress;

class OrganizationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $descriptions = [
            'TODA' => 'Primary short-distance transport association in GenSan operating motorized tricycles.',
            'MODA' => 'Represents motorcycle-for-hire transport operators in GenSan.',
        ];

        $organizations = [
            ['name' => 'Bulaong Terminal', 'type' => 'TODA', 'barangay' => 'Bulaong'],
            ['name' => 'Calumpang Terminal', 'type' => 'TODA', 'barangay' => 'Calumpang'],
            ['name' => 'City Heights Terminal', 'type' => 'TODA', 'barangay' => 'City Heights'],
            ['name' => 'Dadiangas Terminal', 'type' => 'TODA', 'barangay' => 'Dadiangas'],
            ['name' => 'Lagao Terminal', 'type' => 'TODA', 'barangay' => 'Lagao'],
            ['name' => 'Labangal Terminal', 'type' => 'TODA', 'barangay' => 'Labangal'],
            ['name' => 'Apopong Terminal', 'type' => 'TODA', 'barangay' => 'Apopong'],
            ['name' => 'Bula Terminal', 'type' => 'TODA', 'barangay' => 'Bula'],
            ['name' => 'San Isidro Terminal', 'type' => 'TODA', 'barangay' => 'San Isidro'],
            ['name' => 'Fatima Terminal', 'type' => 'TODA', 'barangay' => 'Fatima'],
            ['name' => 'Makar Wharf Terminal', 'type' => 'MODA', 'barangay' => 'Makar Wharf'],
            ['name' => 'Fishport Terminal', 'type' => 'MODA', 'barangay' => 'General Santos Fish Port Complex'],
        ];

        foreach ($organizations as $org) {

            $address = OrganizationAddress::where('barangay', $org['barangay'])->first();

            Organization::updateOrCreate(
                ['name' => $org['name']],
                [
                    'type'        => $org['type'],
                    'description' => $descriptions[$org['type']],
                    'hq_address'  => $address?->id,
                    'status'      => 'active',
                ]
            );
        }
    }
}
