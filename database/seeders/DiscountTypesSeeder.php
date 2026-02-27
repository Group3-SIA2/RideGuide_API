<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiscountTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some sample discount types
        $discountTypes = [
            ['classification_name' => 'Student'],
            ['classification_name' => 'Senior Citizen'],
            ['classification_name' => 'PWD'],
            ['classification_name' => 'Regular'],
        ];

        foreach ($discountTypes as $type) {
            \App\Models\DiscountTypes::create($type);
        }
    }
}
