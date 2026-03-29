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
            ['terminal_name' => 'Lagao Public Transport Terminal', 'barangay' => 'Lagao', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.12176000, 'longitude' => 125.17409000],
            ['terminal_name' => 'Calumpang Public Transport Terminal', 'barangay' => 'Calumpang', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.11124000, 'longitude' => 125.17197000],
            ['terminal_name' => 'Pioneer Avenue Transport Hub', 'barangay' => 'Dadiangas South', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.11688000, 'longitude' => 125.17120000],
            ['terminal_name' => 'Roxas East Transport Hub', 'barangay' => 'Dadiangas East', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.11524000, 'longitude' => 125.17764000],
            ['terminal_name' => 'Lagao Tricycle Terminal', 'barangay' => 'Lagao', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.12328000, 'longitude' => 125.16832000],
            ['terminal_name' => 'City Heights Tricycle Terminal', 'barangay' => 'City Heights', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.12561000, 'longitude' => 125.17351000],
            ['terminal_name' => 'Conel E-Jeepney Terminal', 'barangay' => 'Conel', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.09086000, 'longitude' => 125.13485000],
            ['terminal_name' => 'Tambler E-Jeepney Terminal', 'barangay' => 'Tambler', 'city' => 'General Santos City, South Cotabato', 'latitude' => 6.09237000, 'longitude' => 125.15742000],
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
