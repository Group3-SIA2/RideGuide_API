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
            ['name' => 'create_users',       'display_name' => 'Create Users',          'group' => 'users',         'description' => 'Can register new user accounts and verify their email via OTP.'],
            ['name' => 'manage_users',       'display_name' => 'Manage Users',          'group' => 'users',         'description' => 'Can manage user status and restoration workflows.'],
            ['name' => 'edit_users',         'display_name' => 'Edit Users',            'group' => 'users',         'description' => 'Can edit existing user accounts.'],
            ['name' => 'delete_users',       'display_name' => 'Delete Users',          'group' => 'users',         'description' => 'Can delete user accounts.'],

            // Commuter Management
            ['name' => 'view_commuters',     'display_name' => 'View Commuters',        'group' => 'commuters',     'description' => 'Can view the list of commuters.'],
            ['name' => 'manage_commuters',   'display_name' => 'Manage Commuters',      'group' => 'commuters',     'description' => 'Can manage commuter records.'],

            // Driver Management
            ['name' => 'view_drivers',       'display_name' => 'View Drivers',          'group' => 'drivers',       'description' => 'Can view the list of drivers.'],
            ['name' => 'manage_drivers',     'display_name' => 'Manage Drivers',        'group' => 'drivers',       'description' => 'Can manage driver records.'],
            ['name' => 'assign_drivers_to_organization', 'display_name' => 'Assign Drivers To Organization', 'group' => 'drivers', 'description' => 'Can assign and unassign drivers for owned organization.'],
            ['name' => 'unassign_drivers_from_organization', 'display_name' => 'Unassign Drivers From Organization', 'group' => 'drivers', 'description' => 'Can unassign drivers from owned organization.'],

            // Organization Management
            ['name' => 'view_organizations', 'display_name' => 'View Organizations',    'group' => 'organizations', 'description' => 'Can view the list of organizations.'],
            ['name' => 'create_organizations','display_name' => 'Create Organizations', 'group' => 'organizations', 'description' => 'Can create new organizations.'],
            ['name' => 'edit_organizations', 'display_name' => 'Edit Organizations',    'group' => 'organizations', 'description' => 'Can edit existing organizations.'],
            ['name' => 'delete_organizations','display_name' => 'Delete Organizations', 'group' => 'organizations', 'description' => 'Can delete organizations.'],
            ['name' => 'manage_organization_types','display_name' => 'Manage Organization Types', 'group' => 'organizations', 'description' => 'Can create and manage organization types.'],
            ['name' => 'manage_organization_terminals','display_name' => 'Manage Organization Terminals', 'group' => 'organizations', 'description' => 'Can add terminals to owned organizations.'],
            ['name' => 'view_organization_assignments', 'display_name' => 'View Organization Assignments', 'group' => 'organizations', 'description' => 'Can view the driver and terminal assignment panel.'],
            ['name' => 'view_organization_terminals', 'display_name' => 'View Organization Terminals', 'group' => 'organizations', 'description' => 'Can view terminals linked to organizations.'],
            ['name' => 'assign_organization_terminals', 'display_name' => 'Assign Existing Organization Terminals', 'group' => 'organizations', 'description' => 'Can link existing terminals to owned organizations.'],
            ['name' => 'create_organization_terminals', 'display_name' => 'Create Organization Terminals', 'group' => 'organizations', 'description' => 'Can create and link new terminals to owned organizations.'],
            ['name' => 'delete_organization_terminals', 'display_name' => 'Delete Organization Terminals', 'group' => 'organizations', 'description' => 'Can remove linked terminals from owned organizations.'],

            // Dashboard
            ['name' => 'view_admin_dashboard',        'display_name' => 'View Admin Dashboard',        'group' => 'dashboard', 'description' => 'Can view the admin dashboard.'],
            ['name' => 'view_organization_dashboard', 'display_name' => 'View Organization Dashboard', 'group' => 'dashboard', 'description' => 'Can view the organization manager dashboard.'],

            // Backup & Restore
            ['name' => 'view_backups',       'display_name' => 'View Backups',          'group' => 'backups',       'description' => 'Can view backup list.'],
            ['name' => 'create_backups',     'display_name' => 'Create Backups',        'group' => 'backups',       'description' => 'Can create new backups.'],
            ['name' => 'restore_backups',    'display_name' => 'Restore Backups',       'group' => 'backups',       'description' => 'Can restore from backups.'],
            ['name' => 'download_backups',   'display_name' => 'Download Backups',      'group' => 'backups',       'description' => 'Can download backup files.'],

            // Authorization Management
            ['name' => 'manage_authorization', 'display_name' => 'Manage Authorization', 'group' => 'authorization', 'description' => 'Can manage role permissions and user roles.'],

            // Transaction Logbook
            ['name' => 'view_transactions', 'display_name' => 'View Transactions', 'group' => 'audit', 'description' => 'Can view transaction logbook.'],
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

        $seededPermissionNames = collect($permissions)->pluck('name')->all();

        // Admin gets all seeded permissions (create_users is now included).
        $adminRole = Role::where('name', Role::ADMIN)->first();
        if ($adminRole) {
            $adminPermissions = Permission::whereIn('name', $seededPermissionNames)
                ->pluck('id')
                ->toArray();
            $adminRole->permissions()->sync($adminPermissions);
        }

        $organizationRole = Role::where('name', Role::ORGANIZATION)->first();
        if ($organizationRole) {
            $organizationPermissions = Permission::whereIn('name', [
                'view_organizations',
                'view_drivers',
                'view_organization_assignments',
                'view_organization_terminals',
                'assign_drivers_to_organization',
                'unassign_drivers_from_organization',
                'assign_organization_terminals',
                'create_organization_terminals',
                'delete_organization_terminals',
                'view_organization_dashboard',
                'manage_organization_terminals',
            ])->pluck('id')->toArray();

            $organizationRole->permissions()->sync($organizationPermissions);
        }
    }
}