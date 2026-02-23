<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles first (Admin, Driver, Commuter)
        $this->call(RoleSeeder::class);

        // Create a test admin user
        $adminRole = Role::where('name', Role::ADMIN)->first();

        User::factory()->create([
            'first_name'    => 'Admin',
            'last_name'     => 'User',
            'middle_name'   => 'A',
            'email'         => 'admin@rideguide.com',
            'role_id'       => $adminRole->id,
        ]);

          if (! $adminRole) {
            $adminRole = Role::firstOrCreate(
                ['name' => $adminName],
                ['description' => 'Administrator role']
            );
        }
    }
}
