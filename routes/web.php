<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhonePe\PhonepeController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Payment checkout
Route::get('/payment/checkout', [PhonepeController::class, 'index'])->name('payment.checkout');

// Shared payment link route
Route::get('/pay/{merchantOrderId}', [PhonepeController::class, 'processSharedLink'])->name('process.shared.link');
