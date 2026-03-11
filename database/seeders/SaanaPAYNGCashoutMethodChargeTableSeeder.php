<?php

namespace Database\Seeders;

use App\Models\CashOutMethod;
use App\Models\User;
use App\Models\WalletType;
use Illuminate\Support\Arr;
use App\Models\CashInMethod;
use Illuminate\Database\Seeder;
use App\Models\ChargeConfiguration;

class SaanaPAYNGCashoutMethodChargeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $user = User::where('email', 'system@brij.money')->first();


        $getSaanaPayChannels = $this->getSaanaPayBankData('cashout');


        $ngFeeWalletType = WalletType::whereIsInternal(0)
            ->whereCurrency('NGN')
            ->first();

        if (blank($ngFeeWalletType)) {
            $this->command->error('Wallet type for NGN not found');
            return;
        }

        foreach ($getSaanaPayChannels as $getSaanaPayChannel) {

            $method = CashOutMethod::query()->where('channel', Arr::get($getSaanaPayChannel, 'channel'))->first();
            if (!blank($method)) {
                ChargeConfiguration::query()->updateOrCreate(
                    [
                        'code' => Arr::get($getSaanaPayChannel, 'channel'),
                    ],
                    [
                        'code' => Arr::get($getSaanaPayChannel, 'code'),
                        'charge_name' => $method->channel,
                        'transaction_channel' => $method->channel,
                        'charge_type' => 'flat',
                        'service_provider_rate' => Arr::get($getSaanaPayChannel, 'service_provider_rate'),
                        'brij_marked_up_rate' => Arr::get($getSaanaPayChannel, 'brij_marked_up_rate'),
                        'amount' => (float) (Arr::get($getSaanaPayChannel, 'service_provider_rate') + Arr::get($getSaanaPayChannel, 'brij_marked_up_rate')),
                        'transaction_method' => 'ngbank',
                        'transaction_type' => Arr::get($getSaanaPayChannel, 'transaction_type'),
                        'wallet_type_id' => $ngFeeWalletType->id,
                        'daily_limit' => 1000000,
                        'transaction_limit' => 1000000,
                        'channel_id' => $method->id,
                        'processor' => 'saanapay',
                        'created_by' => $user->id,
                    ]
                );
            }
        }
    }

    private function getSaanaPayBankData($transactionType): array
    {
        $cashOutMethods = CashOutMethod::query()->where([
            'processor' => 'saanapay',
            'fund_type' => 'ngbank',
        ])->whereJsonContains('supported_currencies', 'NGN')->get();

        $data = $cashOutMethods->map(function ($cashOutMethod) use ($transactionType) {
            return [
                'channel' => $cashOutMethod->channel,
                'code' => $cashOutMethod->charge_code,
                'transaction_type' => $transactionType,
                'service_provider_rate' => 17,
                'brij_marked_up_rate' => 8
            ];
        });

        return $data->toArray();
    }
}
