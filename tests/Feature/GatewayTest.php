<?php

use App\Models\Gateway;
use App\Models\User;

beforeEach(function () {
    User::factory()->create(['email' => 'admin@test.com', 'role' => 'admin']);
    User::factory()->create(['email' => 'user@test.com', 'role' => 'user']);

    Gateway::factory()->create([
        'id' => 1,
        'name' => 'gateway1',
        'is_active' => true,
        'priority' => 1,
    ]);

    Gateway::factory()->create([
        'id' => 2,
        'name' => 'gateway2',
        'is_active' => false,
        'priority' => 2,
    ]);
});

function authenticateGateway(string $role): string
{
    $user = User::where('role', $role)->first();
    return $user->createToken('test')->plainTextToken;
}

describe('Gateway List', function () {
    it('admin can list all gateways', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/gateways');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'is_active', 'priority'],
            ]);
    });

    it('gateways are ordered by priority', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/gateways');

        $response->assertStatus(200);

        $data = $response->json();
        expect($data[0]['priority'])->toBeLessThanOrEqual($data[1]['priority']);
    });
});

describe('Gateway Activation', function () {
    it('admin can activate a gateway', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/2/activate');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Gateway activated',
                'gateway' => [
                    'id' => 2,
                    'is_active' => true,
                ],
            ]);

        expect(Gateway::find(2)->is_active)->toBeTrue();
    });

    it('admin can deactivate a gateway', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/deactivate');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Gateway deactivated',
                'gateway' => [
                    'id' => 1,
                    'is_active' => false,
                ],
            ]);

        expect(Gateway::find(1)->is_active)->toBeFalse();
    });

    it('returns 404 for non-existent gateway', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/999/activate');

        $response->assertStatus(404);
    });
});

describe('Gateway Priority', function () {
    it('admin can update gateway priority', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/priority', ['priority' => 10]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Priority updated',
                'gateway' => [
                    'id' => 1,
                    'priority' => 10,
                ],
            ]);

        expect(Gateway::find(1)->priority)->toBe(10);
    });

    it('validates priority is required', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/priority', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    });

    it('validates priority is positive integer', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/priority', ['priority' => 0]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    });

    it('validates priority is an integer', function () {
        $token = authenticateGateway('admin');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/priority', ['priority' => 'not-a-number']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    });
});

describe('Gateway Authorization', function () {
    it('unauthenticated user cannot access gateways', function () {
        $response = $this->getJson('/api/gateways');

        $response->assertStatus(401);
    });

    it('regular user cannot access gateways', function () {
        $token = authenticateGateway('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/gateways');

        $response->assertStatus(403);
    });

    it('regular user cannot activate gateway', function () {
        $token = authenticateGateway('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/activate');

        $response->assertStatus(403);
    });

    it('regular user cannot deactivate gateway', function () {
        $token = authenticateGateway('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/deactivate');

        $response->assertStatus(403);
    });

    it('regular user cannot update gateway priority', function () {
        $token = authenticateGateway('user');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/gateways/1/priority', ['priority' => 5]);

        $response->assertStatus(403);
    });
});
