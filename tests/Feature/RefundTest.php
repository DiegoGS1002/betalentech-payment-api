<?php

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Create users with different roles
    User::factory()->create(['email' => 'admin@test.com', 'role' => 'admin']);
    User::factory()->create(['email' => 'finance@test.com', 'role' => 'finance']);
    User::factory()->create(['email' => 'manager@test.com', 'role' => 'manager']);
    User::factory()->create(['email' => 'user@test.com', 'role' => 'user']);

    // Create gateway
    Gateway::factory()->create([
        'id' => 1,
        'name' => 'gateway1',
        'is_active' => true,
        'priority' => 1,
    ]);

    Gateway::factory()->create([
        'id' => 2,
        'name' => 'gateway2',
        'is_active' => true,
        'priority' => 2,
    ]);

    // Create client
    Client::factory()->create(['id' => 1, 'name' => 'Test Client', 'email' => 'client@test.com']);

    // Create product
    Product::factory()->create(['id' => 1, 'name' => 'Product A', 'amount' => 1000]);
});

describe('Refund Authorization', function () {
    it('admin user can refund transaction', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1,
            'external_id' => 'ext-123',
            'status' => 'approved',
            'amount' => 1000,
            'card_last_numbers' => '6063',
        ]);

        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            "*/transactions/ext-123/charge_back" => Http::response(['status' => 'refunded'], 200),
        ]);

        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify transaction status was updated
        $transaction->refresh();
        expect($transaction->status)->toBe('refunded');
    });

    it('finance user can refund transaction', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1,
            'external_id' => 'ext-456',
            'status' => 'approved',
            'amount' => 2000,
            'card_last_numbers' => '1234',
        ]);

        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions/ext-456/charge_back' => Http::response(['status' => 'refunded'], 200),
        ]);

        $user = User::where('role', 'finance')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(200);
    });

    it('manager user cannot refund transaction', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1,
            'external_id' => 'ext-789',
            'status' => 'approved',
            'amount' => 1000,
            'card_last_numbers' => '6063',
        ]);

        $user = User::where('role', 'manager')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(403);
    });

    it('regular user cannot refund transaction', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1,
            'external_id' => 'ext-999',
            'status' => 'approved',
            'amount' => 1000,
            'card_last_numbers' => '6063',
        ]);

        $user = User::where('role', 'user')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(403);
    });

    it('unauthenticated user cannot refund transaction', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1,
            'external_id' => 'ext-111',
            'status' => 'approved',
            'amount' => 1000,
            'card_last_numbers' => '6063',
        ]);

        $response = $this->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(401);
    });
});

describe('Refund Processing', function () {
    it('cannot refund already refunded transaction', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1,
            'external_id' => 'ext-refunded',
            'status' => 'refunded',
            'amount' => 1000,
            'card_last_numbers' => '6063',
        ]);

        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Transaction already refunded']);
    });

    it('refunds transaction via gateway1', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1, // gateway1
            'external_id' => 'ext-gw1-refund',
            'status' => 'approved',
            'amount' => 1500,
            'card_last_numbers' => '6063',
        ]);

        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions/ext-gw1-refund/charge_back' => Http::response([
                'status' => 'refunded',
                'id' => 'ext-gw1-refund',
            ], 200),
        ]);

        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Refund processed successfully',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transactions/ext-gw1-refund/charge_back');
        });
    });

    it('refunds transaction via gateway2', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 2, // gateway2
            'external_id' => 'ext-gw2-refund-uuid',
            'status' => 'approved',
            'amount' => 2000,
            'card_last_numbers' => '1234',
        ]);

        Http::fake([
            '*/transacoes/reembolso' => Http::response([
                'status' => 'reembolsado',
                'id' => 'ext-gw2-refund-uuid',
            ], 200),
        ]);

        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Refund processed successfully',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transacoes/reembolso')
                && $request['id'] === 'ext-gw2-refund-uuid';
        });
    });

    it('returns error when gateway refund fails', function () {
        $transaction = Transaction::factory()->create([
            'client_id' => 1,
            'gateway_id' => 1,
            'external_id' => 'ext-fail-refund',
            'status' => 'approved',
            'amount' => 1000,
            'card_last_numbers' => '6063',
        ]);

        Http::fake([
            '*/login' => Http::response(['token' => 'fake-token'], 200),
            '*/transactions/ext-fail-refund/charge_back' => Http::response([
                'error' => 'Transaction not found',
            ], 404),
        ]);

        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        // Transaction status should remain approved
        $transaction->refresh();
        expect($transaction->status)->toBe('approved');
    });

    it('returns 404 for non-existent transaction', function () {
        $user = User::where('role', 'admin')->first();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/transactions/99999/refund');

        $response->assertStatus(404);
    });
});

