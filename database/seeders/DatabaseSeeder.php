<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(state: [
            'name' => 'Admin',
            'email' => 'proksalevente@gmail.com',
            'role' => 'admin',
            'password' => Hash::make('msir6670'),
        ])
            ->create();

        Meter::factory(10)->create();

        foreach (Meter::inRandomOrder()->get() as $meter) {
            for ($i = 0; $i < 12; $i++) {
                $meter->readings()->create([
                    'date' => Reading::query()
                        ->where('meter_id', $meter->id)
                        ->orderByDesc('date')
                        ->first()?->date->addMonth() ?? today()->startOfYear(),
                    'value' => Reading::factory()->make()->value,
                ]);
            }
        }
    }
}
