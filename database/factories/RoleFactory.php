<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $roleNames = ['driver', 'commuter', 'admin', 'super_admin'];

        return [
            'name' => $this->faker->randomElement($roleNames),
            'description' => $this->faker->sentence(),
            'is_reserved' => true,
        ];
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'driver',
        ]);
    }

    public function commuter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'commuter',
        ]);
    }
}
