<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Menus & Tables - Accessible by all authenticated users
    Route::apiResource('menus', MenuController::class);
    Route::apiResource('tables', TableController::class);

    // Orders - View accessible by all
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);

    // Pelayan (Waiter) only routes
    Route::middleware('role:Pelayan')->group(function () {
        Route::post('orders/open', [OrderController::class, 'openOrder']);
        Route::post('orders/{order}/items', [OrderController::class, 'addItem']);
        Route::delete('orders/{order}/items/{orderItem}', [OrderController::class, 'removeItem']);
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    });

    // Kasir (Cashier) only routes
    Route::middleware('role:Kasir')->group(function () {
        Route::post('orders/{order}/close', [OrderController::class, 'closeOrder']);
        Route::get('orders/{order}/receipt', [OrderController::class, 'generateReceipt']);

        Route::post('orders/{order}/payments', [PaymentController::class, 'store']);
        Route::get('orders/{order}/payments', [PaymentController::class, 'index']);
        Route::post('orders/{order}/payments/{payment}/refund', [PaymentController::class, 'refund']);

        Route::get('reports/daily-sales', [ReportController::class, 'dailySales']);
        Route::get('reports/best-sellers', [ReportController::class, 'bestSellers']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/staff-performance', [ReportController::class, 'staffPerformance']);
        Route::get('reports/category-analysis', [ReportController::class, 'categoryAnalysis']);
        Route::get('reports/summary', [ReportController::class, 'summary']);
    });
});
