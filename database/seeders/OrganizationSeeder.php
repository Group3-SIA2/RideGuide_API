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
        $organizationTypeDescriptions = [
            'Transport Cooperative' => 'Registered transport cooperatives in General Santos City operating structured and modernized public transport services.',
            'Transport Alliance/Association' => 'Transport alliances and associations in General Santos City consolidating route-based operators and dispatch coordination.',
            'TODA' => 'Barangay-level Tricycle Operators and Drivers Associations aligned with LGU and LPTRP regulations in General Santos City.',
            'PUVMP Group' => 'Organizations and route clusters participating in the PUV Modernization Program, deploying higher-capacity and lower-emission units.',
        ];

        $organizations = [
            [
                'name' => 'Metro GenSan Transport Cooperative (MGTC) - Lagao Cluster',
                'organization_type' => 'Transport Cooperative',
                'address' => ['barangay' => 'Lagao', 'street' => 'Santiago Boulevard'],
            ],
            [
                'name' => 'Metro GenSan Transport Cooperative (MGTC) - Calumpang Cluster',
                'organization_type' => 'Transport Cooperative',
                'address' => ['barangay' => 'Calumpang', 'street' => 'Jose Catolico Sr. Avenue'],
            ],
            [
                'name' => 'Public Transport Alliance of GenSan (PTAG) - Pioneer Corridor',
                'organization_type' => 'Transport Alliance/Association',
                'address' => ['barangay' => 'Dadiangas South', 'street' => 'Pioneer Avenue'],
            ],
            [
                'name' => 'Public Transport Alliance of GenSan (PTAG) - Roxas East Corridor',
                'organization_type' => 'Transport Alliance/Association',
                'address' => ['barangay' => 'Dadiangas East', 'street' => 'Roxas East Avenue'],
            ],
            [
                'name' => 'Lagao TODA',
                'organization_type' => 'TODA',
                'address' => ['barangay' => 'Lagao', 'street' => 'San Miguel Street'],
            ],
            [
                'name' => 'City Heights TODA',
                'organization_type' => 'TODA',
                'address' => ['barangay' => 'City Heights', 'street' => 'Santiago Boulevard'],
            ],
            [
                'name' => 'Conel PUVMP E-Jeepney Group',
                'organization_type' => 'PUVMP Group',
                'address' => ['barangay' => 'Conel', 'street' => 'National Highway'],
            ],
            [
                'name' => 'Tambler PUVMP E-Jeepney Group',
                'organization_type' => 'PUVMP Group',
                'address' => ['barangay' => 'Tambler', 'street' => 'Fishport Road'],
            ],
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

            $organizationType->description = $organizationTypeDescriptions[$org['organization_type']] ?? null;
            $organizationType->save();

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
                    'hq_address'  => $address->id,
                    'status'      => 'active',
                ]
            );
        }
    }
}
