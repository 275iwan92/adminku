<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_DATA_ENTRY,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function administrator(): static
    {
        return $this->state(['role' => User::ROLE_ADMINISTRATOR]);
    }

    public function adminData(): static
    {
        return $this->state(['role' => User::ROLE_ADMIN_DATA]);
    }

    public function supervisor(): static
    {
        return $this->state(['role' => User::ROLE_SUPERVISOR]);
    }
}
