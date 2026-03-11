<?php

namespace App\Http\Services\Traits;

use Illuminate\Support\Arr;

trait UserTransactionNotificationContructionTrait 
{
    public function constructSMS($walletTrxn, $message = ''): string
    {
        $payer = $this->getPayerName($walletTrxn);

        $message = !blank($message) ? $message : "Payment received successful\n\n";

        if( !blank($payer) ) {
          $message .= "From: $payer\n";
        }

        $message .= "Trxn ID: {$walletTrxn->transaction_id}\n";

        $message .= "Trxn Type: " . getWalletTransactionRemark($walletTrxn->remark) . "\n";

        $message .= "CR Amt: {$walletTrxn->currency}" . formatAmountWithCommas( $walletTrxn->amount_in_figures ) . "\n";

        if( $walletTrxn->fee_bearer === 'merchant' ) {
            $message .= "Trxn Fee: {$walletTrxn->currency}" . formatAmountWithCommas($walletTrxn->app_fee) . "\n";
        }

        $message .= "Bal: {$walletTrxn->currency}" . formatAmountWithCommas($walletTrxn->wallet->balance) . "\n";

        $message .="DT: {$walletTrxn->created_at->format('Y-m-d h:i:s a')}\n";

        return $message;
    }


    private function getPayerName($walletTrxn)
    {
        $payer = Arr::get($walletTrxn->meta, 'customer.name');

        if( ! blank($payer) ) {

            return mb_strimwidth(( $payer ), 0, 20, '...');
        }

        return null;
    }
}