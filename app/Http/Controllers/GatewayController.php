<?php

namespace App\Http\Controllers;

use App\Models\Gateway;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function index()
    {
        return response()->json(Gateway::orderBy('priority')->get());
    }

    public function activate(Gateway $gateway)
    {
        $gateway->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Gateway ativado com sucesso',
            'gateway' => $gateway,
        ]);
    }

    public function deactivate(Gateway $gateway)
    {
        $gateway->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Gateway desativado com sucesso',
            'gateway' => $gateway,
        ]);
    }

    public function updatePriority(Request $request, Gateway $gateway)
    {
        $validated = $request->validate([
            'priority' => 'required|integer|min:1',
        ]);

        $gateway->update(['priority' => $validated['priority']]);

        return response()->json([
            'success' => true,
            'message' => 'Prioridade atualizada com sucesso',
            'gateway' => $gateway,
        ]);
    }
}
