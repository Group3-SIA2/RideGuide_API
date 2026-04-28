<?php

namespace Tests\Feature\Api;

use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use App\Models\Terminal;
use App\Models\User;
use Tests\TestCase;

class RideRequestControllerTest extends TestCase
{
    /**
     * POST /api/commuter/ride-requests
     */

    public function test_commuter_creates_ride_request()
    {
        // Create and authenticate commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create a terminal
        $terminal = Terminal::factory()->create();

        // Make POST request to create ride request
        $response = $this->actingAs($commuter)
            ->postJson('/api/commuter/ride-requests', [
                'destination' => '123 Main St, General Santos City',
                'terminal_id' => $terminal->id,
            ]);

        // Assert 201 Created
        $response->assertStatus(201);

        // Assert response has correct structure
        $response->assertJsonStructure([
            'id',
            'commuter_id',
            'destination',
            'status',
            'expires_at',
        ]);

        // Assert commuter_id matches
        $response->assertJsonPath('commuter_id', $commuter->id);

        // Assert status is 'active'
        $response->assertJsonPath('status', 'active');

        // Assert expires_at is approximately 10 minutes from now
        $expiresAt = strtotime($response->json('expires_at'));
        $expectedTime = now()->addMinutes(10)->timestamp;
        $this->assertLessThan(2, abs($expiresAt - $expectedTime), 'expires_at should be ~10 minutes from now');

        // Assert record created in DB
        $this->assertDatabaseHas('commuter_ride_requests', [
            'commuter_id' => $commuter->id,
            'destination' => '123 Main St, General Santos City',
            'status' => 'active',
        ]);
    }

    public function test_commuter_enforces_one_active_request_per_commuter()
    {
        // Create commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create first active request
        $firstRequest = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter->id,
            'status' => 'active',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Try to create second request
        $response = $this->actingAs($commuter)
            ->postJson('/api/commuter/ride-requests', [
                'destination' => 'Another destination',
            ]);

        // Assert 409 Conflict
        $response->assertStatus(409);

        // Assert error message
        $response->assertJsonPath('error', 'You already have an active ride request.');

        // Assert existing_request_id in response
        $response->assertJsonPath('existing_request_id', $firstRequest->id);
    }

    public function test_create_ride_request_requires_destination_or_route_or_terminal()
    {
        // Create commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Try to create request WITHOUT destination, route, or terminal
        $response = $this->actingAs($commuter)
            ->postJson('/api/commuter/ride-requests', []);

        // Assert validation error (422)
        $response->assertStatus(422);
    }

    public function test_create_ride_request_requires_authentication()
    {
        // Make request WITHOUT authentication
        $response = $this->postJson('/api/commuter/ride-requests', [
            'destination' => 'Test destination',
        ]);

        // Assert 401 Unauthorized
        $response->assertStatus(401);
    }

    /**
     * GET /api/commuter/ride-requests
     */

