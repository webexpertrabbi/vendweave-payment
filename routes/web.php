<?php

use Illuminate\Support\Facades\Route;
use VendWeave\Gateway\Http\Controllers\VerifyController;

/*
|--------------------------------------------------------------------------
| VendWeave Web Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the VendWeaveServiceProvider and are
| prefixed with the configured route prefix (default: 'vendweave').
|
*/

Route::get('/verify/{order}', [VerifyController::class, 'show'])
    ->name('vendweave.verify');

Route::get('/success/{order}', [VerifyController::class, 'success'])
    ->name('vendweave.success');

Route::get('/failed/{order}', [VerifyController::class, 'failed'])
    ->name('vendweave.failed');

Route::get('/cancelled/{order}', [VerifyController::class, 'cancelled'])
    ->name('vendweave.cancelled');
