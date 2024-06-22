<?php

namespace Database\Factories;

use App\Models\Reading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reading>
 */
class ReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = Reading::query()
            ->select('value')
            ->orderByDesc('value')
            ->first()
            ?->value ?? 0;

        return [
            'value' => rand($min, $min + 10000),
            'date' => Reading::latest()->first()?->date->addMonth() ?? today()->startOfYear(),
        ];
    }
}
