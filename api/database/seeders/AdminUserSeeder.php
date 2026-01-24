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
        $email = env('ADMIN_EMAIL', 'admin@moviemind.local');

        // Create admin user if not exists
        if (! User::where('email', $email)->exists()) {
            User::create([
                'name' => env('ADMIN_NAME', 'Admin User'),
                'email' => $email,
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password123')),
                'email_verified_at' => now(),
            ]);
        }
    }
}
