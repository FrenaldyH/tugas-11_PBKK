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
        // Demo account for Postman / the playground page (password: "password")
        User::factory()->create([
            'name' => 'Demo Kelompok 1',
            'email' => 'demo@agentchat.test',
            'password' => 'password',
        ]);
    }
}
