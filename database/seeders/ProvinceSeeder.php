<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            ['name' => 'South Cotabato', 'code' => 'PH-SCO', 'region' => 'Region XII'],
            ['name' => 'Sultan Kudarat', 'code' => 'PH-SUK', 'region' => 'Region XII'],
            ['name' => 'Sarangani', 'code' => 'PH-SAR', 'region' => 'Region XII'],
            ['name' => 'Cotabato', 'code' => 'PH-NCO', 'region' => 'Region XII'],
            ['name' => 'Davao del Sur', 'code' => 'PH-DAS', 'region' => 'Region XI'],
            ['name' => 'Davao del Norte', 'code' => 'PH-DAV', 'region' => 'Region XI'],
            ['name' => 'Davao de Oro', 'code' => 'PH-COM', 'region' => 'Region XI'],
            ['name' => 'Davao Oriental', 'code' => 'PH-DAO', 'region' => 'Region XI'],
            ['name' => 'Davao Occidental', 'code' => 'PH-DVO', 'region' => 'Region XI'],
            ['name' => 'Metro Manila', 'code' => 'PH-00', 'region' => 'NCR'],
            ['name' => 'Cebu', 'code' => 'PH-CEB', 'region' => 'Region VII'],
            ['name' => 'Iloilo', 'code' => 'PH-ILI', 'region' => 'Region VI'],
            ['name' => 'Misamis Oriental', 'code' => 'PH-MSR', 'region' => 'Region X'],
            ['name' => 'Bukidnon', 'code' => 'PH-BUK', 'region' => 'Region X'],
            ['name' => 'Agusan del Norte', 'code' => 'PH-AGN', 'region' => 'Region XIII'],
            ['name' => 'Agusan del Sur', 'code' => 'PH-AGS', 'region' => 'Region XIII'],
            ['name' => 'Surigao del Norte', 'code' => 'PH-SUN', 'region' => 'Region XIII'],
            ['name' => 'Surigao del Sur', 'code' => 'PH-SUR', 'region' => 'Region XIII'],
        ];

        foreach ($provinces as $province) {
            Province::query()->updateOrCreate(
                ['name' => $province['name']],
                [
                    'code' => $province['code'],
                    'region' => $province['region'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}
