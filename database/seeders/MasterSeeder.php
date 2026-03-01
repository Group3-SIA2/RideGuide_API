<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //MasterSeeder will call all the other seeders to populate the database with initial data.
        $this->call([
            DatabaseSeeder::class,
            RoleSeeder::class,
            DiscountTypesSeeder::class,
        ]);
    }
}
