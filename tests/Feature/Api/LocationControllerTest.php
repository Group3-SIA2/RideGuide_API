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
}
