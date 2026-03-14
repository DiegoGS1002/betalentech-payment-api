<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}
