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
        $organizations = [
            [
                'name'           => 'TODA - Bulaong Terminal',
                'type'           => 'TODA',
                'address'        => 'Bulaong, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - Calumpang Terminal',
                'type'           => 'TODA',
                'address'        => 'Calumpang, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - City Heights Terminal',
                'type'           => 'TODA',
                'address'        => 'City Heights, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - Dadiangas Terminal',
                'type'           => 'TODA',
                'address'        => 'Dadiangas, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - Lagao Terminal',
                'type'           => 'TODA',
                'address'        => 'Lagao, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - Labangal Terminal',
                'type'           => 'TODA',
                'address'        => 'Labangal, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - Apopong Terminal',
                'type'           => 'TODA',
                'address'        => 'Apopong, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - Bula Terminal',
                'type'           => 'TODA',
                'address'        => 'Bula, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - San Isidro Terminal',
                'type'           => 'TODA',
                'address'        => 'San Isidro, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'TODA - Fatima Terminal',
                'type'           => 'TODA',
                'address'        => 'Fatima, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'PODA - KCC Mall Terminal',
                'type'           => 'PODA',
                'address'        => 'KCC Mall, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'PODA - Gaisano Mall Terminal',
                'type'           => 'PODA',
                'address'        => 'Gaisano Mall, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'MODA - Makar Wharf Terminal',
                'type'           => 'MODA',
                'address'        => 'Makar Wharf, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
            [
                'name'           => 'MODA - Fishport Terminal',
                'type'           => 'MODA',
                'address'        => 'General Santos Fish Port Complex, General Santos City',
                'contact_number' => null,
                'status'         => 'active',
            ],
        ];

        foreach ($organizations as $org) {
            Organization::firstOrCreate(
                ['name' => $org['name']],
                $org
            );
        }
    }
}
