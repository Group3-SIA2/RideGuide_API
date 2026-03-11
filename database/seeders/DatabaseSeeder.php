<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles first
        $this->call(RoleSeeder::class);
        $this->call(DiscountTypesSeeder::class);
       // $this->call(OrganizationSeeder::class);

        // Super Admin account
        // Email   : superadmin@rideguide.com
        // Password: SuperAdmin@2026
        User::firstOrCreate(
            ['email' => 'superadmin@rideguide.com'],
            [
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'middle_name'       => null,
                'birthdate'          => '1990-01-01',
                'profile_picture'   => null,
                'email_verified_at' => now(),
                'password'          => Hash::make('SuperAdmin@2026'),
            ]
        );

        // Admin account
        // Email   : admin@rideguide.com
        // Password: Admin@2026
        User::firstOrCreate(
            ['email' => 'admin@rideguide.com'],
            [
                'first_name'        => 'Admin',
                'last_name'         => 'User',
                'middle_name'       => null,
                'birthdate'          => '1990-01-01',
                'profile_picture'   => null,
                'email_verified_at' => now(),
                'password'          => Hash::make('Admin@2026'),
            ]
        );

        $this->call(UserRoleSeeder::class);
    }
}
