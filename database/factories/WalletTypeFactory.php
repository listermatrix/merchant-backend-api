<?php

namespace Database\Factories;

use App\Models\WalletType;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTypeFactory extends Factory
{
    protected $model = WalletType::class;

    public function definition()
    {
        return [
            'exchange_state' => 'active',
            'fund_state' => 'active',
            'withdraw_state' => 'active',
            'minimum_cashin' => 100,
            'minimum_cashout' => 100,
            'category' => 'users',
            'is_internal' => 0,
            'description' => 'Wallet for user',
            'name' => 'Dummy',
            'currency' => 'GHS',
            'country_currency' => 'GH_GHS',
        ];
    }

    private function getName($currency)
    {
        $map = [
            'GHS' => 'Brij Fees Ghana',
            'NGN' => 'Brij Fees Nigeria',
            'KSH' => 'Brij Fees Kenya',
            'TZS' => 'Brij Fees Tanzania',
        ]; 

        return Arr::get($map, $currency);
    }

    private function getCountryCurrency($currency)
    {
        $map = [
            'GHS' => 'GH_GHS',
            'NGN' => 'NG_NGN',
            'KSH' => 'KE_KSH',
            'TZS' => 'TZ_TZS',
        ];

         return Arr::get($map, $currency);
    }

    public function fees($currency = 'GHS')
    {
        return $this->state(function (array $attributes) use ($currency) {

            return [
                'category' => 'fees',
                'is_internal' => 1, 
                'name' => $this->getName($currency),
                'country_currency' => $this->getCountryCurrency($currency),
                'description' => 'Wallet for fees'
            ];
        });
    }

    public function prefunded()
    {
        return $this->state(function (array $attributes) {
            return [
                'category' => 'prefunds',
            ];
        });
    }

    public function GHGHS()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Cedi',
                'currency' => 'GHS',
                'country_currency' => 'GH_GHS',
                'icon_url' => env('APP_URL').'/media/icons/flags/ghana_flag.png',
                'description' => 'Base wallet for Ghanaians',
            ];
        });
    }

    public function NGNGN()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Naira',
                'currency' => 'NGN',
                'country_currency' => 'NG_NGN',
                'icon_url' => env('APP_URL').'/media/icons/flags/nigeria_flag.png',
                'description' => 'Base wallet for Nigerians',
            ];
        });
    }

    public function KEKSH()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Shilling',
                'currency' => 'KSH',
                'country_currency' => 'KE_KSH',
                'icon_url' => env('APP_URL').'/media/icons/flags/shilling_flag.png',
                'description' => 'Base wallet for Shillings',
            ];
        });
    }

    public function BJXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'BJ_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/benin_flag.png',
                'description' => 'Base wallet for Benin',
            ];
        });
    }

    public function BFXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'BF_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/burkina_faso_flag.png',
                'description' => 'Base wallet for Burkina Faso',
            ];
        });
    }

    public function CMXAF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Central African CFA franc',
                'currency' => 'XAF',
                'country_currency' => 'CM_XAF',
                'icon_url' => env('APP_URL').'/media/icons/flags/cameroon_flag.png',
                'description' => 'Base wallet for Cameroon',
            ];
        });
    }

    public function TDXAF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Central African CFA franc',
                'currency' => 'XAF',
                'country_currency' => 'TD_XAF',
                'icon_url' => env('APP_URL').'/media/icons/flags/chad_flag.png',
                'description' => 'Base wallet for Chad',
            ];
        });
    }

    public function CGXAF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Central African CFA franc',
                'currency' => 'XAF',
                'country_currency' => 'TD_XAF',
                'icon_url' => env('APP_URL').'/media/icons/flags/congo_flag.png',
                'description' => 'Base wallet for Congo',
            ];
        });
    }

    public function CDCDF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Central African CFA franc',
                'currency' => 'XAF',
                'country_currency' => 'CD_CDF',
                'icon_url' => env('APP_URL').'/media/icons/flags/congo_flag.png',
                'description' => 'Base wallet for Dr Congo',
            ];
        });
    }

    public function CIXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'CI_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/cote_flag.png',
                'description' => 'Base wallet for Cote d\'Ivoire',
            ];
        });
    }

    public function GAXAF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Central African CFA franc',
                'currency' => 'XAF',
                'country_currency' => 'GA_XAF',
                'icon_url' => env('APP_URL').'/media/icons/flags/gabon_flag.png',
                'description' => 'Base wallet for Gabon',
            ];
        });
    }

    public function GWXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'GW_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/guinea_flag.png',
                'description' => 'Base wallet for Guinea Bissau',
            ];
        });
    }

    public function MLXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'ML_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/mali_flag.png',
                'description' => 'Base wallet for Mali',
            ];
        });
    }

    public function NEXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'NE_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/niger_flag.png',
                'description' => 'Base wallet for Niger',
            ];
        });
    }

    public function RWRWF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Rwandan franc',
                'currency' => 'RWF',
                'country_currency' => 'RW_RWF',
                'icon_url' => env('APP_URL').'/media/icons/flags/rwanda_flag.png',
                'description' => 'Base wallet for Rwanda',
            ];
        });
    }

    public function SNXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'SN_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/senegal_flag.png',
                'description' => 'Base wallet for Senegal',
            ];
        });
    }

    public function UGUGX()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Ugandan Shilling',
                'currency' => 'UGX',
                'country_currency' => 'UG_UGX',
                'icon_url' => env('APP_URL').'/media/icons/flags/uganda_flag.png',
                'description' => 'Base wallet for Uganda',
            ];
        });
    }

    public function TGXOF()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'West African CFA franc',
                'currency' => 'XOF',
                'country_currency' => 'TG_XOF',
                'icon_url' => env('APP_URL').'/media/icons/flags/togo_flag.png',
                'description' => 'Base wallet for Togo',
            ];
        });
    }
}
