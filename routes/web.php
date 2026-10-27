<?php

use Illuminate\Support\Facades\Route;
use Msilabs\Bkash\Http\Controllers\BkashSandboxController;

Route::prefix('bkash-sandbox')->name('bkash.sandbox.')->group(function () {
    Route::get('create/{account?}', [BkashSandboxController::class, 'create'])->name('create');
    Route::get('callback/{account?}', [BkashSandboxController::class, 'callback'])->name('callback');
    Route::get('query/{paymentId}/{account?}', [BkashSandboxController::class, 'query'])->name('query');
});
