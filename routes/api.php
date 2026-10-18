<?php

use App\Modules\Platform\Http\Controllers\Api\V1\ReadApiController;
use Illuminate\Support\Facades\Route;

/**
 * API v1 read-only (F4.6) — seluruh rute GET di balik token
 * bercakupan (middleware api.token:<scope>). Tidak ada rute tulis.
 */
Route::prefix('v1')->group(function () {
    Route::get('/products', [ReadApiController::class, 'products'])->middleware('api.token:products.read');
    Route::get('/customers', [ReadApiController::class, 'customers'])->middleware('api.token:customers.read');
    Route::get('/sales', [ReadApiController::class, 'sales'])->middleware('api.token:sales.read');
    Route::get('/stock', [ReadApiController::class, 'stock'])->middleware('api.token:stock.read');
    Route::get('/reports/summary', [ReadApiController::class, 'reportsSummary'])->middleware('api.token:reports.read');
});
