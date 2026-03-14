<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'gateway_id' => Gateway::factory(),
            'external_id' => $this->faker->uuid(),
            'status' => $this->faker->randomElement(['approved', 'refunded', 'failed']),
            'amount' => $this->faker->numberBetween(100, 100000),
            'card_last_numbers' => $this->faker->numerify('####'),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
        ]);
    }
}
