<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'cardNumber' => 'required|string|size:16',
            'cvv' => 'required|string|min:3|max:4',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        $result = $this->paymentService->process($validated);

        if ($result['success']) {
            return response()->json($result, 201);
        }

        return response()->json($result, 422);
    }
}
