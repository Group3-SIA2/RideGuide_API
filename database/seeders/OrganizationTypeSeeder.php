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
            'TODA',
            'MODA',
            'Transport Cooperative',
        ];

        foreach ($organizationTypes as $organizationTypeName) {
            $organizationType = OrganizationType::withTrashed()->firstOrNew([
                'name' => $organizationTypeName,
            ]);

            if (!$organizationType->exists) {
                $organizationType->save();
            } elseif ($organizationType->trashed()) {
                $organizationType->restore();
            }
        }
    }
}
