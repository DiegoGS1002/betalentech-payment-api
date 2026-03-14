<?php

use App\Models\User;

beforeEach(function () {
    User::factory()->create([
        'email' => 'admin@betalent.tech',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);
});

describe('Login', function () {
    it('user can login with valid credentials and receive token', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@betalent.tech',
            'password' => 'password'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
                'token'
            ]);
    });

    it('user cannot login with invalid password', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@betalent.tech',
            'password' => 'wrong_password'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('user cannot login with non-existent email', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@email.com',
            'password' => 'password'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates email is required', function () {
        $response = $this->postJson('/api/login', [
            'password' => 'password'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates password is required', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@betalent.tech'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('validates email format', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'password'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });
});

describe('Logout', function () {
    it('authenticated user can logout', function () {
        $user = User::first();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        // Token should be invalidated
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    });

    it('unauthenticated user cannot logout', function () {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    });
});
