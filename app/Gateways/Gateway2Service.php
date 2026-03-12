<?php

namespace App\Gateways;

use App\Gateways\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class Gateway2Service implements PaymentGatewayInterface
{
    protected function getBaseUrl(): string
    {
        return config('services.gateway2.url', 'http://gateway-mock:3002');
    }

    protected function getAuthHeaders(): array
    {
        return [
            'Gateway-Auth-Token' => config('services.gateway2.auth_token'),
            'Gateway-Auth-Secret' => config('services.gateway2.auth_secret'),
        ];
    }

    public function process(array $data): array
    {
        $response = Http::withHeaders($this->getAuthHeaders())
            ->post($this->getBaseUrl() . '/transacoes', [
                'valor' => $data['amount'],
                'nome' => $data['name'],
                'email' => $data['email'],
                'numeroCartao' => $data['cardNumber'],
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
        $response = Http::withHeaders($this->getAuthHeaders())
            ->post($this->getBaseUrl() . '/transacoes/reembolso', [
                'id' => $externalId,
            ]);

        return [
            'success' => $response->successful(),
            'data' => $response->json(),
            'status' => $response->status(),
        ];
    }
}
