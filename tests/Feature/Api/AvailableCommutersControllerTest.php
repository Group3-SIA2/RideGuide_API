<?php

namespace Tests\Feature\Api;

use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use App\Models\Terminal;
use App\Models\User;
use Database\Factories\UserFactory;
use Tests\TestCase;

class AvailableCommutersControllerTest extends TestCase
{
    /**
     * GET /api/available-commuters
     */

    public function test_driver_sees_active_commuter_requests()
    {
        // Create driver and authenticate
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create commuter with active ride request
        $commuter = User::factory()->create();
        $terminal = Terminal::factory()->create();
        $commuterRequest = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter->id,
            'terminal_id' => $terminal->id,
            'status' => 'active',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Make request from driver with location params
        $response = $this->actingAs($driver, 'sanctum')
            ->getJson('/api/available-commuters?latitude=6.1184&longitude=125.1774');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert response is an array with at least 1 element
        $responseData = $response->json();
        $this->assertIsArray($responseData);
        $this->assertNotEmpty($responseData);

        // Assert correct structure
        $response->assertJsonStructure([
            '*' => [
                'id',
                'current_location',
                'destination',
                'wait_time_seconds',
            ]
        ]);

        // Assert NO personal info (privacy)
        $responseJson = $response->json();
        $this->assertArrayNotHasKey('commuter_name', $responseJson[0]);
        $this->assertArrayNotHasKey('commuter_picture', $responseJson[0]);

        // Assert destination is correct
        $response->assertJsonPath('0.destination', $commuterRequest->destination);
    }

    public function test_driver_request_excludes_expired_requests()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create expired request
        $commuter = User::factory()->create();
        $expiredRequest = CommuterRideRequest::factory()->expired()->create([
            'commuter_id' => $commuter->id,
        ]);

        // Create active request
        $activeCommuter = User::factory()->create();
        $activeRequest = CommuterRideRequest::factory()->active()->create([
            'commuter_id' => $activeCommuter->id,
        ]);

        // Make request from driver
        $response = $this->actingAs($driver, 'sanctum')
            ->getJson('/api/available-commuters?latitude=6.1184&longitude=125.1774');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert only 1 request (active one)
        $response->assertJsonCount(1);

        // Assert active request is returned
        $response->assertJsonPath('0.id', $activeRequest->id);
    }

    public function test_get_available_commuters_requires_latitude_longitude()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Make request WITHOUT latitude/longitude
        $response = $this->actingAs($driver, 'sanctum')
            ->getJson('/api/available-commuters');

        // Assert validation error (422)
        $response->assertStatus(422);
    }

    public function test_get_available_commuters_requires_driver_role()
    {
        // Create commuter (not driver)
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Make request as commuter
        $response = $this->actingAs($commuter)
            ->getJson('/api/available-commuters', [
                'latitude' => 6.1184,
                'longitude' => 125.1774,
            ]);

        // Assert 403 Forbidden
        $response->assertStatus(403);
    }

    public function test_get_available_commuters_excludes_non_active_statuses()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create cancelled request
        $cancelledRequest = CommuterRideRequest::factory()->cancelled()->create();

        // Create active request for comparison
        $activeRequest = CommuterRideRequest::factory()->active()->create();

        // Make request from driver
        $response = $this->actingAs($driver)
            ->getJson('/api/available-commuters', [
                'latitude' => 6.1184,
                'longitude' => 125.1774,
            ]);

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert only 1 (active one)
        $response->assertJsonCount(1);

        // Assert cancelled is NOT in response
        $responseIds = collect($response->json())->pluck('id');
        $this->assertNotContains($cancelledRequest->id, $responseIds);
    }

    /**
     * POST /api/available-commuters/respond
     */

    public function test_driver_accepts_commuter_request()
    {
        // Create driver and authenticate
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create commuter request
        $commuterRequest = CommuterRideRequest::factory()->create();

        // Make POST request to respond
        $response = $this->actingAs($driver)
            ->postJson('/api/available-commuters/respond', [
                'commuter_ride_request_id' => $commuterRequest->id,
                'status' => 'accepted',
            ]);

        // Assert 201 Created
        $response->assertStatus(201);

        // Assert RideRequest record created
        $this->assertDatabaseHas('ride_requests', [
            'driver_id' => $driver->id,
            'commuter_ride_request_id' => $commuterRequest->id,
            'status' => 'accepted',
        ]);

        // Assert response has correct structure
        $response->assertJsonStructure(['id', 'status', 'responded_at']);

        // Assert status is 'accepted'
        $response->assertJsonPath('status', 'accepted');

        // Assert responded_at is set
        $this->assertNotNull($response->json('responded_at'));
    }

    public function test_driver_rejects_commuter_request()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create commuter request
        $commuterRequest = CommuterRideRequest::factory()->create();

        // Make POST request to reject
        $response = $this->actingAs($driver)
            ->postJson('/api/available-commuters/respond', [
                'commuter_ride_request_id' => $commuterRequest->id,
                'status' => 'rejected',
            ]);

        // Assert 201 Created
        $response->assertStatus(201);

        // Assert RideRequest created with status='rejected'
        $this->assertDatabaseHas('ride_requests', [
            'driver_id' => $driver->id,
            'commuter_ride_request_id' => $commuterRequest->id,
            'status' => 'rejected',
        ]);
    }

    public function test_respond_to_commuter_requires_driver_role()
    {
        // Create commuter (not driver)
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create commuter request
        $commuterRequest = CommuterRideRequest::factory()->create();

        // Make POST request as commuter
        $response = $this->actingAs($commuter)
            ->postJson('/api/available-commuters/respond', [
                'commuter_ride_request_id' => $commuterRequest->id,
                'status' => 'accepted',
            ]);

        // Assert 403 Forbidden
        $response->assertStatus(403);
    }

    public function test_respond_validates_commuter_ride_request_id_exists()
    {
        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Use invalid request ID
        $response = $this->actingAs($driver)
            ->postJson('/api/available-commuters/respond', [
                'commuter_ride_request_id' => fake()->uuid(),
                'status' => 'accepted',
            ]);

        // Assert 422 validation error or 404 not found
        $this->assertTrue(
            $response->status() === 422 || $response->status() === 404,
            "Expected 422 or 404, got {$response->status()}"
        );
    }

    /**
     * Helper methods
     */

    private function getDriverRoleId(): string
    {
        // Get or create driver role
        $driverRole = \App\Models\Role::where('name', 'driver')->first();
        if (!$driverRole) {
            $driverRole = \App\Models\Role::create(['name' => 'driver']);
        }
        return $driverRole->id;
    }

    private function getCommuterRoleId(): string
    {
        // Get or create commuter role
        $commuterRole = \App\Models\Role::where('name', 'commuter')->first();
        if (!$commuterRole) {
            $commuterRole = \App\Models\Role::create(['name' => 'commuter']);
        }
        return $commuterRole->id;
    }
}
