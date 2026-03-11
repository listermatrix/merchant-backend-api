<?php

namespace App\Http\Actions\Wallet;

use App\Exceptions\DuplicateWalletTypeIsNotAllowedException;
use App\Exceptions\WalletTypeIsNotSupportedInClientsCountryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CreateNewZeepayWallet
{
    //TODO: switch to named arguments
    public function execute($wallettype = null, $walletName = null, $user = null)
    {
        //get token from cache
        $access_token = Cache::get('zeepay_bearer_token');

        //register user with kyc

        $data = [[
            'msisdn' => request()->phone,
            'identifier' => env('ZEEPAY_CLIENT_ID'),
            'customerName' => env('ZEEPAY_CLIENT_SECRET'),
            'dateofBirth' => env('USERNAME'),
            'residentialAddress' => env('PASSWORD'),
            'gpsPost' => env('SCOPE'),
            'idTypeYT' => env('PASSWORD'),
            'region' => env('SCOPE'),
            'gender' => env('SCOPE'),
            'callbackURL' => '',
        ]];
        $server_response = Http::WithToken($access_token)->post(
            env('ZEEPAY_BASE_URL').'api/v1/register-with-kyc', $data
        );

        return $server_response;
    }

    private function ensureUserDoesNotHaveSameWalletType($user, $walletType)
    {
        $current_wallets = $user->client->wallets;

        $existing_wallet = $current_wallets->filter(function ($wallet, $key) use ($walletType) {
            return $wallet->wallet_type_id == $walletType->id;
        });

        $existing_wallet = $existing_wallet->all();

        if (! blank($existing_wallet)) {
            throw new DuplicateWalletTypeIsNotAllowedException;
        }

        return true;
    }

    private function ensureWalletIsSupportedInCountry($country, $walletType)
    {
        $country_supports_wallet = false;

        foreach ($country->wallettype as $type) {
            if ($type->uuid == $walletType->uuid) {
                $country_supports_wallet = true;
            }
        }

        if (! $country_supports_wallet) {
            throw new WalletTypeIsNotSupportedInClientsCountryException;
        }

        return true;
    }
}
