<?php

namespace App\Console\Commands;

use App\Models\Role;
use Illuminate\Console\Command;

class VerifyPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify role permission assignments match specification';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== PERMISSION VERIFICATION ===');
        $this->newLine();

        $results = [];

        // Driver: 4 permissions (location, routes, available_commuters, route_planning) - NO "drivers"
        $results[] = $this->checkRole('driver', 
            ['view_map_locations', 'view_map_routes', 'view_map_available_commuters', 'view_map_route_planning'],
            ['view_map_drivers']
        );

        // Commuter: 4 permissions (location, routes, drivers, route_planning) - NO "available_commuters"
        $results[] = $this->checkRole('commuter',
            ['view_map_locations', 'view_map_routes', 'view_map_drivers', 'view_map_route_planning'],
            ['view_map_available_commuters']
        );

        // Organization: all 5 map permissions
        $results[] = $this->checkRole('organization',
            ['view_map_locations', 'view_map_routes', 'view_map_drivers', 'view_map_available_commuters', 'view_map_route_planning']
        );

        // Admin: all 5 map permissions
        $results[] = $this->checkRole('admin',
            ['view_map_locations', 'view_map_routes', 'view_map_drivers', 'view_map_available_commuters', 'view_map_route_planning']
        );

        // SuperAdmin: 0 permissions
        $results[] = $this->checkRole('super_admin', []);

        $this->newLine();
        $this->info('=== SUMMARY ===');
        
        $allPass = array_reduce($results, function($carry, $item) { return $carry && $item; }, true);

        if ($allPass) {
            $this->info('ALL CHECKS PASSED');
            return 0;
        } else {
            $this->error('SOME CHECKS FAILED');
            return 1;
        }
    }

    private function checkRole($roleName, $expectedPerms, $excludedPerms = [])
    {
        $role = Role::where('name', $roleName)->first();
        
        $this->info(strtoupper($roleName) . ':');
        
        if (!$role) {
            $this->error('  Role not found');
            return false;
        }
        
        $actualPerms = $role->permissions()->pluck('name')->toArray();
        sort($actualPerms);
        sort($expectedPerms);
        
        $this->line('  Expected count (minimum): ' . count($expectedPerms));
        $this->line('  Actual count: ' . count($actualPerms));
        
        // For Driver and Commuter, verify exact match
        // For Organization and Admin (roles with multiple permissions), verify they HAVE all required
        $isExactMatch = ($actualPerms === $expectedPerms);
        $hasAllRequired = count(array_intersect($expectedPerms, $actualPerms)) === count($expectedPerms);
        
        $this->line('  Expected: ' . json_encode($expectedPerms));
        $this->line('  Actual: ' . json_encode($actualPerms));
        
        // For special roles (organization, admin), verify they have all required map permissions
        // For driver/commuter, verify exact match
        if (in_array($roleName, ['organization', 'admin'])) {
            $match = $hasAllRequired;
            if ($match) {
                $this->info('  PASS (Has all required map permissions)');
            } else {
                $this->error(' FAIL (Missing required permissions)');
                $missing = array_diff($expectedPerms, $actualPerms);
                if ($missing) $this->line('  Missing: ' . json_encode($missing));
                $match = false;
            }
        } else {
            $match = $isExactMatch;
            if ($match) {
                $this->info('  PASS');
            } else {
                $this->error('  FAIL');
                $missing = array_diff($expectedPerms, $actualPerms);
                $extra = array_diff($actualPerms, $expectedPerms);
                if ($missing) $this->line('  Missing: ' . json_encode($missing));
                if ($extra) $this->line('  Extra: ' . json_encode($extra));
                $match = false;
            }
        }
        
        foreach ($excludedPerms as $excluded) {
            if (in_array($excluded, $actualPerms)) {
                $this->error(' FAIL: Has excluded permission \'' . $excluded . '\'');
                $match = false;
            }
        }
        
        $this->newLine();
        return $match;
    }
}
