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
                'name' => 'TODA',
                'description' => 'Tricycle Operators and Drivers Association serving short-distance city routes.',
            ],
            [
                'name' => 'MODA',
                'description' => 'Motorcycle Operators and Drivers Association supporting organized motorcycle transport.',
            ],
            [
                'name' => 'Transport Cooperative',
                'description' => 'Registered transport cooperative coordinating fleet operations and member services.',
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
