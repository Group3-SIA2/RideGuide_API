<?php

namespace Database\Seeders;

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
        // Seed core lookup/reference data first.
        $this->call(RoleSeeder::class);
        $this->call(DiscountTypesSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(BarangaySeeder::class);
        $this->call(ProvinceSeeder::class);
        $this->call(HQAddressSeeder::class);
        $this->call(OrganizationTypeSeeder::class);
        $this->call(OrganizationSeeder::class);
        $this->call(TerminalSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(UserRoleSeeder::class);
    }
}
