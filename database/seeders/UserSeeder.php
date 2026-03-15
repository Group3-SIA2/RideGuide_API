<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Super Admin account
        // Email   : superadmin@rideguide.com
        // Password: SuperAdmin@2026
        User::updateOrCreate(
            ['email' => 'edriane.bangonon26@gmail.com'],
            [
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'middle_name'       => null,
                'birthdate'         => '1990-01-01',
                'profile_picture'   => null,
                'email_verified_at' => now(),
                'password'          => Hash::make('SuperAdmin@2026'),
                'status'            => User::STATUS_ACTIVE,
                'status_reason'     => null,
                'status_changed_at' => now(),
            ]
        );

        // Admin account
        // Email   : marjovicdev@gmail.com
        // Password: Admin@2026
        User::updateOrCreate(
            ['email' => 'marjovicdev@gmail.com'],
            [
                'first_name'        => 'Admin',
                'last_name'         => 'User',
                'middle_name'       => null,
                'birthdate'         => '1990-01-01',
                'profile_picture'   => null,
                'email_verified_at' => now(),
                'password'          => Hash::make('Admin@2026'),
                'status'            => User::STATUS_ACTIVE,
                'status_reason'     => null,
                'status_changed_at' => now(),
            ]
        );
    }
}
