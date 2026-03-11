<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Dyrynda\Database\Casts\EfficientUuid;
use Illuminate\Database\Eloquent\Builder;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends Model
{
    use GeneratesUuid, HasFactory;

    protected $fillable = [
        'name', 'status', 'zeepay_phone', 'wema_ngn_account',
        'wallet_type_id', 'balance', 'client_id',
        'debit', 'credit', 'running_bal',
        'running_bal_credit', 'running_bal_debit', 'overdraft_bal',
        'overdraft_bal_debit', 'overdraft_bal_credit','currency'
    ];

    protected $with = ['client'];

    public function scopeForType(Builder $query, ?int $walletTypeId = null): void
    {
        $query->where('wallet_type_id', '=', $walletTypeId);
    }

    public function scopeForUser(Builder $query, ?int $clientId = null): void
    {
        if (! $clientId) {
            $query->where('status', '=', 'active');
        }

        if ($clientId) {
            $query->where('status', '=', 'active')->where('client_id', '=', $clientId);
        }
    }

    public function getPhoneAttribute()
    {
        return $this->client->user->phone;
    }

    public function hasSameCurrency($wallet)
    {
        return $this->wallet_type_id == $wallet->wallet_type_id;
    }

    public function hasDifferentCurrency($wallet)
    {
        return ! $this->hasSameCurrency($wallet);
    }

    public function canPay($amount)
    {
        return $this->balance >= $amount;
    }

    public function canNotPay($amount): bool
    {
        return $this->balance < $amount;
    }

    public function iconUrl()
    {
        return $this->wallettype->icon_url;
    }

    public function cashinmethods($wallet)
    {
        $methods = $wallet->client->user->country->cashinmethods;

        $supported_methods = $methods->filter(function ($method, $key) use ($wallet) {
            $match = false;

            foreach ($method->supported_currencies as $currency) {
                if ($wallet->wallettype->currency == $currency) {
                    $match = true;
                }
            }

            return $match;
        })->all();

        return $supported_methods;
    }

    public function cashoutmethods($wallet)
    {
        $methods = $wallet->client->user->country->cashoutmethods;

        $supported_methods = $methods->filter(function ($method, $key) use ($wallet) {
            $match = false;

            foreach ($method->supported_currencies as $currency) {
                if ($wallet->wallettype->currency == $currency) {
                    $match = true;
                }
            }

            return $match;
        })->all();

        return $supported_methods;
    }

    public function getUserAttribute()
    {
        return $this->client->user;
    }

    public function getCurrencyAttribute()
    {
        return $this->wallettype->currency;
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function flutterwaveVirtualAccount()
    {
        return $this->hasOne(FlutterwaveVirtualAccount::class, 'wallet_id');
    }

    public function wallettype()
    {
        return $this->belongsTo(WalletType::class, 'wallet_type_id');
    }

    public function wallettransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    public function recentTrx()
    {
        return $this->transactions()->orderBy('id', 'desc')->first();
    }

    public function brijxoffers()
    {
        return $this->hasMany(BrijXOffer::class, 'wallet_id');
    }

    public function funds()
    {
        return $this->hasMany(Fund::class, 'wallet_id');
    }


    public function walletKycLevel()
    {
        return $this->hasOne(WalletKycLevel::class, 'level', 'user_wallet_kyc_level');
    }

    public function walletDebitSchedule()
    {
        return $this->hasMany(WalletDebitSchedule::class, 'wallet_id');
    }

    public function balanceAlertSubscription()
    {
        return $this->hasOne(BalanceAlertSubscription::class, 'wallet_id');
    }

    /**
     * Fetch all wallet abilities related to this wallet
     *
     * @param  bool  $inherit  true if you want kyc level 1 to inherit wallet abilities under kyc level 0
     * @return \Illuminate\Support\Collection
     */
    public function getWalletAbilities($inherit = true)
    {
        if ($inherit) {
            $wallet_kyc_levels_ids = WalletKycLevel::where('wallet_type_id', $this->wallet_type_id)->where('level', '<=', $this->user_wallet_kyc_level)->pluck('id');
            if ($wallet_kyc_levels_ids->isEmpty()) {
                return collect([]);
            }

            return WalletKycLevelWalletAbility::selectRaw('DISTINCT wallet_ability_id')->whereIn('wallet_kyc_level_id', $wallet_kyc_levels_ids)->with('walletAbility')->get()->transform(function ($item) {
                return $item->walletAbility;
            });
        } else {
            return $this->walletKycLevel->walletAbilities;
        }
    }

    protected $casts = [
        'uuid' => EfficientUuid::class,
    ];


    public function eligibleForFlutterwavePSA(): bool
    {
        $walletTypeIsUsd = $this->wallettype?->currency == 'USD';
        $clientIsMerchant = $this->client?->clientaccounttype->name == 'Merchant Account';
        $clientHasRemittanceRole = $this->user->hasRole('remittance-merchant');

        return $walletTypeIsUsd && $clientIsMerchant && $clientHasRemittanceRole;
    }
    public function walletServiceDeactivations(): HasMany
    {
        return $this->hasMany(WalletServiceDeactivation::class, 'wallet_id');
    }
}
