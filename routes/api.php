<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Disbursement\CashoutV2Controller;


/** For security reasons, important
 * routes are removed , only showed a few to demonstrate the purpose of this repo
 */

Route::middleware(['user.permitted', 'user.block-old-accounts'])->group(function () {
    Route::middleware(['auth:sanctum', 'token.master'])->group(function () {
        Route::post('/wallets/{wallet_id}/cashout', [CashoutV2Controller::class, 'cashout'])->middleware(['usermanagement.permission:can-withdraw-wallet-payout']);
        Route::get('/wallets/cashoutmethods', [CashoutV2Controller::class, 'getByCountry']);
    });
});






