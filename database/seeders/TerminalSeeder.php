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
            ['terminal_name' => 'LCR Terminal', 'barangay' => 'Dadiangas East', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.1135678, 'longitude' => 125.1703239],
            ['terminal_name' => 'Save More Calumpang Terminal', 'barangay' => 'Calumpang', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.077254, 'longitude' => 125.146266],
            ['terminal_name' => 'P. Acharon Blvd Terminal', 'barangay' => 'Dadiangas West', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.107059, 'longitude' => 125.170965],
            ['terminal_name' => 'Roxas East Transport Hub', 'barangay' => 'Dadiangas East', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.113095, 'longitude' => 125.173277],
            ['terminal_name' => 'Lagao E-Jeep Terminal', 'barangay' => 'Lagao', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.127855, 'longitude' => 125.196691],
            ['terminal_name' => 'Malakas Satellite Market Terminal', 'barangay' => 'City Heights', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.137967, 'longitude' => 125.171399],
            ['terminal_name' => 'KCC Entrance Terminal', 'barangay' => 'Lagao', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.117283, 'longitude' => 125.185948],
            ['terminal_name' => 'Bulaong Exit Terminal', 'barangay' => 'Dadiangas North', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.1127334, 'longitude' => 125.1630613],
        ];

        foreach ($terminals as $terminal) {
            $existingTerminal = Terminal::withTrashed()
                ->where('barangay', $terminal['barangay'])
                ->where('city', $terminal['city'])
                ->where('latitude', $terminal['latitude'])
                ->where('longitude', $terminal['longitude'])
                ->first();

            if ($existingTerminal) {
                if ($existingTerminal->trashed()) {
                    $existingTerminal->restore();
                }

                $existingTerminal->fill($terminal);
                $existingTerminal->save();

                continue;
            }

            Terminal::create($terminal);
        }
    }
}
