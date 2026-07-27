<?php

namespace Database\Factories;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meter>
 */
class MeterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::first() ?? User::factory(),
            'type' => fake()->randomElement(MeterType::cases()),
            'name' => fake()->streetName,
            'description' => null,
            'settings' => [],
            'shared_users' => [],
        ];
    }
}
