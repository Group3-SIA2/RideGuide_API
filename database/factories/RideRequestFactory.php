<?php

namespace Database\Factories;

use App\Models\RideRequest;
use App\Models\User;
use App\Models\CommuterRideRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class RideRequestFactory extends Factory
{
    protected $model = RideRequest::class;

    public function definition(): array
    {
        return [
            'driver_id' => User::factory(),
            'commuter_ride_request_id' => CommuterRideRequest::factory(),
            'status' => 'pending',
            'responded_at' => now(),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
