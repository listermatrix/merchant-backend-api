<?php

namespace App\Models;

use App\Models\RemittanceSourceRail;
use Illuminate\Database\Eloquent\Model;
use Dyrynda\Database\Casts\EfficientUuid;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use GeneratesUuid, HasFactory;

    protected $fillable = ['name', 'code', 'currency', 'currency_symbol', 'abbreviation', 'icon_url', 'supports_brijx_bill_payment'];

    public function remittanceSourceRails()
    {
        return $this->hasMany(RemittanceSourceRail::class);
    }

    public function remittanceDestinationRails()
    {
        return $this->hasMany(RemittanceDestinationRail::class);
    }

    public function kycIdTypes()
    {
        return $this->belongsToMany(KYCIdType::class, 'country_kyc_id_types', 'country_id', 'kyc_id_type_id');
    }

    public function billplans()
    {
        return $this->hasMany(BillPlan::class, 'country_id');
    }

    public function externalpaymentproviders()
    {
        return $this->belongsToMany(ExternalPaymentProvider::class, 'country_external_payment_providers', 'country_id', 'external_payment_provider_id');
    }

    public function cashinmethods()
    {
        return $this->belongsToMany(CashInMethod::class, 'country_cash_in_methods', 'country_id', 'cash_in_method_id')
            ->where('active_state', '=', 'active');
    }

    public function cashoutmethods()
    {
        return $this->belongsToMany(CashOutMethod::class, 'country_cash_out_methods', 'country_id', 'cash_out_method_id');
    }

    //TODO: this and getCashoutMethods have duplicated code
    public function getCashinMethods($wallet_currency = null)
    {
        if (! $wallet_currency) {
            return $this->cashinmethods;
        }

        $cashinMethodsInCountry = $this->cashinmethods;

        $supported_methods = $cashinMethodsInCountry->filter(function ($method, $key) use ($wallet_currency) {
            $match = false;

            foreach ($method->supported_currencies as $currency) {
                if ($wallet_currency == $currency) {
                    $match = true;
                }
            }

            return $match;
        })->all();

        return $supported_methods;
    }

    public function getCashinMethodsByCurrencies($currencies = [])
    {
        if (empty($currencies)) {
            return $this->cashinmethods;
        }

        $counter = 0;
        $methods = collect();
        $cashinMethodsInCountry = $this->cashinmethods;

        while ($counter < count($currencies)) {
            $supported_methods = $cashinMethodsInCountry->filter(function ($method, $key) use ($currencies, $counter) {
                $match = false;

                foreach ($method->supported_currencies as $currency) {
                    if ($currencies[$counter] == $currency) {
                        $match = true;
                    }
                }

                return $match;
            })->all();

            $methods = $methods->merge($supported_methods);

            $counter++;
        }

        //a work around uuid bug , when hydrate is used

        $ids = $methods->pluck('id')->toArray();

        return CashInMethod::whereIn('id', $ids)->get();
    }

    public function getCashoutMethods($wallet_currency = null, $countryCurrency = null)
    {
        if (! $wallet_currency) {
            return $this->cashoutmethods()->where('supports_cashout', true)->where('disabled_at', null)->orderBy('priority', 'asc')->orderBy('name', 'asc')->get();
        }

        if( filled( $countryCurrency ) ) {

            $cashoutMethodsInCountry = $this->cashoutmethods()->where('supports_cashout', true)->where('disabled_at', null)

                ->whereHas('externalpaymentproviders', function($query) use($countryCurrency) {

                    $query->whereHas('externalpaymentprovidercashoutservice', function($subQuery) use($countryCurrency) {

                        $subQuery->where('country_currency', $countryCurrency);

                    });
                })
                ->orderBy('name', 'asc')
                
                ->get();

            $supported_methods = $cashoutMethodsInCountry->filter(function ($method, $key) use ($wallet_currency) {
                $match = false;

                foreach ($method->supported_currencies as $currency) {
                    if ($wallet_currency == $currency) {
                        $match = true;
                    }
                }

                return $match;

            })->all();

            return $supported_methods;

        } else {

            $cashoutMethodsInCountry = $this->cashoutmethods()->where('supports_cashout', true)->where('disabled_at', null)->orderBy('priority', 'asc')->orderBy('name', 'asc')->get();

            $supported_methods = $cashoutMethodsInCountry->filter(function ($method, $key) use ($wallet_currency) {
                $match = false;

                foreach ($method->supported_currencies as $currency) {
                    if ($wallet_currency == $currency) {
                        $match = true;
                    }
                }

                return $match;
            })->all();

            //return $cashoutMethodsInCountry;
            return $supported_methods;
        }

       
    }

    public function wallettype()
    {
        return $this->belongsToMany(WalletType::class, 'country_wallet_types');
    }

    public function individualUserWalletType()
    {
        return $this->belongsToMany(WalletType::class, 'country_wallet_types')
            ->where('is_internal', 0);
    }

    public function client()
    {
        return $this->hasMany(Client::class, 'client_id');
    }

    public function user()
    {
        return $this->hasMany(User::class, 'country_id');
    }

    public function identification_types()
    {
        return $this->hasMany(KycIdentificationTypes::class, 'country_id');
    }

    public function merchant_business_document_types()
    {
        return $this->hasMany(MerchantBusinessDocumentType::class, 'country_id');
    }

    public function external_biller_aggregator()
    {
        return $this->hasMany(ExternalBillerAggregator::class, 'country_id');
    }

    protected $casts = [
        'uuid' => EfficientUuid::class,
    ];
}
