<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\TransactionController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/payments', [PaymentController::class, 'store']);

// Private routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Gateway management (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/gateways', [GatewayController::class, 'index']);
        Route::patch('/gateways/{gateway}/activate', [GatewayController::class, 'activate']);
        Route::patch('/gateways/{gateway}/deactivate', [GatewayController::class, 'deactivate']);
        Route::patch('/gateways/{gateway}/priority', [GatewayController::class, 'updatePriority']);
    });

    // User CRUD (admin, manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // Product CRUD (admin, manager, finance)
    Route::middleware('role:admin,manager,finance')->group(function () {
        Route::apiResource('products', ProductController::class);
    });

    // Clients (all authenticated users)
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);

    // Transactions (all authenticated users can view)
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);

    // Refund (admin, finance)
    Route::middleware('role:admin,finance')->group(function () {
        Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']);
    });
});
