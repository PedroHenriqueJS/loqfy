<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\RentalRequestController;
use Illuminate\Support\Facades\Route;

Route::post('login/demo', [AuthController::class, 'demo'])->middleware('throttle:20,1');

Route::middleware('auth')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('catalog', [CatalogController::class, 'index']);
    Route::post('equipment', [CatalogController::class, 'storeEquipment']);
    Route::put('equipment/{equipment}', [CatalogController::class, 'updateEquipment']);
    Route::delete('equipment/{equipment}', [CatalogController::class, 'destroyEquipment']);
    Route::post('delivery-zones', [CatalogController::class, 'storeZone']);
    Route::put('delivery-zones/{deliveryZone}', [CatalogController::class, 'updateZone']);
    Route::delete('delivery-zones/{deliveryZone}', [CatalogController::class, 'destroyZone']);

    Route::apiResource('customers', CustomerController::class)->except('show');
    Route::get('cnpj/{cnpj}', [CustomerController::class, 'cnpj'])->middleware('throttle:20,1');

    Route::get('quotes', [QuoteController::class, 'index']);
    Route::post('quotes', [QuoteController::class, 'store']);
    Route::get('quotes/{quote}', [QuoteController::class, 'show']);
    Route::put('quotes/{quote}', [QuoteController::class, 'update']);
    Route::post('quotes/{quote}/approve', [QuoteController::class, 'approve']);
    Route::post('quotes/{quote}/discard', [QuoteController::class, 'discard']);

    Route::get('rentals', [RentalController::class, 'index']);
    Route::post('rentals/{rental}/checkout', [RentalController::class, 'checkout']);
    Route::get('rentals/{rental}/contract', [ContractController::class, 'show']);
    Route::post('rental-items/{rentalItem}/manual-return', [InspectionController::class, 'manualReturn']);

    Route::get('dashboard', [DashboardController::class, 'show']);

    Route::get('finance/summary', [FinanceController::class, 'summary']);
    Route::get('finance/entries', [FinanceController::class, 'index']);
    Route::post('finance/entries', [FinanceController::class, 'store']);
    Route::put('finance/entries/{entry}', [FinanceController::class, 'update']);
    Route::delete('finance/entries/{entry}', [FinanceController::class, 'destroy']);
    Route::post('finance/entries/{entry}/toggle-paid', [FinanceController::class, 'togglePaid']);

    Route::get('rental-requests', [RentalRequestController::class, 'index']);
    Route::post('rental-requests', [RentalRequestController::class, 'store'])->middleware('throttle:ai');
    Route::get('rental-requests/{rentalRequest}', [RentalRequestController::class, 'show']);
    Route::post('rental-requests/{rentalRequest}/reprocess', [RentalRequestController::class, 'reprocess'])->middleware('throttle:ai');
    Route::post('rental-requests/{rentalRequest}/discard', [RentalRequestController::class, 'discard']);

    Route::post('rental-items/{rentalItem}/checkout-photo', [InspectionController::class, 'checkout']);
    Route::post('rental-items/{rentalItem}/return-photo', [InspectionController::class, 'return'])->middleware('throttle:ai');
    Route::get('inspections/{inspection}', [InspectionController::class, 'show']);
    Route::post('inspections/{inspection}/decision', [InspectionController::class, 'decide']);

    Route::post('finance/receipts', [FinanceController::class, 'readReceipt'])->middleware('throttle:ai');
    Route::post('dashboard/analysis', [DashboardController::class, 'analyze'])->middleware('throttle:ai');
    Route::get('ai/summary', [AiController::class, 'summary']);
});
