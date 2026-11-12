<?php

declare(strict_types=1);

use App\Http\Controllers\LoginController;
use App\Http\Controllers\MasterData\ProductController;
use App\Http\Controllers\Production\ReportController;
use App\Http\Controllers\Production\WorkOrderController;
use App\Http\Middleware\DemoLogin;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class);

Route::middleware(DemoLogin::class)->group(function () {
    Route::post('/work-orders', [WorkOrderController::class, 'store']);
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show']);
    Route::patch('/work-orders/{workOrder}', [WorkOrderController::class, 'update']);
    Route::delete('/work-orders/{workOrder}', [WorkOrderController::class, 'destroy']);
    Route::post('/work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/imports/bom', [ProductController::class, 'importBom']);
    Route::post('/attachments', [ProductController::class, 'attach']);
    Route::get('/reports/output', [ReportController::class, 'output']);
    Route::post('/password/reset/{token}', fn () => response()->json(['message' => 'Your password has been reset']));
});
