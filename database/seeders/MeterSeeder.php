<?php

namespace Database\Seeders;

use App\Models\Meter;
use Illuminate\Database\Seeder;

class MeterSeeder extends Seeder
{
    public function run(): void
    {
        Meter::factory(5)->create();
    }
}
