<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GatewayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['gateway1', 'gateway2']),
            'is_active' => true,
            'priority' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
