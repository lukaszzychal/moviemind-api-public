<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user if not exists
        if (! User::where('email', 'admin@moviemind.local')->exists()) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@moviemind.local',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);
        }
    }
}
