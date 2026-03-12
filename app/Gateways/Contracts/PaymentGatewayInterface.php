<?php

namespace App\Gateways\Contracts;

interface PaymentGatewayInterface
{
    public function process(array $data): array;

    public function refund(string $externalId): array;
}
