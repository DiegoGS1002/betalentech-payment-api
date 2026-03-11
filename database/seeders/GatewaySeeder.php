<?php

namespace Database\Seeders;

use App\Models\Gateway;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Gateway::create([
            'name' => 'gateway1',
            'priority' => 1,
            'is_active' => true
        ]);

        Gateway::create([
            'name' => 'gateway2',
            'priority' => 2,
            'is_active' => true
        ]);
    }
}
