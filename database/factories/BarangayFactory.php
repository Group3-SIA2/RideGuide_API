<?php

namespace Database\Factories;

use App\Models\Barangay;
use Illuminate\Database\Eloquent\Factories\Factory;

class BarangayFactory extends Factory
{
    protected $model = Barangay::class;

    public function definition(): array
    {
        $barangayNames = [
            'Downtown', 'Alabel', 'Bagacay', 'Baluan', 'Banuyo',
            'Conel', 'Dadiangas', 'Katangawan', 'Lagao', 'Lanao',
            'Mabini', 'Manuguay', 'Nueve', 'Polomolok', 'Sarangani',
            'Tinang', 'Tuen', 'Villa Arevalo', 'Boliney'
        ];
        $name = $this->faker->randomElement($barangayNames);

        return [
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'center_latitude' => 6.1184,
            'center_longitude' => 125.1774,
            'north_latitude' => 6.3,
            'south_latitude' => 5.9,
            'east_longitude' => 125.4,
            'west_longitude' => 125.0,
        ];
    }
}
