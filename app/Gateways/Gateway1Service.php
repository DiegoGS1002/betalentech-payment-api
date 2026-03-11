<?php

namespace App\Gateways;

use App\Gateways\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class Gateway1Service implements PaymentGatewayInterface
{
    public function process(array $data): array
    {

        $response = Http::withToken(
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJnYXRld2F5IjoiZ2F0ZXdheTEifQ.jwS2eC6p6UQf7D9nQ1R2v9FQ1v5mP1Q1gJ2sR4G7K8A'
        )->post('http://gateway1:3001/transactions', [
            'amount' => $data['valor']
        ]);

        return $response->json();
    }
}
