<?php

namespace App\Repositories;

use App\Exceptions\CustomBadRequestException;
use App\Exceptions\CustomModelNotFoundException;
use App\Exceptions\SomethingWentWrongException;
use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;

class WalletRepository implements WalletRepositoryInterface
{
    public static function find($uuid): Wallet
    {
        $wallet = Wallet::whereUuid($uuid)->whereStatus('active')->lockForUpdate()->first();

        if (blank($wallet)) {
            throw new CustomBadRequestException('The wallet is temporarily inactive');
        }

        if (blank($wallet)) {
            throw new CustomModelNotFoundException('No wallet found with the id specified.');
        }

        return $wallet;
    }

    public static function create($clientID, $walletType, $wemaNgnAccount = null, $walletName = 'null')
    {
        $wallet = self::checkIfExists($clientID, $walletType);

        if (! $wallet) {
            $wallet = Wallet::create([
                'name' => $walletName,
                'wema_ngn_account' => $wemaNgnAccount,
                'wallet_type_id' => $walletType->id,
                'colour_code' => $walletType->colour_code,
                'client_id' => $clientID,
                'balance' => 0.00,
                'status' => 'active',
            ]);
        }

        throw_if(blank($wallet), new SomethingWentWrongException('Error occurred. Wallet not created'));

        return $wallet;
    }

    public static function checkIfExists($clientID, $walletType)
    {
        $wallet = Wallet::where('client_id', $clientID)->where('wallet_type_id', $walletType->id)->first();

        return $wallet;
    }

    public static function reduceBalance(Wallet $wallet, $amount)
    {
        $wallet->sharedLock();
        $wallet->balance = $wallet->balance - $amount;
        $wallet->debit = $wallet->debit + $amount;
        $wallet->save();

        return $wallet->refresh();
    }

    public static function refund(Wallet $wallet, $amount)
    {
        $wallet->sharedLock();
        $wallet->balance = $wallet->balance + $amount;
        $wallet->save();

        return $wallet->refresh();
    }
}
