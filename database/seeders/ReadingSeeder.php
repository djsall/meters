<?php

namespace Database\Seeders;

use App\Models\Meter;
use Illuminate\Database\Seeder;

class ReadingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Meter::all() as $meter) {
            for ($i = 1; $i <= 12; $i++) {

                $meter->readings()->create([
                    'date' => today()->month($i)->startOfMonth(),
                    'value' => $i * rand(125, 150),
                ]);
            }
        }
    }
}
