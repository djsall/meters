<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Meter;
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
            ->has(
                Meter::factory(5)
                    ->hasReadings(8)
            )
            ->create();

        User::factory(3)
            ->has(
                Meter::factory(5)
                    ->hasReadings(8)
            )
            ->create();
    }
}
