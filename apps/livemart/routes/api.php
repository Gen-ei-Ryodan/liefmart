<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PenerimaanController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\Master\BrandController;
use App\Http\Controllers\Master\SubBrandController;
use App\Http\Controllers\Master\ProductCategoryController;
use App\Http\Controllers\Master\ProductTypeController;
use App\Http\Controllers\Master\ProductSizeController;
use App\Http\Controllers\Master\ProductVariantController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes for penerimaan-related API endpoints
Route::get('/tax-categories', [PenerimaanController::class, 'getTaxCategories']);
Route::get('/products', [PenerimaanController::class, 'getProducts']);

// Routes for sales-related API endpoints
Route::get('/products/{product}/stock-info', [SalesController::class, 'getProductStockInfo']);

// Routes for dynamic creation of master data (require authenticated web session)
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/brands', [BrandController::class, 'storeApi']);
    Route::post('/sub-brands', [SubBrandController::class, 'storeApi']);
    Route::post('/product-categories', [ProductCategoryController::class, 'storeApi']);
    Route::post('/product-types', [ProductTypeController::class, 'storeApi']);
    Route::post('/product-sizes', [ProductSizeController::class, 'storeApi']);
    Route::post('/product-variants', [ProductVariantController::class, 'storeApi']);
});
