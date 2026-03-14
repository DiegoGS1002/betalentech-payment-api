<?php

use App\Gateways\Gateway1Service;
use App\Gateways\Gateway2Service;
use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionProduct;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Create active gateways
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

    // Create products
    Product::factory()->create([
        'id' => 1,
        'name' => 'Product A',
        'amount' => 1000,
    ]);

    Product::factory()->create([
        'id' => 2,
        'name' => 'Product B',
        'amount' => 2500,
    ]);
});

describe('Payment Processing', function () {
    it('can process a payment successfully with single product', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response([
                'id' => 'ext-123',
                'status' => 'approved',
            ], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [
                ['product_id' => 1, 'quantity' => 1]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'amount' => 1000,
                'status' => 'approved',
            ])
            ->assertJsonStructure([
                'success',
                'transaction_id',
                'external_id',
                'gateway',
                'amount',
                'status',
            ]);

        // Verify transaction was created
        $this->assertDatabaseHas('transactions', [
            'amount' => 1000,
            'status' => 'approved',
            'card_last_numbers' => '6063',
        ]);

        // Verify client was created
        $this->assertDatabaseHas('clients', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
        ]);
    });

    it('can process a payment with multiple products', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response([
                'id' => 'ext-123',
                'status' => 'approved',
            ], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 1]
            ]
        ]);

        // Amount should be: (1000 * 2) + (2500 * 1) = 4500
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'amount' => 4500,
            ]);

        $this->assertDatabaseHas('transactions', [
            'amount' => 4500,
        ]);

        // Verify transaction products
        $transaction = Transaction::first();
        expect($transaction->transactionProducts)->toHaveCount(2);
    });

    it('calculates amount from products correctly', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response([
                'id' => 'ext-123',
                'status' => 'approved',
            ], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [
                ['product_id' => 1, 'quantity' => 5] // 1000 * 5 = 5000
            ]
        ]);

        $response->assertStatus(201)
            ->assertJson(['amount' => 5000]);
    });

    it('creates or reuses existing client', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response(['id' => 'ext-123'], 200),
        ]);

        // First purchase
        $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        // Second purchase with same email
        $this->postJson('/api/payments', [
            'name' => 'Cliente Teste Updated',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        // Should only have one client
        expect(Client::count())->toBe(1);
        expect(Transaction::count())->toBe(2);
    });

    it('stores card last 4 digits only', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response(['id' => 'ext-123'], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(201);

        $transaction = Transaction::first();
        expect($transaction->card_last_numbers)->toBe('6063');
    });
});

describe('Payment Validation', function () {
    it('validates name is required', function () {
        $response = $this->postJson('/api/payments', [
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('validates email is required and valid format', function () {
        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'invalid-email',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates cardNumber has exactly 16 digits', function () {
        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '123456', // Too short
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cardNumber']);
    });

    it('validates cvv has 3-4 digits', function () {
        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '12', // Too short
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cvv']);
    });

    it('validates products array is required', function () {
        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products']);
    });

    it('validates products array is not empty', function () {
        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => []
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products']);
    });

    it('validates product_id exists in database', function () {
        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [
                ['product_id' => 999, 'quantity' => 1]
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.product_id']);
    });

    it('validates quantity is positive integer', function () {
        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [
                ['product_id' => 1, 'quantity' => 0]
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.quantity']);
    });
});

describe('Payment Failures', function () {
    it('returns error when no active gateways available', function () {
        // Deactivate all gateways
        Gateway::query()->update(['is_active' => false]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'No active gateways available'
            ]);
    });

    it('returns error when all gateways fail', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response(['error' => 'Card declined'], 400),
            '*/transacoes' => Http::response(['error' => 'Card declined'], 400),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Payment failed on all gateways'
            ]);

        // No transaction should be created
        expect(Transaction::count())->toBe(0);
    });
});
