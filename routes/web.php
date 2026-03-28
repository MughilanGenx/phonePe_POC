<?php

use App\Http\Controllers\PhonePe\PhonepeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Payment checkout
Route::get('/payment/checkout', [PhonepeController::class, 'index'])->name('payment.checkout');

// Admin History
Route::get('/payment/history', [PhonepeController::class, 'history'])->name('payment.history');

// Download Invoice PDF
Route::get('/payment/invoice/{merchantOrderId}', [PhonepeController::class, 'downloadInvoice'])->name('payment.invoice');

// Manual Refresh Statuses
Route::get('/payment/refresh', [PhonepeController::class, 'refreshStatuses'])->name('payment.refresh');

// Import a PhonePe dashboard / payment-link order by Merchant Order ID
Route::post('/payment/import-phonepe-order', [PhonepeController::class, 'importPhonePeOrder'])->name('payment.import.phonepe');

// Shared payment link route
Route::get('/pay/{merchantOrderId}', [PhonepeController::class, 'processSharedLink'])->name('process.shared.link');
