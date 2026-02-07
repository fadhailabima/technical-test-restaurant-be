<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderSessionController;
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

    // Order Sessions - View accessible by all
    Route::get('order-sessions', [OrderSessionController::class, 'index']);
    Route::get('order-sessions/{session}', [OrderSessionController::class, 'show']);
    Route::get('order-sessions/{session}/receipt', [OrderSessionController::class, 'generateReceipt'])->name('api.order-sessions.receipt');

    // Pelayan (Waiter) only routes
    Route::middleware('role:pelayan')->group(function () {
        Route::post('orders/open', [OrderController::class, 'openOrder']);
        Route::post('orders/{order}/items', [OrderController::class, 'addItem']);
        Route::match(['patch', 'put', 'post'], 'orders/{order}/status', [OrderController::class, 'updateStatus']);
    });

    // Kasir (Cashier) only routes
    Route::middleware('role:kasir')->group(function () {
        Route::post('orders/{order}/close', [OrderController::class, 'closeOrder']);
        Route::get('orders/{order}/receipt', [OrderController::class, 'generateReceipt']);

        Route::post('orders/{order}/payments', [PaymentController::class, 'store']);
        Route::get('orders/{order}/payments', [PaymentController::class, 'index']);
        Route::post('payments/bulk', [PaymentController::class, 'bulkPayment']);
        Route::get('payments', [PaymentController::class, 'all']);

        Route::post('order-sessions/{session}/complete', [OrderSessionController::class, 'complete']);

        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/staff-dashboard', [ReportController::class, 'staffDashboard']);
        Route::get('reports/daily-sales', [ReportController::class, 'dailySales']);
        Route::get('reports/best-sellers', [ReportController::class, 'bestSellers']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/staff-performance', [ReportController::class, 'staffPerformance']);
        Route::get('reports/category-analysis', [ReportController::class, 'categoryAnalysis']);
        Route::get('reports/summary', [ReportController::class, 'summary']);
    });
});
