<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizationTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $organizationTypes = [
            [
                'name' => 'Transport Cooperative',
                'description' => 'Registered transport cooperatives in General Santos City (e.g., MGTC) operating structured and modernized public transport services.',
            ],
            [
                'name' => 'Transport Alliance/Association',
                'description' => 'Transport alliances and associations in General Santos City (e.g., PTAG) consolidating route-based operators and dispatch coordination.',
            ],
            [
                'name' => 'TODA',
                'description' => 'Barangay-level Tricycle Operators and Drivers Associations aligned with LGU and LPTRP regulations in General Santos City.',
            ],
            [
                'name' => 'PUVMP Group',
                'description' => 'Organizations and route clusters participating in the PUV Modernization Program, deploying higher-capacity and lower-emission units.',
            ],
        ];

        foreach ($organizationTypes as $organizationTypeData) {
            $organizationType = OrganizationType::withTrashed()->firstOrNew([
                'name' => $organizationTypeData['name'],
            ]);

            if (!$organizationType->exists) {
                $organizationType->save();
            } elseif ($organizationType->trashed()) {
                $organizationType->restore();
            }

            $organizationType->description = $organizationTypeData['description'];
            $organizationType->save();
        }
    }
}
