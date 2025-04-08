<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::factory(state: [
            'name' => 'Admin',
            'email' => 'proksalevente@gmail.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ])
            ->create();
    }
}
