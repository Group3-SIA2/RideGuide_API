<?php

namespace Database\Seeders;

use App\Models\Terminal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TerminalSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed default terminals used in the application.
     */
    public function run(): void
    {
        $terminals = [
            ['terminal_name' => 'TODA - City Heights Terminal', 'barangay' => 'City Heights', 'city' => 'General Santos City', 'latitude' => 6.137000, 'longitude' => 125.171000],
            ['terminal_name' => 'TODA - Dadiangas Terminal', 'barangay' => 'Dadiangas', 'city' => 'General Santos City', 'latitude' => 6.119200, 'longitude' => 125.177500],
            ['terminal_name' => 'TODA - Lagao Terminal', 'barangay' => 'Lagao', 'city' => 'General Santos City', 'latitude' => 6.130400, 'longitude' => 125.175900],
            ['terminal_name' => 'TODA - Labangal Terminal', 'barangay' => 'Labangal', 'city' => 'General Santos City', 'latitude' => 6.103500, 'longitude' => 125.177200],
            ['terminal_name' => 'TODA - Apopong Terminal', 'barangay' => 'Apopong', 'city' => 'General Santos City', 'latitude' => 6.100300, 'longitude' => 125.152900],
            ['terminal_name' => 'TODA - Bula Terminal', 'barangay' => 'Bula', 'city' => 'General Santos City', 'latitude' => 6.084800, 'longitude' => 125.147400],
            ['terminal_name' => 'TODA - San Isidro Terminal', 'barangay' => 'San Isidro', 'city' => 'General Santos City', 'latitude' => 6.129200, 'longitude' => 125.154700],
            ['terminal_name' => 'TODA - Fatima Terminal', 'barangay' => 'Fatima', 'city' => 'General Santos City', 'latitude' => 6.106400, 'longitude' => 125.153200],
            ['terminal_name' => 'MODA - Makar Wharf Terminal', 'barangay' => 'Makar', 'city' => 'General Santos City', 'latitude' => 6.072600, 'longitude' => 125.162500],
            ['terminal_name' => 'MODA - Fishport Terminal', 'barangay' => 'Tambler', 'city' => 'General Santos City', 'latitude' => 6.065700, 'longitude' => 125.112900],
        ];

        foreach ($terminals as $terminal) {
            Terminal::updateOrCreate(
                ['terminal_name' => $terminal['terminal_name']],
                $terminal
            );
        }
    }
}
