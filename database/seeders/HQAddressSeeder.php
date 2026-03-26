<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\HqAddress;

class HQAddressSeeder extends Seeder
{
    public function run(): void
    {
        $addresses = [
            [
                'barangay' => 'Bulaong',
                'street' => 'National Highway',
                'subdivision' => 'Bulaong Proper',
                'floor_unit_room' => null,
                'lat' => '6.1164',
                'lng' => '125.1716',
            ],
            [
                'barangay' => 'Calumpang',
                'street' => 'Calumpang Road',
                'subdivision' => 'Zone 1',
                'floor_unit_room' => null,
                'lat' => '6.1092',
                'lng' => '125.1685',
            ],
            [
                'barangay' => 'City Heights',
                'street' => 'Santiago Blvd',
                'subdivision' => 'Phase 2',
                'floor_unit_room' => 'Unit 3B',
                'lat' => '6.1201',
                'lng' => '125.1763',
            ],
            [
                'barangay' => 'Dadiangas',
                'street' => 'Pendatun Avenue',
                'subdivision' => 'Central District',
                'floor_unit_room' => null,
                'lat' => '6.1145',
                'lng' => '125.1732',
            ],
            [
                'barangay' => 'Lagao',
                'street' => 'San Miguel Street',
                'subdivision' => 'Lagao 1',
                'floor_unit_room' => null,
                'lat' => '6.1223',
                'lng' => '125.1654',
            ],
            [
                'barangay' => 'Labangal',
                'street' => 'Diversion Road',
                'subdivision' => 'Labangal Proper',
                'floor_unit_room' => null,
                'lat' => '6.1011',
                'lng' => '125.1502',
            ],
            [
                'barangay' => 'Apopong',
                'street' => 'Apopong Road',
                'subdivision' => 'Upper Apopong',
                'floor_unit_room' => null,
                'lat' => '6.1356',
                'lng' => '125.1810',
            ],
            [
                'barangay' => 'Bula',
                'street' => 'Bula Road',
                'subdivision' => 'Bula Center',
                'floor_unit_room' => null,
                'lat' => '6.0954',
                'lng' => '125.1403',
            ],
            [
                'barangay' => 'San Isidro',
                'street' => 'San Isidro Street',
                'subdivision' => 'Zone 2',
                'floor_unit_room' => null,
                'lat' => '6.1302',
                'lng' => '125.1904',
            ],
            [
                'barangay' => 'Fatima',
                'street' => 'Fatima Road',
                'subdivision' => 'Phase 1',
                'floor_unit_room' => 'Room 101',
                'lat' => '6.1187',
                'lng' => '125.1608',
            ],
            [
                'barangay' => 'Makar Wharf',
                'street' => 'Makar Port Road',
                'subdivision' => 'Port Area',
                'floor_unit_room' => null,
                'lat' => '6.0899',
                'lng' => '125.1567',
            ],
            [
                'barangay' => 'General Santos Fish Port Complex',
                'street' => 'Fishport Road',
                'subdivision' => 'Complex Area',
                'floor_unit_room' => null,
                'lat' => '6.0912',
                'lng' => '125.1575',
            ],
        ];

        foreach ($addresses as $addr) {
            HqAddress::create([
                'id' => Str::uuid(),
                'barangay' => $addr['barangay'],
                'street' => $addr['street'],
                'subdivision' => $addr['subdivision'],
                'floor_unit_room' => $addr['floor_unit_room'],
                'lat' => $addr['lat'],
                'lng' => $addr['lng'],
            ]);
        }
    }
}