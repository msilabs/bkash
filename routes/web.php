<?php

use Illuminate\Support\Facades\Route;
use Msilabs\Bkash\Http\Controllers\BkashSandboxController;

Route::get('/bkash-sandbox/create-payment', [BkashSandboxController::class, 'createSandboxPayment']);

Route::get('/bkash-sandbox/execute-payment', [BkashSandboxController::class, 'executeSandboxPayment']);
