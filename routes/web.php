<?php

use Illuminate\Support\Facades\Route;
use Msilabs\Bkash\Http\Controllers\BkashSandboxController;

Route::get('/bkash-sandbox-validation/create-payment/{account?}', [BkashSandboxController::class, 'createSandboxPayment']);

Route::get('/bkash-sandbox-validation/execute-payment/{account?}', [BkashSandboxController::class, 'executeSandboxPayment']);
