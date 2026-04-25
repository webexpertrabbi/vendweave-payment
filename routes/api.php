<?php

use Illuminate\Support\Facades\Route;
use VendWeave\Gateway\Http\Controllers\PollController;
use VendWeave\Gateway\Http\Controllers\ManualVerifyController;

/*
|--------------------------------------------------------------------------
| VendWeave API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the VendWeaveServiceProvider with 'api'
| middleware and are prefixed with 'api/vendweave'.
|
*/

Route::middleware(['throttle:vendweave-poll'])
    ->get('/poll/{order}', [PollController::class, 'poll'])
    ->name('vendweave.poll');

Route::get('/health', [PollController::class, 'health'])
    ->name('vendweave.health');

/**
 * Manual TRX verification (no reference required).
 * Used when the user paid without including the reference number.
 * Matches by: TRX ID + amount + payment method + store slug.
 */
Route::post('/manual-verify/{order}', [ManualVerifyController::class, 'verify'])
    ->name('vendweave.manual-verify');

