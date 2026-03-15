<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@betalent.tech'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'role' => 'user',
            ]
        );

        // Additional default roles to ease local/demo testing
        User::firstOrCreate(
            ['email' => 'manager@betalent.tech'],
            [
                'name' => 'Manager',
                'password' => bcrypt('password'),
                'role' => 'manager',
            ]
        );

        User::firstOrCreate(
            ['email' => 'finance@betalent.tech'],
            [
                'name' => 'Finance',
                'password' => bcrypt('password'),
                'role' => 'finance',
            ]
        );

        Product::firstOrCreate(['name' => 'Product A'], ['amount' => 1000]);
        Product::firstOrCreate(['name' => 'Product B'], ['amount' => 2500]);
        Product::firstOrCreate(['name' => 'Product C'], ['amount' => 5000]);

        $this->call([
            GatewaySeeder::class,
        ]);
    }
}
