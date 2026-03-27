<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhonePe\PhonepeController;

Route::post('/generate-payment-link', [PhonepeController::class, 'generatePaymentLink'])->name('generate.payment.link');
Route::any('/phonepe/callback',       [PhonepeController::class, 'callback'])->name('phonepe.callback');
Route::post('/phonepe/webhook',       [PhonepeController::class, 'webhook'])->name('phonepe.webhook');
Route::get('/payment/success',        [PhonepeController::class, 'success'])->name('payment.success');
Route::get('/payment/failed',         [PhonepeController::class, 'failed'])->name('payment.failed');