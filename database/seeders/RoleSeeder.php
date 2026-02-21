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
                'name'        => Role::ADMIN,
                'description' => 'System administrator with full access.',
            ],
            [
                'name'        => Role::DRIVER,
                'description' => 'Driver who provides ride services.',
            ],
            [
                'name'        => Role::COMMUTER,
                'description' => 'Commuter who books rides.',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']],
            );
        }
    }
}
