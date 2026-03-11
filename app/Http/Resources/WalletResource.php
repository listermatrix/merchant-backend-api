<?php

namespace App\Http\Resources;

use App\Models\Country;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray($request)
    {
        $country = $this->getCountryName();
        return [
            'name' => $this->name,
            'id' => $this->uuid,
            'balance' => $this->balance,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'brijx_escrow_balance' => $this->brijx_escrow,
            'currency' => $this->wallettype->currency,
            'country_name' => $country?->name,
            'country_currency' => $country?->country_currency,
            'icon_url' => url($this->wallettype->icon_url),
            'exchange_state' => $this->wallettype->exchange_state,
            'fund_state' => $this->wallettype->fund_state,
            'can_swap_currency' => $this->wallettype->can_swap_currency,
            'user_wallet_kyc_level' => $this->user_wallet_kyc_level,
            'withdraw_state' => $this->wallettype->withdraw_state,
            'status' => $this->status,
            'colour_code' => $this->wallettype->colour_code,
            // 'payment_methods' => CashInMethodResource::collection( $this->cashinmethods($this) ),
            // 'cashout_methods' => CashOutMethodResource::collection( $this->cashoutmethods($this) ),
            //'transactions' => WalletTransactionResource::collection($this->wallettransactions),
            'minimum_cashin' => $this->wallettype->minimum_cashin,
            'minimum_cashout' => $this->wallettype->minimum_cashout,
            'wema_ngn_account' => $this->wema_ngn_account,
            'wallet_abilities' => $this->getWalletAbilities(),
            'disable_wallet_services' => WalletServiceDeactivationResource::collection($this->walletServiceDeactivations),
        ];
    }

    private function getWalletAbilities()
    {
        return $this->walletKycLevel ? $this->walletKycLevel->walletAbilities->pluck('slug') : [];
    }

    private function getCountryName(): ?Country
    {
        $country = Country::whereCurrencySymbol($this->wallettype->currency)->first();

        return $country;
    }
}
