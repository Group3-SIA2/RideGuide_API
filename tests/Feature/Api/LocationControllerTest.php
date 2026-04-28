<?php

namespace Tests\Feature\Api;

use App\Models\Barangay;
use App\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;
    /**
     * GET /api/locations/terminals
     */

    public function test_get_terminals_returns_all_terminals_with_correct_structure()
    {
        // Create some test terminals
        $terminal1 = Terminal::factory()->create([
            'terminal_name' => 'Terminal A',
            'latitude' => 6.1184,
            'longitude' => 125.1774,
        ]);
        $terminal2 = Terminal::factory()->create([
            'terminal_name' => 'Terminal B',
            'latitude' => 6.1200,
            'longitude' => 125.1800,
        ]);

        // Make request (no auth required)
        $response = $this->getJson('/api/locations/terminals');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert response is JSON array
        $response->assertJsonIsArray();

        // Assert terminals are in response
        $response->assertJsonCount(2);

        // Assert structure of each terminal
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'latitude', 'longitude']
        ]);

        // Assert correct data
        $response->assertJsonFragment([
            'name' => 'Terminal A',
            'latitude' => 6.1184,
            'longitude' => 125.1774,
        ]);
    }

    public function test_get_terminals_supports_barangay_id_filter()
    {
        // Create barangays
        $barangay1 = Barangay::factory()->create(['name' => 'Downtown']);
        $barangay2 = Barangay::factory()->create(['name' => 'Alabel']);

        // Create terminals for different barangays
        $terminal1 = Terminal::factory()->create(['barangay' => $barangay1->id]);
        $terminal2 = Terminal::factory()->create(['barangay' => $barangay2->id]);

        // Query with barangay_id filter
        $response = $this->getJson("/api/locations/terminals?barangay_id={$barangay1->id}");

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert only one terminal returned (from barangay1)
        $response->assertJsonCount(1);

        // Assert the returned terminal is from barangay1
        $response->assertJsonPath('0.id', $terminal1->id);
    }

    public function test_get_terminals_respects_limit_parameter()
    {
        // Create 10 terminals
        Terminal::factory()->count(10)->create();

        // Query with limit=5
        $response = $this->getJson('/api/locations/terminals?limit=5');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert max 5 terminals returned
        $response->assertJsonCount(5);
    }

    public function test_get_terminals_returns_empty_array_if_no_terminals()
    {
        // No terminals created

        // Query terminals
        $response = $this->getJson('/api/locations/terminals');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert empty array
        $response->assertJsonCount(0);
    }

    /**
     * GET /api/locations/routes
     */

    public function test_get_routes_returns_routes_with_structure()
    {
        // Make request (no auth required)
        $response = $this->getJson('/api/locations/routes');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert response is JSON array (currently empty until Route model exists)
        $response->assertJsonIsArray();
    }

    public function test_get_routes_returns_empty_array_if_no_routes()
    {
        // Make request
        $response = $this->getJson('/api/locations/routes');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert empty array (Route model doesn't exist yet)
        $response->assertJsonCount(0);
    }

    /**
     * GET /api/locations/barangays
     */

    public function test_get_barangays_returns_all_barangays_with_structure()
    {
        // Create 5 barangays
        Barangay::factory()->count(5)->create();

        // Make request (no auth required)
        $response = $this->getJson('/api/locations/barangays');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert response is JSON array
        $response->assertJsonIsArray();

        // Assert 5 barangays returned
        $response->assertJsonCount(5);

        // Assert correct structure
        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'code',
                'center_latitude',
                'center_longitude',
                'north_latitude',
                'south_latitude',
                'east_longitude',
                'west_longitude',
            ]
        ]);
    }

    public function test_get_barangays_contains_gsc_barangays()
    {
        // Create barangays for GSC (General Santos City)
        $downtown = Barangay::factory()->create(['name' => 'Downtown']);
        $alabel = Barangay::factory()->create(['name' => 'Alabel']);
        Barangay::factory()->count(3)->create();

        // Make request
        $response = $this->getJson('/api/locations/barangays');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert 'Downtown' is in response
        $this->assertTrue(
            collect($response->json())->pluck('name')->contains('Downtown'),
            'Downtown barangay not found in response'
        );

        // Assert 'Alabel' is in response
        $this->assertTrue(
            collect($response->json())->pluck('name')->contains('Alabel'),
            'Alabel barangay not found in response'
        );
    }

    /**
     * GET /api/map/available-filters
     */

    public function test_get_available_filters_requires_authentication()
    {
        // Make request without authentication
        $response = $this->getJson('/api/map/available-filters');

        // Assert 401 Unauthorized (from Sanctum middleware)
        $response->assertStatus(401);
    }

    public function test_get_available_filters_super_admin_sees_all_filters()
    {
        // Create and authenticate SuperAdmin
        $superAdmin = \App\Models\User::factory()->create();
        $superAdminRole = $this->getSuperAdminRoleId();
        $superAdmin->roles()->attach($superAdminRole);

        // Make authenticated request
        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/map/available-filters');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert response structure
        $response->assertJsonStructure([
            'success',
            'user_role',
            'available_filters' => [
                '*' => ['id', 'name', 'enabled', 'description']
            ]
        ]);

        // Assert success
        $response->assertJsonPath('success', true);

        // Assert user_role is super_admin
        $response->assertJsonPath('user_role', 'super_admin');

        // Assert all 5 filters are present
        $response->assertJsonCount(5, 'available_filters');

        // Verify filter IDs
        $filterIds = collect($response->json('available_filters'))->pluck('id')->toArray();
        $this->assertEquals(
            ['location', 'routes', 'drivers', 'available_commuters', 'route_planning'],
            $filterIds
        );

        // Assert all filters have enabled = false
        foreach ($response->json('available_filters') as $filter) {
            $this->assertFalse($filter['enabled']);
            $this->assertIsString($filter['name']);
            $this->assertIsString($filter['description']);
        }
    }

    public function test_get_available_filters_driver_sees_limited_filters()
    {
        // Create and authenticate driver with permissions
        $driver = \App\Models\User::factory()->create();
        $driverRole = $this->getDriverRoleId();
        $driver->roles()->attach($driverRole);

        // Attach permissions to driver role
        $this->attachPermissionsToRole($driverRole, [
            'view_map_locations',
            'view_map_routes',
            'view_map_available_commuters',
            'view_map_route_planning',
        ]);

        // Make authenticated request
        $response = $this->actingAs($driver, 'sanctum')
            ->getJson('/api/map/available-filters');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert response structure
        $response->assertJsonStructure([
            'success',
            'user_role',
            'available_filters' => [
                '*' => ['id', 'name', 'enabled', 'description']
            ]
        ]);

        // Assert success
        $response->assertJsonPath('success', true);

        // Assert user_role is driver
        $response->assertJsonPath('user_role', 'driver');

        // Assert 4 filters are present (not including 'drivers')
        $response->assertJsonCount(4, 'available_filters');

        // Verify filter IDs (should not include 'drivers' since permission not assigned)
        $filterIds = collect($response->json('available_filters'))->pluck('id')->toArray();
        $this->assertContains('location', $filterIds);
        $this->assertContains('routes', $filterIds);
        $this->assertContains('available_commuters', $filterIds);
        $this->assertContains('route_planning', $filterIds);
        $this->assertNotContains('drivers', $filterIds);
    }

    public function test_get_available_filters_role_without_permissions_returns_empty()
    {
        // Create a new role without permissions
        $limitedRole = \App\Models\Role::create(['name' => 'limited_viewer']);

        // Create and authenticate user with limited role
        $user = \App\Models\User::factory()->create();
        $user->roles()->attach($limitedRole);

        // Make authenticated request
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/map/available-filters');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert success
        $response->assertJsonPath('success', true);

        // Assert no filters available
        $response->assertJsonCount(0, 'available_filters');
    }

    public function test_get_available_filters_user_without_role_returns_403()
    {
        // Create user without any roles
        $userNoRole = \App\Models\User::factory()->create();

        // Make authenticated request
        $response = $this->actingAs($userNoRole, 'sanctum')
            ->getJson('/api/map/available-filters');

        // Assert 403 Forbidden
        $response->assertStatus(403);

        // Assert error message
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'User has no role assigned');
    }

    public function test_get_available_filters_response_has_all_filter_fields()
    {
        // Create SuperAdmin to see all filters
        $superAdmin = \App\Models\User::factory()->create();
        $superAdminRole = $this->getSuperAdminRoleId();
        $superAdmin->roles()->attach($superAdminRole);

        // Make request
        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/map/available-filters');

        // Assert 200 OK
        $response->assertStatus(200);

        // Get filters
        $filters = $response->json('available_filters');
        $this->assertCount(5, $filters);

        // Verify each filter has required fields
        foreach ($filters as $filter) {
            $this->assertArrayHasKey('id', $filter);
            $this->assertArrayHasKey('name', $filter);
            $this->assertArrayHasKey('enabled', $filter);
            $this->assertArrayHasKey('description', $filter);

            // Verify field types
            $this->assertIsString($filter['id']);
            $this->assertIsString($filter['name']);
            $this->assertIsBool($filter['enabled']);
            $this->assertIsString($filter['description']);

            // Verify enabled is always false
            $this->assertFalse($filter['enabled']);

            // Verify non-empty strings
            $this->assertNotEmpty($filter['id']);
            $this->assertNotEmpty($filter['name']);
            $this->assertNotEmpty($filter['description']);
        }
    }

    /**
     * Helper methods
     */

    private function getSuperAdminRoleId(): string
    {
        $superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
        if (!$superAdminRole) {
            $superAdminRole = \App\Models\Role::create([
                'name' => 'super_admin',
                'is_reserved' => true,
            ]);
        }
        return $superAdminRole->id;
    }

    private function getDriverRoleId(): string
    {
        $driverRole = \App\Models\Role::where('name', 'driver')->first();
        if (!$driverRole) {
            $driverRole = \App\Models\Role::create(['name' => 'driver']);
        }
        return $driverRole->id;
    }

    private function attachPermissionsToRole(string $roleId, array $permissionNames): void
    {
        $role = \App\Models\Role::find($roleId);

        foreach ($permissionNames as $permissionName) {
            $permission = \App\Models\Permission::where('name', $permissionName)->first();
            if (!$permission) {
                $permission = \App\Models\Permission::create([
                    'name' => $permissionName,
                    'display_name' => ucfirst(str_replace('_', ' ', $permissionName)),
                ]);
            }
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
    }
}
