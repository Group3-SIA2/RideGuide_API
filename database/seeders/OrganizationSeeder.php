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
                'name'       => 'Bulaong Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Bulaong, General Santos City',
            ],
            [
                'name'       => 'Calumpang Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Calumpang, General Santos City',
            ],
            [
                'name'       => 'City Heights Terminal',
                'type'       => 'TODA',
                'hq_address' => 'City Heights, General Santos City',
            ],
            [
                'name'       => 'Dadiangas Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Dadiangas, General Santos City',
            ],
            [
                'name'       => 'Lagao Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Lagao, General Santos City',
            ],
            [
                'name'       => 'Labangal Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Labangal, General Santos City',
            ],
            [
                'name'       => 'Apopong Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Apopong, General Santos City',
            ],
            [
                'name'       => 'Bula Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Bula, General Santos City',
            ],
            [
                'name'       => 'San Isidro Terminal',
                'type'       => 'TODA',
                'hq_address' => 'San Isidro, General Santos City',
            ],
            [
                'name'       => 'Fatima Terminal',
                'type'       => 'TODA',
                'hq_address' => 'Fatima, General Santos City',
            ],
            [
                'name'       => 'Makar Wharf Terminal',
                'type'       => 'MODA',
                'hq_address' => 'Makar Wharf, General Santos City',
            ],
            [
                'name'       => 'Fishport Terminal',
                'type'       => 'MODA',
                'hq_address' => 'General Santos Fish Port Complex, General Santos City',
            ],
        ];

        foreach ($organizations as $org) {
            Organization::updateOrCreate(
                ['name' => $org['name']],
                [
                    'type'           => $org['type'],
                    'description'    => $descriptions[$org['type']],
                    'hq_address'     => $org['hq_address'],
                    'status'         => 'active',
                ]
            );
        }
    }
}
