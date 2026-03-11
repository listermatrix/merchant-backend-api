<?php

namespace Database\Factories;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition()
    {
        $dynamicData = $this->getDynamicFields($this->faker->randomElement(['credit']));

        $walletId = 11;

        $wallet = Wallet::whereId($walletId)->first();
        $balanceBefore = $wallet->balance; 

        $this->action($wallet, $dynamicData); 

        $balanceAfter = $wallet->refresh()->balance;

        Arr::set($dynamicData, 'balance_before', $balanceBefore); 
        Arr::set($dynamicData, 'wallet_balance', $balanceAfter);
        Arr::set($dynamicData, 'wallet_id', $wallet->id);

        return $dynamicData;
    }

    private function action($wallet, $data)
    {
        $position = Arr::get($data, 'position'); 
        $amount   = Arr::get($data, 'amount_in_figures');
        $status   = Arr::get($data, 'status'); 

        if($position == 'credit' && $status == 'successful') 
        {
            safelyCredit($wallet, $amount);
        } else if ($position == 'debit' && $status == 'successful'){
            safelyDebit($wallet, $amount);
        }
    }

    private function getDynamicFields($position)
    {
        $randomAmount = [1,10,20,30,35,40,50,100,120]; 
        $amountInFigures = $this->faker->randomElement($randomAmount);

        $default = [
            'card_number' => null,
            'brijx_id' => 0, 
            'momo_contact' => null, 
            'bank_account' => null, 
            'transaction_id' => makeTellerId(),
            'transaction_amount' => makeStringMoney($amountInFigures),
            'amount_in_figures' => $amountInFigures,
            'position' => '', 
            'debit' => '', 
            'credit' => '', 
            'remark' => '',
            'currency' => 'GHS',
            'transaction_method' => '',
            'transaction_channel' => '',
            'channel_id' => 0, 
            'meta' => [],
            'target_client_id' => 0,
            'source_client_id' => 0,
            'internal_escrow_id' => 0,
            'mifos_transaction_sync_status' => 0, 
            'wallet_id' => 0,
            'status' => '',
            'app_fee' => 0,
            'revenue_transaction_id' => 0,
            'status_reason' =>  '',
            'expires_at' => 0,
            'wallet_balance' => 0,
            'balance_before' => 0,
            'settled_at' => null, 
        ]; 

     
        $positions = [
            'credit' => $this->faker->randomElement([array_merge($default, [
                'position' => 'credit', 
                'remark' => 'fund', 
                'transaction_channel' => 'mtnghana',
                'transaction_method' => 'momo',
                'status' => 'successful',
                'status_reason' => 'Wallet funded successfully', 
                'amount_in_figures' => $amountInFigures
            ]), array_merge($default, [
                'position' => 'credit', 
                'remark' => 'fund', 
                'transaction_channel' => 'mtnghana',
                'transaction_method' => 'momo',
                'status' => 'failed',
                'status_reason' => 'Wallet funding failed', 
                'amount_in_figures' => $amountInFigures
            ]), array_merge($default, [
                'position' => 'credit', 
                'remark' => 'paywithbrij', 
                'transaction_channel' => 'mtnghana',
                'transaction_method' => 'momo',
                'status' => 'successful',
                'status_reason' => 'Payment collection successful', 
                'amount_in_figures' => $amountInFigures
            ])]), 
            'debit' => $this->faker->randomElement([array_merge($default, [
                'position' => 'debit', 
                'remark' => 'cashout', 
                'transaction_channel' => 'mtnghana',
                'transaction_method' => 'momo',
                'status' => 'successful',
                'status_reason' => 'Cashout completed successfully', 
                'amount_in_figures' => $amountInFigures
            ]), array_merge($default, [
                'position' => 'debit', 
                'remark' => 'billpayment', 
                'transaction_channel' => 'airtime',
                'transaction_method' => 'billpayment',
                'status' => 'successful',
                'status_reason' => 'Bill paid successfully', 
                'amount_in_figures' => $amountInFigures
            ]), 
            array_merge($default, [
                'position' => 'debit', 
                'remark' => 'billpayment', 
                'transaction_channel' => 'airtime',
                'transaction_method' => 'billpayment',
                'status' => 'failed',
                'status_reason' => 'Bill payment failed', 
                'amount_in_figures' => $amountInFigures
            ])])
        ];

        return Arr::get($positions, $position);
    }
}
