<?php

namespace Database\Factories;

use App\Models\Reading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reading>
 */
class ReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeThisYear(),
            'value' => fake()->randomFloat(),
        ];
    }
}
