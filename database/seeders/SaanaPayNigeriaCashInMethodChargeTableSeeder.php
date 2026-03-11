<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WalletType;
use Illuminate\Support\Arr;
use App\Models\CashInMethod;
use Illuminate\Database\Seeder;
use App\Models\ChargeConfiguration;

class SaanaPayNigeriaCashInMethodChargeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        $user = User::where('email', 'system@brij.money')->first();
        
        $nigeriaCashInMethodChannels = [
            //paywithbrij
            [ 'channel' =>'directbanktransfer', 'code' => 'BFCAS001', 'transaction_type' => 'collection-api', 'service_provider_rate' => 60, 'brij_marked_up_rate' => 40],
            [ 'channel' =>'card', 'code' => 'BFCAS002', 'transaction_type' => 'collection-api', 'service_provider_rate' => 60, 'brij_marked_up_rate' => 40 ],
           
            //paymerchant
            [ 'channel' =>'directbanktransfer', 'code' => 'BFCS001', 'transaction_type' => 'collection', 'service_provider_rate' => 60, 'brij_marked_up_rate' => 40],
            [ 'channel' =>'card', 'code' => 'BFCS002', 'transaction_type' => 'collection', 'service_provider_rate' => 60, 'brij_marked_up_rate' => 40],
        ];

        $nigeriaFeeWalletType = WalletType::whereIsInternal(0)
                                    ->whereCurrency('NGN')
                                    ->first();

        if(blank($nigeriaFeeWalletType)){
            $this->command->error('Wallet type for NGN not found');
            return;
        }

        foreach ($nigeriaCashInMethodChannels as $nigeriaCashInMethodChannel){

            $method = CashInMethod::where('channel', Arr::get($nigeriaCashInMethodChannel, 'channel'))->first();

            if(!blank($method)){

                ChargeConfiguration::updateOrCreate(
                    [
                        'code' => Arr::get($nigeriaCashInMethodChannel, 'code'),
                    ],
                    [
                        'code' => Arr::get($nigeriaCashInMethodChannel, 'code'),
                        'charge_name' => $method->channel,
                        'transaction_channel' => $method->channel,
                        'charge_type' => 'flat',
                        'service_provider_rate' => Arr::get($nigeriaCashInMethodChannel, 'service_provider_rate'),
                        'brij_marked_up_rate' => Arr::get($nigeriaCashInMethodChannel, 'brij_marked_up_rate'),
                        'amount' => (float) ( Arr::get($nigeriaCashInMethodChannel, 'service_provider_rate') + Arr::get($nigeriaCashInMethodChannel, 'brij_marked_up_rate') ),
                        'transaction_method' => 'momo',
                        'transaction_type' => Arr::get($nigeriaCashInMethodChannel, 'transaction_type'),
                        'wallet_type_id' => $nigeriaFeeWalletType->id,
                        'daily_limit' => 0,
                        'transaction_limit' => 0,
                        'channel_id' => $method->id,
                        'processor' => 'saanapay',
                        'created_by' => $user->id,
                    ]
                );
            }
        }

    }
}
