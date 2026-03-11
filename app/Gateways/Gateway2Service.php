<?php

namespace App\Gateways;

use App\Gateways\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class Gateway2Service implements PaymentGatewayInterface
{
    public function process(array $data): array
    {

        $response = Http::withToken(
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJnYXRld2F5IjoiZ2F0ZXdheTIifQ.L8v1q1S8n7J4g2W6m8V5X3c1H9P7b4K2R6T5Y8Z9X0A'
        )->post('http://gateway2:3002/transactions', [
            'amount' => $data['valor']
        ]);
        return $response->json();
    }
}
