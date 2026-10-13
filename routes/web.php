<?php

use App\Http\Controllers\PurchaseOrderPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Purchase order PDF (opens in a new tab from the PO page)
Route::get('/purchase-orders/{purchaseOrder}/pdf', PurchaseOrderPdfController::class)
    ->name('purchase-orders.pdf');