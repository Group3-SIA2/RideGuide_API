<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HqAddress;

class HQAddressSeeder extends Seeder
{
    public function run(): void
    {
        $addresses = [
            [
                'barangay' => 'Lagao',
                'street' => 'Santiago Boulevard',
                'subdivision' => 'Lagao Proper',
                'floor_unit_room' => null,
                'lat' => '6.121760',
                'lng' => '125.174090',
            ],
            [
                'barangay' => 'Calumpang',
                'street' => 'Jose Catolico Sr. Avenue',
                'subdivision' => 'Calumpang Proper',
                'floor_unit_room' => null,
                'lat' => '6.111240',
                'lng' => '125.171970',
            ],
            [
                'barangay' => 'Dadiangas South',
                'street' => 'Pioneer Avenue',
                'subdivision' => 'Central Business District',
                'floor_unit_room' => null,
                'lat' => '6.116880',
                'lng' => '125.171200',
            ],
            [
                'barangay' => 'Dadiangas East',
                'street' => 'Roxas East Avenue',
                'subdivision' => 'Dadiangas East Proper',
                'floor_unit_room' => null,
                'lat' => '6.115240',
                'lng' => '125.177640',
            ],
            [
                'barangay' => 'Lagao',
                'street' => 'San Miguel Street',
                'subdivision' => 'Lagao 1',
                'floor_unit_room' => null,
                'lat' => '6.123280',
                'lng' => '125.168320',
            ],
            [
                'barangay' => 'City Heights',
                'street' => 'Santiago Boulevard',
                'subdivision' => 'City Heights Proper',
                'floor_unit_room' => null,
                'lat' => '6.125610',
                'lng' => '125.173510',
            ],
            [
                'barangay' => 'Conel',
                'street' => 'National Highway',
                'subdivision' => 'Conel Proper',
                'floor_unit_room' => null,
                'lat' => '6.090860',
                'lng' => '125.134850',
            ],
            [
                'barangay' => 'Tambler',
                'street' => 'Fishport Road',
                'subdivision' => 'Fishport Zone',
                'floor_unit_room' => null,
                'lat' => '6.092370',
                'lng' => '125.157420',
            ],
        ];

        foreach ($addresses as $addr) {
            HqAddress::updateOrCreate(
                [
                    'barangay' => $addr['barangay'],
                    'street' => $addr['street'],
                ],
                [
                    'subdivision' => $addr['subdivision'],
                    'floor_unit_room' => $addr['floor_unit_room'],
                    'lat' => $addr['lat'],
                    'lng' => $addr['lng'],
                ]
            );
        }
    }
}