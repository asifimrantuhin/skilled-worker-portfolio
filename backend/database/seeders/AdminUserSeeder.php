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
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@travelagency.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Test Agent',
            'email' => 'agent@travelagency.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'commission_rate' => 10.00,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Test Customer',
            'email' => 'customer@travelagency.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'is_active' => true,
        ]);
    }
}

