<?php

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionProduct;
use App\Models\User;

beforeEach(function () {
    // Create users
    User::factory()->create(['email' => 'admin@test.com', 'role' => 'admin']);
    User::factory()->create(['email' => 'user@test.com', 'role' => 'user']);

    // Create gateway
    Gateway::factory()->create([
        'id' => 1,
        'name' => 'gateway1',
        'is_active' => true,
        'priority' => 1,
    ]);

    // Create products
    Product::factory()->create(['id' => 1, 'name' => 'Product A', 'amount' => 1000]);
    Product::factory()->create(['id' => 2, 'name' => 'Product B', 'amount' => 2500]);

    // Create clients
    Client::factory()->create([
        'id' => 1,
        'name' => 'Client One',
        'email' => 'client1@test.com',
    ]);

    Client::factory()->create([
        'id' => 2,
        'name' => 'Client Two',
        'email' => 'client2@test.com',
    ]);

    // Create transactions
    $transaction1 = Transaction::factory()->create([
        'id' => 1,
        'client_id' => 1,
        'gateway_id' => 1,
        'external_id' => 'ext-001',
        'status' => 'approved',
        'amount' => 1000,
        'card_last_numbers' => '6063',
    ]);

    $transaction2 = Transaction::factory()->create([
        'id' => 2,
        'client_id' => 1,
        'gateway_id' => 1,
        'external_id' => 'ext-002',
        'status' => 'approved',
        'amount' => 2500,
        'card_last_numbers' => '1234',
    ]);

    $transaction3 = Transaction::factory()->create([
        'id' => 3,
        'client_id' => 2,
        'gateway_id' => 1,
        'external_id' => 'ext-003',
        'status' => 'refunded',
        'amount' => 5000,
        'card_last_numbers' => '5678',
    ]);

    // Create transaction products
    TransactionProduct::factory()->create([
        'transaction_id' => 1,
        'product_id' => 1,
        'quantity' => 1,
    ]);

    TransactionProduct::factory()->create([
        'transaction_id' => 2,
        'product_id' => 2,
        'quantity' => 1,
    ]);

    TransactionProduct::factory()->create([
        'transaction_id' => 3,
        'product_id' => 1,
        'quantity' => 2,
    ]);

    TransactionProduct::factory()->create([
        'transaction_id' => 3,
        'product_id' => 2,
        'quantity' => 1,
    ]);
});

function authenticateUser(string $role = 'user'): string
{
    $user = User::where('role', $role)->first();
    return $user->createToken('test')->plainTextToken;
}

describe('Client Listing', function () {
    it('authenticated user can list all clients', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'email'],
            ]);
    });

    it('admin can list all clients', function () {
        $token = authenticateUser('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    });

    it('unauthenticated user cannot list clients', function () {
        $response = $this->getJson('/api/clients');

        $response->assertStatus(401);
    });
});

describe('Client Details', function () {
    it('authenticated user can view client details', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients/1');

        $response->assertStatus(200)
            ->assertJson([
                'id' => 1,
                'name' => 'Client One',
                'email' => 'client1@test.com',
            ])
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'transactions' => [
                    '*' => [
                        'id',
                        'status',
                        'amount',
                        'gateway',
                        'transaction_products',
                    ],
                ],
            ]);
    });

    it('client details include all purchases', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients/1');

        $response->assertStatus(200);

        $data = $response->json();
        expect($data['transactions'])->toHaveCount(2);
    });

    it('client details include transaction products', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients/2');

        $response->assertStatus(200);

        $data = $response->json();
        $transaction = $data['transactions'][0];
        expect($transaction['transaction_products'])->toHaveCount(2);
    });

    it('returns 404 for non-existent client', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients/999');

        $response->assertStatus(404);
    });

    it('unauthenticated user cannot view client details', function () {
        $response = $this->getJson('/api/clients/1');

        $response->assertStatus(401);
    });
});

describe('Transaction Listing', function () {
    it('authenticated user can list all transactions', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'status',
                    'amount',
                    'card_last_numbers',
                    'external_id',
                    'client',
                    'gateway',
                ],
            ]);
    });

    it('transaction list includes client info', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/transactions');

        $response->assertStatus(200);

        $data = $response->json();
        expect($data[0]['client'])->not->toBeNull();
        expect($data[0]['client']['name'])->not->toBeEmpty();
    });

    it('transaction list includes gateway info', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/transactions');

        $response->assertStatus(200);

        $data = $response->json();
        expect($data[0]['gateway'])->not->toBeNull();
        expect($data[0]['gateway']['name'])->not->toBeEmpty();
    });

    it('unauthenticated user cannot list transactions', function () {
        $response = $this->getJson('/api/transactions');

        $response->assertStatus(401);
    });
});

describe('Transaction Details', function () {
    it('authenticated user can view transaction details', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/transactions/1');

        $response->assertStatus(200)
            ->assertJson([
                'id' => 1,
                'status' => 'approved',
                'amount' => 1000,
                'card_last_numbers' => '6063',
            ])
            ->assertJsonStructure([
                'id',
                'client_id',
                'gateway_id',
                'external_id',
                'status',
                'amount',
                'card_last_numbers',
                'client',
                'gateway',
                'transaction_products' => [
                    '*' => [
                        'id',
                        'transaction_id',
                        'product_id',
                        'quantity',
                        'product',
                    ],
                ],
            ]);
    });

    it('transaction details include product information', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/transactions/3');

        $response->assertStatus(200);

        $data = $response->json();
        expect($data['transaction_products'])->toHaveCount(2);
        expect($data['transaction_products'][0]['product']['name'])->not->toBeEmpty();
    });

    it('returns 404 for non-existent transaction', function () {
        $token = authenticateUser('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/transactions/999');

        $response->assertStatus(404);
    });

    it('unauthenticated user cannot view transaction details', function () {
        $response = $this->getJson('/api/transactions/1');

        $response->assertStatus(401);
    });
});
