<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'role' => 'admin',
            'username' => 'BDS-2026-0000',
            'email' => 'admin@brighterday.test',
            'password_hash' => Hash::make('password123'),
            'must_change_password' => true,
        ]);
    }
}
