<?php

use Illuminate\Support\Facades\Route;
use VendWeave\Gateway\Http\Controllers\PollController;

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
