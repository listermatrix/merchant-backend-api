<?php

namespace App\Http\Actions\Wallet;

use App\DataTransferObjects\WalletData;
use App\Events\FlutterwavePayoutSubAccountRequiredEvent;
use App\Exceptions\CustomBadRequestException;
use App\Exceptions\DuplicateWalletTypeIsNotAllowedException;
use App\Exceptions\WalletTypeIsNotSupportedInClientsCountryException;
use App\Models\Country;
use App\Models\Wallet;
use App\Models\WalletType;
use Illuminate\Support\Facades\Auth;

class CreateNewWallet
{
    //TODO: switch to named arguments
    public function execute($wallettype = null, $walletName = null, $user = null)
    {
        $walletType = (blank(request('wallet_type_id'))) ? $wallettype : WalletType::whereUuid(request('wallet_type_id'))->first();

        $user = $user ?? Auth::user();

        if (blank($walletType)) {
            throw new CustomBadRequestException('No wallet type found for the id specified');
        }

        if ($walletType->is_internal == 1 && request()->user()->is_admin == 'no') {
            throw new CustomBadRequestException('The selected wallet type is not available');
        }

        //check if the wallet is supported in their country
        $users_country = Country::where('code', $user->country_code)->first();

        $this->ensureWalletIsSupportedInCountry($users_country, $walletType);

        $this->ensureUserDoesNotHaveSameWalletType($user, $walletType);

        $data = WalletData::fromCreateWalletRequest();

        //create corresponding bank account
        //TODO ensure account number doesn't exist

        if ($walletType->currency == 'NGN' && $walletType->is_internal != intval(1)) {
            $wema_ngn_account = (new GenerateWemaVirtualAccount)->execute();
        }

        //TODO: in addition to the wallet id, store the currency 
        //directly on the wallet because there are many places 
        // where wallet type needs to be retrieved to know
        //the currency of the wallet. 

        $wallet = Wallet::create([
            'wema_ngn_account' => $wema_ngn_account ?? null,
            'name' => $data->name ?? $walletName,
            'wallet_type_id' => $walletType->id,
            'colour_code' => $walletType->colour_code,
            'client_id' => $user->client_id,
            'balance' => 0.00,
            'status' => 'active',
            'currency' => $walletType->currency
        ]);

        $this->handleFlutterwavePSACreation($wallet);

        return $wallet;
    }

    private function handleFlutterwavePSACreation(Wallet $wallet)
    {
        if ($wallet->eligibleForFlutterwavePSA()) {
            FlutterwavePayoutSubAccountRequiredEvent::dispatch($wallet);
        }
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
