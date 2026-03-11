<?php

namespace App\Http\Actions\Traits;

use App\Exceptions\WalletBalanceInsufficientException;

trait CheckWalletHasEnoughBalance
{
    private function ensureWalletHasEnoughBalance($wallet)
    {
        if ($wallet->balance < request()->amount) {
            //initiate stk push via flutterwave
            if ($wallet->wallettype->currency == 'KSH') {
                $this->payBrijUserViaFlutterwaveMpesa($wallet);
            }
            throw new WalletBalanceInsufficientException;
        }

        return true;
    }
}
