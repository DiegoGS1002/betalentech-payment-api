<?php

namespace App\Gateways;

use App\Gateways\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Gateway1Service implements PaymentGatewayInterface
{
    protected function getBaseUrl(): string
    {
        return config('services.gateway1.url', 'http://gateway-mock:3001');
    }

    protected function authenticate(): ?string
    {
        return Cache::remember('gateway1_auth_token', 3500, function () {
            $response = Http::post($this->getBaseUrl() . '/login', [
                'email' => config('services.gateway1.email'),
                'token' => config('services.gateway1.token'),
            ]);

            if ($response->successful()) {
                return $response->json('token');
            }

            return null;
        });
    }

    public function process(array $data): array
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->post($this->getBaseUrl() . '/transactions', [
                'amount' => $data['amount'],
                'name' => $data['name'],
                'email' => $data['email'],
                'cardNumber' => $data['cardNumber'],
                'cvv' => $data['cvv'],
            ]);

        return [
            'success' => $response->successful(),
            'data' => $response->json(),
            'status' => $response->status(),
        ];
    }

    public function refund(string $externalId): array
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->post($this->getBaseUrl() . "/transactions/{$externalId}/charge_back");

        return [
            'success' => $response->successful(),
            'data' => $response->json(),
            'status' => $response->status(),
        ];
    }
}
