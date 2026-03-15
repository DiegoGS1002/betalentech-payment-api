<?php

namespace App\Services;

use App\Gateways\Gateway1Service;
use App\Gateways\Gateway2Service;
use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionProduct;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected array $gatewayMap = [
        'gateway1' => Gateway1Service::class,
        'gateway2' => Gateway2Service::class,
    ];

    public function process(array $data): array
    {
        $gateways = Gateway::where('is_active', true)
            ->orderBy('priority')
            ->get();

        if ($gateways->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Nenhum gateway ativo disponível',
                'error' => 'Não há gateways de pagamento ativos no momento. Por favor, tente novamente mais tarde.',
            ];
        }

        $products = [];
        $totalAmount = 0;

        foreach ($data['products'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $products[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
            ];
            $totalAmount += $product->amount * $item['quantity'];
        }

        $client = Client::firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name']]
        );

        $paymentData = [
            'amount' => $totalAmount,
            'name' => $data['name'],
            'email' => $data['email'],
            'cardNumber' => $data['cardNumber'],
            'cvv' => $data['cvv'],
        ];

        $lastError = null;

        foreach ($gateways as $gateway) {
            $serviceClass = $this->gatewayMap[$gateway->name] ?? null;

            if (!$serviceClass) {
                continue;
            }

            try {
                $service = new $serviceClass();
                $result = $service->process($paymentData);

                if ($result['success']) {
                    $transaction = DB::transaction(function () use ($client, $gateway, $result, $totalAmount, $data, $products) {
                        $externalId = $result['data']['id'] ?? $result['data']['transactionId'] ?? null;

                        $transaction = Transaction::create([
                            'client_id' => $client->id,
                            'gateway_id' => $gateway->id,
                            'external_id' => $externalId,
                            'status' => 'approved',
                            'amount' => $totalAmount,
                            'card_last_numbers' => substr($data['cardNumber'], -4),
                        ]);

                        foreach ($products as $item) {
                            TransactionProduct::create([
                                'transaction_id' => $transaction->id,
                                'product_id' => $item['product']->id,
                                'quantity' => $item['quantity'],
                            ]);
                        }

                        return $transaction;
                    });

                    return [
                        'success' => true,
                        'message' => 'Pagamento realizado com sucesso',
                        'transaction_id' => $transaction->id,
                        'external_id' => $transaction->external_id,
                        'gateway' => $gateway->name,
                        'amount' => $totalAmount,
                        'status' => 'approved',
                    ];
                }

                $lastError = $result;
            } catch (\Exception $e) {
                $lastError = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return [
            'success' => false,
            'message' => 'Falha no pagamento',
            'error' => 'O pagamento falhou em todos os gateways disponíveis.',
            'last_error' => $lastError,
        ];
    }

    public function refund(Transaction $transaction): array
    {
        $gateway = $transaction->gateway;
        $serviceClass = $this->gatewayMap[$gateway->name] ?? null;

        if (!$serviceClass) {
            return [
                'success' => false,
                'message' => 'Gateway não encontrado',
                'error' => 'O serviço do gateway não foi encontrado.',
            ];
        }

        $service = new $serviceClass();

        try {
            $result = $service->refund($transaction->external_id);

            if ($result['success']) {
                $transaction->update(['status' => 'refunded']);

                return [
                    'success' => true,
                    'message' => 'Reembolso processado com sucesso',
                    'data' => $result['data'],
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar reembolso',
                'error' => $e->getMessage(),
            ];
        }
    }
}
