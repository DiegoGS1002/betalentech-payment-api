<?php

use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Create gateways with different priorities
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

    Product::factory()->create([
        'id' => 1,
        'name' => 'Product A',
        'amount' => 1000,
    ]);
});

describe('Gateway Fallback', function () {
    it('uses gateway with highest priority first', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response(['id' => 'gw1-123', 'status' => 'approved'], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'gateway' => 'gateway1',
            ]);
    });

    it('fallbacks to second gateway when first fails', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response(['error' => 'Card declined'], 400),
            '*/transacoes' => Http::response(['id' => 'gw2-123', 'status' => 'approved'], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '100', // CVV that causes gateway1 to fail
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'gateway' => 'gateway2',
            ]);

        // Verify transaction was created with gateway2
        $transaction = Transaction::first();
        $gateway = Gateway::find($transaction->gateway_id);
        expect($gateway->name)->toBe('gateway2');
    });

    it('respects gateway priority order', function () {
        // Update gateway2 to have higher priority
        Gateway::where('name', 'gateway2')->update(['priority' => 1]);
        Gateway::where('name', 'gateway1')->update(['priority' => 2]);

        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transacoes' => Http::response(['id' => 'gw2-123'], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'gateway' => 'gateway2',
            ]);
    });

    it('skips inactive gateways', function () {
        // Deactivate gateway1
        Gateway::where('name', 'gateway1')->update(['is_active' => false]);

        Http::fake([
            '*/transacoes' => Http::response(['id' => 'gw2-123'], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'gateway' => 'gateway2',
            ]);
    });

    it('handles gateway exception gracefully and tries next', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::throw(fn () => new \Exception('Connection timeout')),
            '*/transacoes' => Http::response(['id' => 'gw2-123'], 200),
        ]);

        $response = $this->postJson('/api/payments', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@email.com',
            'cardNumber' => '5569000000006063',
            'cvv' => '123',
            'products' => [['product_id' => 1, 'quantity' => 1]]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'gateway' => 'gateway2',
            ]);
    });

    it('returns last error when all gateways fail', function () {
        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions' => Http::response(['error' => 'Gateway 1 error'], 400),
            '*/transacoes' => Http::response(['error' => 'Gateway 2 error'], 400),
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
                'message' => 'Falha no pagamento',
            ])
            ->assertJsonStructure(['last_error']);
    });
});
