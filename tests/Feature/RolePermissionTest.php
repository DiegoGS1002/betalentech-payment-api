<?php

use App\Models\Gateway;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    // Create users with all roles
    User::factory()->create(['email' => 'admin@test.com', 'role' => 'admin', 'name' => 'Admin User']);
    User::factory()->create(['email' => 'manager@test.com', 'role' => 'manager', 'name' => 'Manager User']);
    User::factory()->create(['email' => 'finance@test.com', 'role' => 'finance', 'name' => 'Finance User']);
    User::factory()->create(['email' => 'user@test.com', 'role' => 'user', 'name' => 'Regular User']);

    // Create test data
    Gateway::factory()->create(['id' => 1, 'name' => 'gateway1', 'is_active' => true, 'priority' => 1]);
    Product::factory()->create(['id' => 1, 'name' => 'Test Product', 'amount' => 1000]);
});

function authenticateAs(string $role): string
{
    $user = User::where('role', $role)->first();
    return $user->createToken('test')->plainTextToken;
}

describe('Gateway Management (Admin Only)', function () {
    it('admin can list gateways', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/gateways');

        $response->assertStatus(200);
    });

    it('admin can activate gateway', function () {
        Gateway::where('id', 1)->update(['is_active' => false]);

        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/activate');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Gateway ativado com sucesso']);

        expect(Gateway::find(1)->is_active)->toBeTrue();
    });

    it('admin can deactivate gateway', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/deactivate');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Gateway desativado com sucesso']);

        expect(Gateway::find(1)->is_active)->toBeFalse();
    });

    it('admin can update gateway priority', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/priority', ['priority' => 5]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Prioridade atualizada com sucesso']);

        expect(Gateway::find(1)->priority)->toBe(5);
    });

    it('manager cannot manage gateways', function () {
        $token = authenticateAs('manager');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/gateways');

        $response->assertStatus(403);
    });

    it('finance cannot manage gateways', function () {
        $token = authenticateAs('finance');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/activate');

        $response->assertStatus(403);
    });

    it('regular user cannot manage gateways', function () {
        $token = authenticateAs('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/priority', ['priority' => 2]);

        $response->assertStatus(403);
    });
});

describe('User CRUD (Admin and Manager)', function () {
    it('admin can list users', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonCount(4); // 4 users created in beforeEach
    });

    it('admin can create user', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'password' => 'password123',
                'role' => 'user',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email', 'role']);

        $this->assertDatabaseHas('users', ['email' => 'newuser@test.com']);
    });

    it('admin can view user', function () {
        $token = authenticateAs('admin');
        $userId = User::where('role', 'user')->first()->id;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson("/api/users/{$userId}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email', 'role']);
    });

    it('admin can update user', function () {
        $token = authenticateAs('admin');
        $userId = User::where('role', 'user')->first()->id;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/users/{$userId}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJson(['name' => 'Updated Name']);
    });

    it('admin can delete user', function () {
        $token = authenticateAs('admin');
        $userId = User::where('role', 'user')->first()->id;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/users/{$userId}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Usuário deletado com sucesso']);

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    });

    it('manager can create user', function () {
        $token = authenticateAs('manager');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/users', [
                'name' => 'Manager Created User',
                'email' => 'managercreated@test.com',
                'password' => 'password123',
                'role' => 'user',
            ]);

        $response->assertStatus(201);
    });

    it('manager can update user', function () {
        $token = authenticateAs('manager');
        $userId = User::where('role', 'user')->first()->id;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/users/{$userId}", [
                'name' => 'Manager Updated',
            ]);

        $response->assertStatus(200);
    });

    it('finance cannot manage users', function () {
        $token = authenticateAs('finance');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/users');

        $response->assertStatus(403);
    });

    it('regular user cannot manage users', function () {
        $token = authenticateAs('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/users', [
                'name' => 'Test',
                'email' => 'test@test.com',
                'password' => 'password123',
                'role' => 'user',
            ]);

        $response->assertStatus(403);
    });
});

describe('Product CRUD (Admin, Manager, Finance)', function () {
    it('admin can create product', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'name' => 'New Product',
                'amount' => 2500,
            ]);

        $response->assertStatus(201)
            ->assertJson(['name' => 'New Product', 'amount' => 2500]);
    });

    it('admin can list products', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/products');

        $response->assertStatus(200);
    });

    it('admin can update product', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/products/1', [
                'name' => 'Updated Product',
                'amount' => 3000,
            ]);

        $response->assertStatus(200)
            ->assertJson(['name' => 'Updated Product', 'amount' => 3000]);
    });

    it('admin can delete product', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson('/api/products/1');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Produto deletado com sucesso']);
    });

    it('manager can create product', function () {
        $token = authenticateAs('manager');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'name' => 'Manager Product',
                'amount' => 1500,
            ]);

        $response->assertStatus(201);
    });

    it('finance can create product', function () {
        $token = authenticateAs('finance');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'name' => 'Finance Product',
                'amount' => 1800,
            ]);

        $response->assertStatus(201);
    });

    it('regular user cannot create product', function () {
        $token = authenticateAs('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'name' => 'Produto Teste',
                'amount' => 1000
            ]);

        $response->assertStatus(403);
    });

    it('regular user cannot update product', function () {
        $token = authenticateAs('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/products/1', [
                'name' => 'Updated',
            ]);

        $response->assertStatus(403);
    });

    it('regular user cannot delete product', function () {
        $token = authenticateAs('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson('/api/products/1');

        $response->assertStatus(403);
    });
});

describe('Product Validation', function () {
    it('validates product name is required', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'amount' => 1000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('validates product amount is required', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'name' => 'Test Product',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    });

    it('validates product amount is positive integer', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'amount' => -100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    });
});

describe('User Validation', function () {
    it('validates user email is unique', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/users', [
                'name' => 'Duplicate Email',
                'email' => 'admin@test.com', // Already exists
                'password' => 'password123',
                'role' => 'user',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates user role is valid', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/users', [
                'name' => 'Invalid Role',
                'email' => 'invalid@test.com',
                'password' => 'password123',
                'role' => 'invalid_role',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    });

    it('validates password minimum length', function () {
        $token = authenticateAs('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/users', [
                'name' => 'Short Password',
                'email' => 'short@test.com',
                'password' => '123',
                'role' => 'user',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });
});

