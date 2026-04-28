<?php

namespace Database\Factories;

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
            'remember_token'    => Str::random(10),
            'status'            => 'active',
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
     * Assign driver role to the user.
     */
    public function driver(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $driverRole = \App\Models\Role::where('name', 'driver')->first();
            if (!$driverRole) {
                $driverRole = \App\Models\Role::create(['name' => 'driver']);
            }
            $user->roles()->attach($driverRole->id);
        });
    }

    /**
     * Assign commuter role to the user.
     */
    public function commuter(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $commuterRole = \App\Models\Role::where('name', 'commuter')->first();
            if (!$commuterRole) {
                $commuterRole = \App\Models\Role::create(['name' => 'commuter']);
            }
            $user->roles()->attach($commuterRole->id);
        });
    }

    /**
     * Assign admin role to the user.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $adminRole = \App\Models\Role::where('name', 'admin')->first();
            if (!$adminRole) {
                $adminRole = \App\Models\Role::create(['name' => 'admin']);
            }
            $user->roles()->attach($adminRole->id);
        });
    }

    /**
     * Assign super_admin role to the user.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            $superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
            if (!$superAdminRole) {
                $superAdminRole = \App\Models\Role::create(['name' => 'super_admin']);
            }
            $user->roles()->attach($superAdminRole->id);
        });
    }
}

