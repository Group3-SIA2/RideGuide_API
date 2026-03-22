<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $UserRoles = [
            [
                'role_id' => Role::getIdbyName(Role::SUPER_ADMIN),
                'user_id' => User::getIdByFirstName('Super'),
            ],
            [ 
                'role_id' => Role::getIdbyName(Role::ADMIN),
                'user_id' => User::getIdByFirstName('Admin'),
            ],
            [ 
                'role_id' => Role::getIdbyName(Role::ORGANIZATION),
                'user_id' => User::getIdByFirstName('Org'),
            ]
            
        ];

        foreach ($UserRoles as $userRole) {
            \App\Models\UserRole::firstOrCreate($userRole);
        }
    }
}
