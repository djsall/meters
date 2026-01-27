<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reading>
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
