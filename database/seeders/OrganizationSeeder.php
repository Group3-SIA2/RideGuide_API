<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $descriptions = [
            'TODA' => 'Primary short-distance transport association in GenSan operating motorized tricycles. Coordinates route compliance and supports the PUV Modernization Program (PUVMP) across barangay terminals.',
            'MODA' => 'Represents motorcycle-for-hire (habal-habal) and motorized transport operators in GenSan. Works toward route regulation and compliance under local transport ordinances.',
        ];

        $organizations = [
            [
                'name'    => 'TODA - Bulaong Terminal',
                'type'    => 'TODA',
                'address' => 'Bulaong, General Santos City',
            ],
            [
                'name'    => 'TODA - Calumpang Terminal',
                'type'    => 'TODA',
                'address' => 'Calumpang, General Santos City',
            ],
            [
                'name'    => 'TODA - City Heights Terminal',
                'type'    => 'TODA',
                'address' => 'City Heights, General Santos City',
            ],
            [
                'name'    => 'TODA - Dadiangas Terminal',
                'type'    => 'TODA',
                'address' => 'Dadiangas, General Santos City',
            ],
            [
                'name'    => 'TODA - Lagao Terminal',
                'type'    => 'TODA',
                'address' => 'Lagao, General Santos City',
            ],
            [
                'name'    => 'TODA - Labangal Terminal',
                'type'    => 'TODA',
                'address' => 'Labangal, General Santos City',
            ],
            [
                'name'    => 'TODA - Apopong Terminal',
                'type'    => 'TODA',
                'address' => 'Apopong, General Santos City',
            ],
            [
                'name'    => 'TODA - Bula Terminal',
                'type'    => 'TODA',
                'address' => 'Bula, General Santos City',
            ],
            [
                'name'    => 'TODA - San Isidro Terminal',
                'type'    => 'TODA',
                'address' => 'San Isidro, General Santos City',
            ],
            [
                'name'    => 'TODA - Fatima Terminal',
                'type'    => 'TODA',
                'address' => 'Fatima, General Santos City',
            ],
            [
                'name'    => 'MODA - Makar Wharf Terminal',
                'type'    => 'MODA',
                'address' => 'Makar Wharf, General Santos City',
            ],
            [
                'name'    => 'MODA - Fishport Terminal',
                'type'    => 'MODA',
                'address' => 'General Santos Fish Port Complex, General Santos City',
            ],
        ];

        foreach ($organizations as $org) {
            Organization::updateOrCreate(
                ['name' => $org['name']],
                [
                    'type'           => $org['type'],
                    'description'    => $descriptions[$org['type']],
                    'address'        => $org['address'],
                    'contact_number' => null,
                    'status'         => 'active',
                ]
            );
        }
    }
}
