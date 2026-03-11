<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $service = new PaymentService();

        $response = $service->process($request->all());

        return response()->json($response);
    }
}
