<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhonePe\PhonepeController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Payment checkout
Route::get('/payment/checkout', [PhonepeController::class, 'index'])->name('payment.checkout');

// Admin History
Route::get('/payment/history', [PhonepeController::class, 'history'])->name('payment.history');

// Download Invoice PDF
Route::get('/payment/invoice/{merchantOrderId}', [PhonepeController::class, 'downloadInvoice'])->name('payment.invoice');

// Shared payment link route
Route::get('/pay/{merchantOrderId}', [PhonepeController::class, 'processSharedLink'])->name('process.shared.link');
