<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class BarangaySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the 19 barangays of General Santos City.
     */
    public function run(): void
    {
        $barangays = [
            [
                'name' => 'Alabel',
                'code' => 'ALABEL',
                'center_latitude' => 6.1050,
                'center_longitude' => 125.1800,
                'north_latitude' => 6.1150,
                'south_latitude' => 6.0950,
                'east_longitude' => 125.1900,
                'west_longitude' => 125.1700,
            ],
            [
                'name' => 'Balon-Balon',
                'code' => 'BALON_BALON',
                'center_latitude' => 6.1150,
                'center_longitude' => 125.1650,
                'north_latitude' => 6.1250,
                'south_latitude' => 6.1050,
                'east_longitude' => 125.1750,
                'west_longitude' => 125.1550,
            ],
            [
                'name' => 'Bañcon',
                'code' => 'BANCON',
                'center_latitude' => 6.0950,
                'center_longitude' => 125.1950,
                'north_latitude' => 6.1050,
                'south_latitude' => 6.0850,
                'east_longitude' => 125.2050,
                'west_longitude' => 125.1850,
            ],
            [
                'name' => 'Buayan',
                'code' => 'BUAYAN',
                'center_latitude' => 6.1300,
                'center_longitude' => 125.1500,
                'north_latitude' => 6.1400,
                'south_latitude' => 6.1200,
                'east_longitude' => 125.1600,
                'west_longitude' => 125.1400,
            ],
            [
                'name' => 'Calumpang',
                'code' => 'CALUMPANG',
                'center_latitude' => 6.0800,
                'center_longitude' => 125.1650,
                'north_latitude' => 6.0900,
                'south_latitude' => 6.0700,
                'east_longitude' => 125.1750,
                'west_longitude' => 125.1550,
            ],
            [
                'name' => 'Calamcam',
                'code' => 'CALAMCAM',
                'center_latitude' => 6.1400,
                'center_longitude' => 125.2050,
                'north_latitude' => 6.1500,
                'south_latitude' => 6.1300,
                'east_longitude' => 125.2150,
                'west_longitude' => 125.1950,
            ],
            [
                'name' => 'Cawit',
                'code' => 'CAWIT',
                'center_latitude' => 6.1150,
                'center_longitude' => 125.2150,
                'north_latitude' => 6.1250,
                'south_latitude' => 6.1050,
                'east_longitude' => 125.2250,
                'west_longitude' => 125.2050,
            ],
            [
                'name' => 'Downtown',
                'code' => 'DOWNTOWN',
                'center_latitude' => 6.1150,
                'center_longitude' => 125.1850,
                'north_latitude' => 6.1250,
                'south_latitude' => 6.1050,
                'east_longitude' => 125.1950,
                'west_longitude' => 125.1750,
            ],
            [
                'name' => 'Fatima',
                'code' => 'FATIMA',
                'center_latitude' => 6.0950,
                'center_longitude' => 125.1700,
                'north_latitude' => 6.1050,
                'south_latitude' => 6.0850,
                'east_longitude' => 125.1800,
                'west_longitude' => 125.1600,
            ],
            [
                'name' => 'Gasi',
                'code' => 'GASI',
                'center_latitude' => 6.1350,
                'center_longitude' => 125.1800,
                'north_latitude' => 6.1450,
                'south_latitude' => 6.1250,
                'east_longitude' => 125.1900,
                'west_longitude' => 125.1700,
            ],
            [
                'name' => 'General Luna',
                'code' => 'GENERAL_LUNA',
                'center_latitude' => 6.1050,
                'center_longitude' => 125.2050,
                'north_latitude' => 6.1150,
                'south_latitude' => 6.0950,
                'east_longitude' => 125.2150,
                'west_longitude' => 125.1950,
            ],
            [
                'name' => 'Gravahan',
                'code' => 'GRAVAHAN',
                'center_latitude' => 6.1250,
                'center_longitude' => 125.1350,
                'north_latitude' => 6.1350,
                'south_latitude' => 6.1150,
                'east_longitude' => 125.1450,
                'west_longitude' => 125.1250,
            ],
            [
                'name' => 'Hacienda',
                'code' => 'HACIENDA',
                'center_latitude' => 6.1450,
                'center_longitude' => 125.1650,
                'north_latitude' => 6.1550,
                'south_latitude' => 6.1350,
                'east_longitude' => 125.1750,
                'west_longitude' => 125.1550,
            ],
            [
                'name' => 'Katipunan',
                'code' => 'KATIPUNAN',
                'center_latitude' => 6.1350,
                'center_longitude' => 125.2200,
                'north_latitude' => 6.1450,
                'south_latitude' => 6.1250,
                'east_longitude' => 125.2300,
                'west_longitude' => 125.2100,
            ],
            [
                'name' => 'Kaunoran',
                'code' => 'KAUNORAN',
                'center_latitude' => 6.0750,
                'center_longitude' => 125.1850,
                'north_latitude' => 6.0850,
                'south_latitude' => 6.0650,
                'east_longitude' => 125.1950,
                'west_longitude' => 125.1750,
            ],
            [
                'name' => 'Langkaan',
                'code' => 'LANGKAAN',
                'center_latitude' => 6.1500,
                'center_longitude' => 125.1950,
                'north_latitude' => 6.1600,
                'south_latitude' => 6.1400,
                'east_longitude' => 125.2050,
                'west_longitude' => 125.1850,
            ],
            [
                'name' => 'Mabua',
                'code' => 'MABUA',
                'center_latitude' => 6.1200,
                'center_longitude' => 125.1400,
                'north_latitude' => 6.1300,
                'south_latitude' => 6.1100,
                'east_longitude' => 125.1500,
                'west_longitude' => 125.1300,
            ],
            [
                'name' => 'San Isidro',
                'code' => 'SAN_ISIDRO',
                'center_latitude' => 6.1100,
                'center_longitude' => 125.1300,
                'north_latitude' => 6.1200,
                'south_latitude' => 6.1000,
                'east_longitude' => 125.1400,
                'west_longitude' => 125.1200,
            ],
            [
                'name' => 'Tambler',
                'code' => 'TAMBLER',
                'center_latitude' => 6.1400,
                'center_longitude' => 125.1350,
                'north_latitude' => 6.1500,
                'south_latitude' => 6.1300,
                'east_longitude' => 125.1450,
                'west_longitude' => 125.1250,
            ],
        ];

        foreach ($barangays as $barangay) {
            $existingBarangay = DB::table('barangays')
                ->where('code', $barangay['code'])
                ->first();

            if ($existingBarangay) {
                DB::table('barangays')
                    ->where('code', $barangay['code'])
                    ->update([
                        'name' => $barangay['name'],
                        'center_latitude' => $barangay['center_latitude'],
                        'center_longitude' => $barangay['center_longitude'],
                        'north_latitude' => $barangay['north_latitude'],
                        'south_latitude' => $barangay['south_latitude'],
                        'east_longitude' => $barangay['east_longitude'],
                        'west_longitude' => $barangay['west_longitude'],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('barangays')->insert([
                'id' => Uuid::uuid4()->toString(),
                'name' => $barangay['name'],
                'code' => $barangay['code'],
                'center_latitude' => $barangay['center_latitude'],
                'center_longitude' => $barangay['center_longitude'],
                'north_latitude' => $barangay['north_latitude'],
                'south_latitude' => $barangay['south_latitude'],
                'east_longitude' => $barangay['east_longitude'],
                'west_longitude' => $barangay['west_longitude'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
