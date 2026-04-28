<?php

namespace Database\Factories;

use App\Models\CommuterRideRequest;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommuterRideRequestFactory extends Factory
{
    protected $model = CommuterRideRequest::class;

    public function definition(): array
    {
        return [
            'commuter_id' => User::factory(),
            'route_id' => null,
            'terminal_id' => Terminal::factory(),
            'destination' => $this->faker->address(),
            'status' => 'active',
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