    public function test_commuter_lists_their_active_requests()
    {
        // Create commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create another commuter for comparison
        $otherCommuter = User::factory()->create();

        // Create 2 active requests for authenticated commuter
        $request1 = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter->id,
            'status' => 'active',
        ]);
        $request2 = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter->id,
            'status' => 'active',
        ]);

        // Create request for other commuter (should not be returned)
        $otherRequest = CommuterRideRequest::factory()->create([
            'commuter_id' => $otherCommuter->id,
            'status' => 'active',
        ]);

        // Make GET request
        $response = $this->actingAs($commuter)
            ->getJson('/api/commuter/ride-requests');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert 2 requests returned
        $response->assertJsonCount(2);

        // Assert correct structure
        $response->assertJsonStructure([
            '*' => [
                'id',
                'destination',
                'status',
                'expires_at',
                'driver_responses',
            ]
        ]);

        // Assert only authenticated commuter's requests
        $returnedIds = collect($response->json())->pluck('id');
        $this->assertContains($request1->id, $returnedIds);
        $this->assertContains($request2->id, $returnedIds);
        $this->assertNotContains($otherRequest->id, $returnedIds);
    }

    public function test_list_ride_requests_excludes_expired_requests()
    {
        // Create commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create expired request
        $expiredRequest = CommuterRideRequest::factory()->expired()->create([
            'commuter_id' => $commuter->id,
        ]);

        // Create active request
        $activeRequest = CommuterRideRequest::factory()->active()->create([
            'commuter_id' => $commuter->id,
        ]);

        // Make GET request
        $response = $this->actingAs($commuter)
            ->getJson('/api/commuter/ride-requests');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert only 1 request (active one)
        $response->assertJsonCount(1);

        // Assert it's the active request
        $response->assertJsonPath('0.id', $activeRequest->id);
    }

    public function test_list_ride_requests_includes_driver_responses()
    {
        // Create commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create commuter request
        $commuterRequest = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter->id,
        ]);

        // Create driver response
        $driverResponse = RideRequest::factory()->create([
            'driver_id' => $driver->id,
            'commuter_ride_request_id' => $commuterRequest->id,
            'status' => 'accepted',
        ]);

        // Make GET request
        $response = $this->actingAs($commuter)
            ->getJson('/api/commuter/ride-requests');

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert 1 request
        $response->assertJsonCount(1);

        // Assert driver_responses array includes the response
        $response->assertJsonPath('0.driver_responses.0.driver_id', $driver->id);
        $response->assertJsonPath('0.driver_responses.0.status', 'accepted');
        $this->assertNotNull($response->json('0.driver_responses.0.responded_at'));
    }

    /**
     * PUT /api/commuter/ride-requests/{id}
     */

    public function test_commuter_accepts_driver_response()
    {
        // Create commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create commuter request
        $commuterRequest = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter->id,
            'status' => 'active',
        ]);

        // Create driver response (pending)
        $driverResponse = RideRequest::factory()->create([
            'driver_id' => $driver->id,
            'commuter_ride_request_id' => $commuterRequest->id,
            'status' => 'pending',
        ]);

        // Make PUT request to accept
        $response = $this->actingAs($commuter)
            ->putJson("/api/commuter/ride-requests/{$commuterRequest->id}", [
                'status' => 'accepted',
            ]);

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert commuter request status updated
        $this->assertDatabaseHas('commuter_ride_requests', [
            'id' => $commuterRequest->id,
            'status' => 'accepted',
        ]);

        // Assert response has correct structure
        $response->assertJsonStructure(['id', 'status', 'responded_at']);
    }

    public function test_commuter_rejects_driver_response()
    {
        // Create commuter
        $commuter = User::factory()->create();
        $commuter->roles()->attach($this->getCommuterRoleId());

        // Create driver
        $driver = User::factory()->create();
        $driver->roles()->attach($this->getDriverRoleId());

        // Create commuter request
        $commuterRequest = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter->id,
            'status' => 'active',
        ]);

        // Create driver response
        $driverResponse = RideRequest::factory()->create([
            'driver_id' => $driver->id,
            'commuter_ride_request_id' => $commuterRequest->id,
            'status' => 'pending',
        ]);

        // Make PUT request to reject
        $response = $this->actingAs($commuter)
            ->putJson("/api/commuter/ride-requests/{$commuterRequest->id}", [
                'status' => 'rejected',
            ]);

        // Assert 200 OK
        $response->assertStatus(200);

        // Assert commuter request status updated to rejected
        $this->assertDatabaseHas('commuter_ride_requests', [
            'id' => $commuterRequest->id,
            'status' => 'rejected',
        ]);
    }

    public function test_update_ride_request_verifies_ownership()
    {
        // Create two commuters
        $commuter1 = User::factory()->create();
        $commuter1->roles()->attach($this->getCommuterRoleId());

        $commuter2 = User::factory()->create();
        $commuter2->roles()->attach($this->getCommuterRoleId());

        // Create request as commuter1
        $commuterRequest = CommuterRideRequest::factory()->create([
            'commuter_id' => $commuter1->id,
        ]);

        // Try to update as commuter2
        $response = $this->actingAs($commuter2)
            ->putJson("/api/commuter/ride-requests/{$commuterRequest->id}", [
                'status' => 'accepted',
            ]);

        // Assert 403 or 404 (ownership check)
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 404,
            "Expected 403 or 404, got {$response->status()}"
        );
    }

    /**
     * Helper methods
     */

    private function getCommuterRoleId(): string
    {
        $commuterRole = \App\Models\Role::where('name', 'commuter')->first();
        if (!$commuterRole) {
            $commuterRole = \App\Models\Role::create(['name' => 'commuter']);
        }
        return $commuterRole->id;
    }

    private function getDriverRoleId(): string
    {
        $driverRole = \App\Models\Role::where('name', 'driver')->first();
        if (!$driverRole) {
            $driverRole = \App\Models\Role::create(['name' => 'driver']);
        }
        return $driverRole->id;
    }
}
