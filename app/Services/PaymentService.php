<?php

namespace App\Gateways;

use App\Gateways\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class Gateway2Service implements PaymentGatewayInterface
{
    public function process(array $data): array
    {
        $response = Http::post('http://gateway2:8080/transacoes', $data);

        return $response->json();
    }
}
