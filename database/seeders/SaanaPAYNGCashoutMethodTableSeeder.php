<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Support\Str;
use App\Models\CashOutMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Models\CountryCashOutMethod;
use Illuminate\Support\Facades\Http;
use App\Models\ExternalPaymentProvider;
use App\Models\ExternalPaymentProviderCashOutMethod;

class SaanaPAYNGCashoutMethodTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $banks = $this->getAllBanks();
        
        $methods = array_map(function ($bank) {
                $slugChannel = Str::slug($bank['name'], '');

                if (!str_contains($slugChannel, 'nigeria')) {
                    $slugChannel .= 'nigeria';
                }

                $slugIcon = Str::slug($bank['name'], '_');

                return [
                    'name' => $bank['name'],
                    'description' => $bank['name'] . ' cashout for NG',
                    'channel' => strtolower($slugChannel),
                    'fund_type' => 'ngbank',
                    'priority' => 2,
                    'code' => $bank['code'],
                    'supported_currencies' => ['NGN'],
                    'icon_url' => "/media/icons/saanapay/{$slugIcon}.png",
                    'charge_code' => 'BFCNSP01', // Set your charge code logic here if dynamic
                    'processor' => 'saanapay',
                    'supports_cashout' => true,

                ];
            }, $banks);

        $saanaPay = ExternalPaymentProvider::whereName('SaanaPAY')->first();
        $country = Country::where('name', 'Nigeria')->first();

        if ($saanaPay) {
            collect($methods)->each(function ($method) use ($saanaPay, $country) {
                $channel = CashOutMethod::updateOrCreate([
                    'code' => $method['code'],
                    'charge_code' => $method['charge_code'],
                ], $method);

                $data = [
                    'external_payment_provider_id' => $saanaPay->id,
                    'cash_out_method_id' => $channel->id,
                    'charge_code' => $method['charge_code'],
                    'code' => $method['code'],
                ];

                // firstOrCreate first run, then lock to firstOrcreate
                ExternalPaymentProviderCashOutMethod::query()->updateOrCreate([
                    'external_payment_provider_id' => $saanaPay->id,
                    'cash_out_method_id' => $channel->id,
                ], $data);

                $countryCashout = [
                    'country_id' => $country->id,
                    'cash_out_method_id' => $channel->id,
                ];

                CountryCashOutMethod::query()->firstOrCreate($countryCashout, $countryCashout);

                //map cashout method to payment provider
                (new GenericCashoutMethodExternalPaymentProviderMappingSeeder('SaanaPAY', 'Cashout', 'NG_NGN'))->run();
            });

            $this->call([
                SaanaPAYNGCashoutMethodChargeTableSeeder::class,
            ]);
        } else {
            Log::info('SaanaPay processor not seeded yet. Please run ExternalPaymentProviderTableSeeder first.');
        }
    }

    public function getAllBanks()
    {
        $baseUrl = config('services.saanapay.payout.base_url');
        $apiKey = config('services.saanapay.payout.api_token');

        return Http::asJson()->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->get($baseUrl . '/banks')->json();
    }
}
