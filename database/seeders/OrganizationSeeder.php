<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationType;
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
            ['name' => 'Bulaong', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Bulaong', 'street' => 'National Highway']],
            ['name' => 'Calumpang', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Calumpang', 'street' => 'Calumpang Road']],
            ['name' => 'City Heights', 'organization_type' => 'TODA', 'address' => ['barangay' => 'City Heights', 'street' => 'Santiago Blvd']],
            ['name' => 'Dadiangas', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Dadiangas', 'street' => 'Pendatun Avenue']],
            ['name' => 'Lagao', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Lagao', 'street' => 'San Miguel Street']],
            ['name' => 'Labangal', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Labangal', 'street' => 'Diversion Road']],
            ['name' => 'Apopong', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Apopong', 'street' => 'Apopong Road']],
            ['name' => 'Bula', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Bula', 'street' => 'Bula Road']],
            ['name' => 'San Isidro', 'organization_type' => 'TODA', 'address' => ['barangay' => 'San Isidro', 'street' => 'San Isidro Street']],
            ['name' => 'Fatima', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Fatima', 'street' => 'Fatima Road']],
            ['name' => 'Makar Wharf', 'organization_type' => 'TODA', 'address' => ['barangay' => 'Makar Wharf', 'street' => 'Makar Port Road']],
            ['name' => 'Fishport', 'organization_type' => 'TODA', 'address' => ['barangay' => 'General Santos Fish Port Complex', 'street' => 'Fishport Road']],
        ];

        foreach ($organizations as $org) {
            $organizationType = OrganizationType::withTrashed()->firstOrNew([
                'name' => $org['organization_type'],
            ]);

            if (!$organizationType->exists) {
                $organizationType->save();
            } elseif ($organizationType->trashed()) {
                $organizationType->restore();
            }

            $address = HqAddress::query()->firstOrCreate(
                [
                    'barangay' => $org['address']['barangay'],
                    'street' => $org['address']['street'],
                ],
                [
                    'subdivision' => null,
                    'floor_unit_room' => null,
                    'lat' => null,
                    'lng' => null,
                ]
            );

            Organization::updateOrCreate(
                ['name' => $org['name']],
                [
                    'organization_type_id' => $organizationType->id,
                    'description' => $descriptions,
                    'hq_address'  => $address->id,
                    'status'      => 'active',
                ]
            );
        }
    }
}
