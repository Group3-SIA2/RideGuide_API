<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the permissions table with default permissions.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'view_users',         'display_name' => 'View Users',            'group' => 'users',         'description' => 'Can view the list of users.'],
            ['name' => 'create_users',       'display_name' => 'Create Users',          'group' => 'users',         'description' => 'Can create new user accounts.'],
            ['name' => 'edit_users',         'display_name' => 'Edit Users',            'group' => 'users',         'description' => 'Can edit existing user accounts.'],
            ['name' => 'delete_users',       'display_name' => 'Delete Users',          'group' => 'users',         'description' => 'Can delete user accounts.'],

            // Commuter Management
            ['name' => 'view_commuters',     'display_name' => 'View Commuters',        'group' => 'commuters',     'description' => 'Can view the list of commuters.'],
            ['name' => 'manage_commuters',   'display_name' => 'Manage Commuters',      'group' => 'commuters',     'description' => 'Can manage commuter records.'],

            // Driver Management
            ['name' => 'view_drivers',       'display_name' => 'View Drivers',          'group' => 'drivers',       'description' => 'Can view the list of drivers.'],
            ['name' => 'manage_drivers',     'display_name' => 'Manage Drivers',        'group' => 'drivers',       'description' => 'Can manage driver records.'],

            // Organization Management
            ['name' => 'view_organizations', 'display_name' => 'View Organizations',    'group' => 'organizations', 'description' => 'Can view the list of organizations.'],
            ['name' => 'create_organizations','display_name' => 'Create Organizations', 'group' => 'organizations', 'description' => 'Can create new organizations.'],
            ['name' => 'edit_organizations', 'display_name' => 'Edit Organizations',    'group' => 'organizations', 'description' => 'Can edit existing organizations.'],
            ['name' => 'delete_organizations','display_name' => 'Delete Organizations', 'group' => 'organizations', 'description' => 'Can delete organizations.'],

            // Dashboard
            ['name' => 'view_admin_dashboard',     'display_name' => 'View Admin Dashboard',  'group' => 'dashboard',     'description' => 'Can view the admin dashboard.'],
            ['name' => 'view_driver_dashboard',    'display_name' => 'View Driver Dashboard', 'group' => 'dashboard',     'description' => 'Can view the driver dashboard.'],
            ['name' => 'view_commuter_dashboard',  'display_name' => 'View Commuter Dashboard','group' => 'dashboard',     'description' => 'Can view the commuter dashboard.'],


            // Backup & Restore
            ['name' => 'view_backups',       'display_name' => 'View Backups',          'group' => 'backups',       'description' => 'Can view backup list.'],
            ['name' => 'create_backups',     'display_name' => 'Create Backups',        'group' => 'backups',       'description' => 'Can create new backups.'],
            ['name' => 'restore_backups',    'display_name' => 'Restore Backups',       'group' => 'backups',       'description' => 'Can restore from backups.'],
            ['name' => 'download_backups',   'display_name' => 'Download Backups',      'group' => 'backups',       'description' => 'Can download backup files.'],

            // Authorization Management
            ['name' => 'manage_authorization', 'display_name' => 'Manage Authorization', 'group' => 'authorization', 'description' => 'Can manage role permissions and user roles.'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                [
                    'display_name' => $perm['display_name'],
                    'group'        => $perm['group'],
                    'description'  => $perm['description'],
                ]
            );
        }

        // Give all permissions to the admin role by default
        $adminRole = Role::where('name', Role::ADMIN)->first();
        if ($adminRole) {
            $allPermissions = Permission::pluck('id')->toArray();
            $adminRole->permissions()->sync($allPermissions);
        }
    }
}
