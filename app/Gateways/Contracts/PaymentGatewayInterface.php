<?php

namespace App\Gateways\Contracts;

interface PaymentGatewayInterface
{
    public function process(array $data): array;
}
