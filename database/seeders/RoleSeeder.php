<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the roles table.
     */
    public function run(): void
    {
        $roles = [
            [
                'name'        => Role::SUPER_ADMIN,
                'description' => 'Super administrator with unrestricted access.',
                'is_reserved' => true,
            ],
            [
                'name'        => Role::ADMIN,
                'description' => 'System administrator with full access.',
                'is_reserved' => true,
            ],
            [
                'name'        => Role::DRIVER,
                'description' => 'Driver who provides ride services.',
                'is_reserved' => true,
            ],
            [
                'name'        => Role::COMMUTER,
                'description' => 'Commuter who books rides.',
                'is_reserved' => true,
            ],
            [
                'name'        => Role::ORGANIZATION,
                'description' => 'Transport organization manager (e.g. TODA, MODA).',
                'is_reserved' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                [
                    'description' => $role['description'],
                    'is_reserved' => $role['is_reserved'],
                ],
            );
        }
    }
}
