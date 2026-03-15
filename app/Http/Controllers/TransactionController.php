<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\PaymentService;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with('client', 'gateway')->get();

        return response()->json($transactions);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('client', 'gateway', 'transactionProducts.product');

        return response()->json($transaction);
    }

    public function refund(Transaction $transaction, PaymentService $paymentService)
    {
        if ($transaction->status === 'refunded') {
            return response()->json([
                'success' => false,
                'message' => 'Transação já reembolsada',
                'error' => 'Esta transação já foi reembolsada anteriormente.',
            ], 422);
        }

        $result = $paymentService->refund($transaction);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 422);
    }
}
