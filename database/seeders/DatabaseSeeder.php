<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo account for Postman / the playground page (password: "password").
        // firstOrCreate looks the email up first, so re-running the seeder never hits the unique index.
        User::firstOrCreate(
            ['email' => 'demo@promptlab.test'],
            ['name' => 'Demo Kelompok 1', 'password' => 'password', 'email_verified_at' => now()],
        );
    }
}
