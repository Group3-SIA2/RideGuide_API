<?php

namespace Tests\Feature\Api;

use App\Models\DriverLocation;
use App\Models\User;
use Tests\TestCase;

class DriverLocationControllerTest extends TestCase
{
    /**
     * POST /api/drivers/location
     */

    public function test_driver_updates_location_creates_new()
    {
        // Create and authenticate driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Make POST request to update location
        $response = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/drivers/location', [
                'latitude' => 6.1184,
                'longitude' => 125.1774,
            ]);

        // Assert 200 or 201
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 201,
            "Expected 200 or 201, got {$response->status()}: " . $response->getContent()
        );

        // Assert response has correct structure
        $response->assertJsonStructure([
            'id',
            'driver_id',
            'latitude',
            'longitude',
            'heading',
            'accuracy',
            'updated_at',
        ]);

        // Assert driver_id matches
        $response->assertJsonPath('driver_id', $driver->id);

        // Assert coordinates
        $response->assertJsonPath('latitude', 6.1184);
        $response->assertJsonPath('longitude', 125.1774);

        // Assert record created in DB
        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $driver->id,
            'latitude' => 6.1184,
            'longitude' => 125.1774,
        ]);
    }

    public function test_driver_updates_location_upserts_existing()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create initial location
        DriverLocation::factory()->create([
            'driver_id' => $driver->id,
            'latitude' => 6.1000,
            'longitude' => 125.1000,
        ]);

        // Verify only 1 record
        $this->assertDatabaseCount('driver_locations', 1);
        
        // Get the initial location record
        $initialLocation = DriverLocation::where('driver_id', $driver->id)->first();
        $this->assertNotNull($initialLocation);

        // Update location with new coordinates
        $response = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/drivers/location', [
                'latitude' => 6.2000,
                'longitude' => 125.2000,
            ]);

        // Assert 200 or 201
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 201,
            "Expected 200 or 201, got {$response->status()}: " . $response->getContent()
        );

        // Assert still only 1 record (upserted, not created new)
        $this->assertDatabaseCount('driver_locations', 1);

        // Assert coordinates updated
        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $driver->id,
            'latitude' => 6.2000,
            'longitude' => 125.2000,
        ]);
        
        // Verify the ID is the same (not a new record)
        $updatedLocation = DriverLocation::where('driver_id', $driver->id)->first();
        $this->assertEquals($initialLocation->id, $updatedLocation->id);
    }

    public function test_driver_location_stores_heading_and_accuracy()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Make POST request with heading and accuracy
        $response = $this->actingAs($driver)
            ->postJson('/api/drivers/location', [
                'latitude' => 6.1184,
                'longitude' => 125.1774,
                'heading' => 45,
                'accuracy' => 5.5,
            ]);

        // Assert 200 or 201
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 201,
            "Expected 200 or 201, got {$response->status()}"
        );

        // Assert heading and accuracy in response
        $response->assertJsonPath('heading', 45);
        $response->assertJsonPath('accuracy', 5.5);

        // Assert stored in DB
        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $driver->id,
            'heading' => 45,
            'accuracy' => 5.5,
        ]);
    }

    public function test_driver_location_requires_latitude_longitude_validation()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Make POST request WITHOUT latitude
        $response = $this->actingAs($driver)
            ->postJson('/api/drivers/location', [
                'longitude' => 125.1774,
            ]);

        // Assert validation error (422)
        $response->assertStatus(422);

        // Make POST request WITHOUT longitude
        $response = $this->actingAs($driver)
            ->postJson('/api/drivers/location', [
                'latitude' => 6.1184,
            ]);

        // Assert validation error (422)
        $response->assertStatus(422);

        // Make POST request with invalid latitude
        $response = $this->actingAs($driver)
            ->postJson('/api/drivers/location', [
                'latitude' => 91, // Out of range (-90 to 90)
                'longitude' => 125.1774,
            ]);

        // Assert validation error (422)
        $response->assertStatus(422);
    }

    public function test_driver_location_requires_driver_role()
    {
        // Create commuter (not driver)
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Make POST request as commuter
        $response = $this->actingAs($commuter)
            ->postJson('/api/drivers/location', [
                'latitude' => 6.1184,
                'longitude' => 125.1774,
            ]);

        // Assert 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * GET /api/drivers/location
     */

    public function test_driver_gets_current_location()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create driver location
        $driverLocation = DriverLocation::factory()->create([
            'driver_id' => $driver->id,
            'latitude' => 6.1184,
            'longitude' => 125.1774,
            'heading' => 45,
            'accuracy' => 5.5,
        ]);

        // Make GET request
        $response = $this->actingAs($driver)
            ->getJson('/api/drivers/location');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert response has correct structure
        $response->assertJsonStructure([
            'id',
            'driver_id',
            'latitude',
            'longitude',
            'heading',
            'accuracy',
            'updated_at',
        ]);

        // Assert correct values
        $response->assertJsonPath('driver_id', $driver->id);
        $response->assertJsonPath('latitude', 6.1184);
        $response->assertJsonPath('longitude', 125.1774);
        $response->assertJsonPath('heading', 45);
        $response->assertJsonPath('accuracy', 5.5);
    }

    public function test_driver_get_location_returns_404_if_no_location_set()
    {
        // Create driver (without any location set)
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Make GET request
        $response = $this->actingAs($driver)
            ->getJson('/api/drivers/location');

        // Assert 404 Not Found
        $response->assertStatus(404);

        // Assert error message
        $response->assertJsonPath('error', 'Location not found. Please update your location first.');
    }

    public function test_driver_get_location_requires_driver_role()
    {
        // Create commuter (not driver)
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Make GET request
        $response = $this->actingAs($commuter)
            ->getJson('/api/drivers/location');

        // Assert 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * Helper methods
     */

    private function getDriverRoleId(): string
    {
        $driverRole = \App\Models\Role::where('name', 'driver')->first();
        if (!$driverRole) {
            $driverRole = \App\Models\Role::create(['name' => 'driver']);
        }
        return $driverRole->id;
    }

    private function getCommuterRoleId(): string
    {
        $commuterRole = \App\Models\Role::where('name', 'commuter')->first();
        if (!$commuterRole) {
            $commuterRole = \App\Models\Role::create(['name' => 'commuter']);
        }
        return $commuterRole->id;
    }
}
