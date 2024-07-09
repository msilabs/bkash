<?php

use Illuminate\Support\Facades\Route;
use Msilabs\Bkash\Http\Controllers\BkashController;

Route::get('/bkash-sandbox/create-payment', [BkashController::class, 'createSandboxPayment']);

Route::get('/bkash-sandbox/execute-payment', [BkashController::class, 'executeSandboxPayment']);
