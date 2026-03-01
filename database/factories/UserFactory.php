<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name'        => fake()->firstName(),
            'last_name'         => fake()->lastName(),
            'middle_name'       => fake()->optional()->firstName(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role_id'           => Role::inRandomOrder()->first()?->id ?? Role::factory(),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign admin role to the user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('name', Role::ADMIN)->first()?->id,
        ]);
    }

    /**
     * Assign super_admin role to the user.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('name', Role::SUPER_ADMIN)->first()?->id,
        ]);
    }

    /**
     * Assign driver role to the user.
     */
    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('name', Role::DRIVER)->first()?->id,
        ]);
    }

    /**
     * Assign commuter role to the user.
     */
    public function commuter(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('name', Role::COMMUTER)->first()?->id,
        ]);
    }
}
