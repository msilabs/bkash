<?php

use Illuminate\Support\Facades\Route;
use Msilabs\Bkash\Http\Controllers\BkashController;

Route::post('/bkash/create-payment/{amount}/{invoice_number?}', [BkashController::class, 'createPayment']);

Route::post('/bkash/execute-payment/{payment_id}', [BkashController::class, 'executePayment']);
