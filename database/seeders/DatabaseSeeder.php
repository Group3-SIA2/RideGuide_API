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
        $this->call(PermissionSeeder::class);
        $this->call(OrganizationSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(UserRoleSeeder::class);
    }
}
