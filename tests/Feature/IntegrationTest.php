<?php

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Testes de integração do fluxo completo de pagamento
 * Desde a criação até o reembolso
 */

beforeEach(function () {
    // Create users
    User::factory()->create([
        'email' => 'admin@betalent.tech',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);

    User::factory()->create([
        'email' => 'finance@betalent.tech',
        'password' => bcrypt('password'),
        'role' => 'finance',
    ]);

    // Create gateways
    Gateway::factory()->create([
        'name' => 'gateway1',
        'is_active' => true,
        'priority' => 1,
    ]);

    Gateway::factory()->create([
        'name' => 'gateway2',
        'is_active' => true,
        'priority' => 2,
    ]);

    // Create products with explicit IDs for testing
    $this->productA = Product::factory()->create(['name' => 'Produto A', 'amount' => 10000]); // R$ 100,00
    $this->productB = Product::factory()->create(['name' => 'Produto B', 'amount' => 25000]); // R$ 250,00
    $this->productC = Product::factory()->create(['name' => 'Produto C', 'amount' => 50000]); // R$ 500,00
});

describe('Complete Payment Flow Integration', function () {
    it('completes full payment and refund flow', function () {
        // Step 1: Make a payment
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response([
                'id' => 'gw1-transaction-123',
                'status' => 'approved',
            ], 200),
            '*/transactions/gw1-transaction-123/charge_back' => Http::response([
                'status' => 'refunded',
            ], 200),
        ]);

        $paymentResponse = $this->postJson('/api/payments', [
            'name' => 'João Silva',
            'email' => 'joao@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [
                ['product_id' => $this->productA->id, 'quantity' => 2], // 10000 * 2 = 20000
                ['product_id' => $this->productB->id, 'quantity' => 1], // 25000 * 1 = 25000
            ]
        ]);

        $paymentResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'amount' => 45000, // R$ 450,00
                'gateway' => 'gateway1',
            ]);

        $transactionId = $paymentResponse->json('transaction_id');

        // Step 2: Verify transaction exists
        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        $transactionResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/transactions/{$transactionId}");

        $transactionResponse->assertStatus(200)
            ->assertJson([
                'status' => 'approved',
                'amount' => 45000,
            ]);

        // Step 3: Verify client was created
        $clientResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients');

        $clientResponse->assertStatus(200);
        expect($clientResponse->json())->toHaveCount(1);
        expect($clientResponse->json()[0]['email'])->toBe('joao@email.com');

        // Step 4: Refund the transaction
        $refundResponse = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transactionId}/refund");

        $refundResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Step 5: Verify transaction is refunded
        $transaction = Transaction::find($transactionId);
        expect($transaction->status)->toBe('refunded');
    });

    it('handles multiple purchases from same client', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response([
                'id' => 'gw1-multi-purchase',
                'status' => 'approved',
            ], 200),
        ]);

        // First purchase
        $this->postJson('/api/payments', [
            'name' => 'Maria Santos',
            'email' => 'maria@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => $this->productA->id, 'quantity' => 1]]
        ])->assertStatus(201);

        // Second purchase
        $this->postJson('/api/payments', [
            'name' => 'Maria Santos',
            'email' => 'maria@email.com',
            'cardNumber' => '4111111111111111',
            'cvv' => '456',
            'products' => [['product_id' => $this->productB->id, 'quantity' => 2]]
        ])->assertStatus(201);

        // Third purchase
        $this->postJson('/api/payments', [
            'name' => 'Maria Santos',
            'email' => 'maria@email.com',
            'cardNumber' => '4000000000000002',
            'cvv' => '789',
            'products' => [['product_id' => $this->productC->id, 'quantity' => 1]]
        ])->assertStatus(201);

        // Verify single client with multiple transactions
        expect(Client::count())->toBe(1);
        expect(Transaction::count())->toBe(3);

        $client = Client::where('email', 'maria@email.com')->first();
        expect($client->transactions)->toHaveCount(3);

        // Verify total spent
        $totalSpent = $client->transactions->sum('amount');
        // 10000 + (25000 * 2) + 50000 = 110000
        expect($totalSpent)->toBe(110000);
    });

    it('login and access protected routes flow', function () {
        // Step 1: Login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'admin@betalent.tech',
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);

        $token = $loginResponse->json('token');

        // Step 2: Access protected route
        $productsResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/products');

        $productsResponse->assertStatus(200);

        // Step 3: Create a product
        $createResponse = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'name' => 'Novo Produto',
                'amount' => 15000,
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'name' => 'Novo Produto',
                'amount' => 15000,
            ]);

        // Step 4: Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/logout');

        $logoutResponse->assertStatus(200);

        // Step 5: Verify token count decreased (token was deleted)
        $user = User::where('email', 'admin@betalent.tech')->first();
        expect($user->tokens()->count())->toBe(0);
    });

    it('gateway priority change affects payment routing', function () {
        Http::fake([
            '*/transacoes' => Http::response([
                'id' => 'gw2-priority-test',
                'status' => 'approved',
            ], 200),
        ]);

        // Login as admin
        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        // Change gateway2 to highest priority
        $gateway2 = Gateway::where('name', 'gateway2')->first();
        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/gateways/{$gateway2->id}/priority", ['priority' => 1]);

        // Change gateway1 to lower priority
        $gateway1 = Gateway::where('name', 'gateway1')->first();
        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/gateways/{$gateway1->id}/priority", ['priority' => 2]);

        // Make payment - should use gateway2 now
        $response = $this->postJson('/api/payments', [
            'name' => 'Test Priority',
            'email' => 'priority@test.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => $this->productA->id, 'quantity' => 1]]
        ]);

        $response->assertStatus(201)
            ->assertJson(['gateway' => 'gateway2']);
    });

    it('deactivating gateway removes it from payment flow', function () {
        Http::fake([
            '*/transacoes' => Http::response([
                'id' => 'gw2-only',
                'status' => 'approved',
            ], 200),
        ]);

        // Login as admin
        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        // Deactivate gateway1
        $gateway1 = Gateway::where('name', 'gateway1')->first();
        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/gateways/{$gateway1->id}/deactivate");

        // Make payment - should use gateway2 only
        $response = $this->postJson('/api/payments', [
            'name' => 'Test Deactivation',
            'email' => 'deactivate@test.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => $this->productA->id, 'quantity' => 1]]
        ]);

        $response->assertStatus(201)
            ->assertJson(['gateway' => 'gateway2']);
    });
});

describe('Role-Based Access Control Integration', function () {
    it('admin has full access to all resources', function () {
        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        // Can access gateways
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/gateways')
            ->assertStatus(200);

        // Can manage users
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/users')
            ->assertStatus(200);

        // Can manage products
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/products')
            ->assertStatus(200);

        // Can view clients
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/clients')
            ->assertStatus(200);

        // Can view transactions
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/transactions')
            ->assertStatus(200);
    });

    it('finance can refund but not manage users', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response(['id' => 'ext-fin'], 200),
            '*charge_back*' => Http::response(['status' => 'refunded'], 200),
        ]);

        // Create a transaction first
        $this->postJson('/api/payments', [
            'name' => 'Finance Test',
            'email' => 'fintest@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => $this->productA->id, 'quantity' => 1]]
        ]);

        $transaction = Transaction::first();
        $user = User::where('role', 'finance')->first();
        $token = $user->createToken('test')->plainTextToken;

        // Can refund
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(200);

        // Cannot manage users
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/users')
            ->assertStatus(403);

        // Cannot manage gateways
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/gateways')
            ->assertStatus(403);

        // Can manage products
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/products')
            ->assertStatus(200);
    });
});
