<?php

namespace Database\Factories;

use App\Models\DriverLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverLocationFactory extends Factory
{
    protected $model = DriverLocation::class;

    public function definition(): array
    {
        return [
            'driver_id' => User::factory(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'heading' => $this->faker->numberBetween(0, 360),
            'accuracy' => $this->faker->randomFloat(2, 0, 50),
            'updated_at' => now(),
        ];
    }

    public function withoutHeading(): static
    {
        return $this->state(fn (array $attributes) => [
            'heading' => null,
        ]);
    }

    public function withoutAccuracy(): static
    {
        return $this->state(fn (array $attributes) => [
            'accuracy' => null,
        ]);
    }
}
