<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
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
            'national_code' => (string) fake()->unique()->numerify('##########'),
            'username' => function (array $attributes) {
                return $attributes['national_code'];
            },
            'name' => fake()->name(),
            'mobile' => (string) fake()->numerify('09#########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     * (Removed since email_verified_at is not in the schema anymore)
     */
}
