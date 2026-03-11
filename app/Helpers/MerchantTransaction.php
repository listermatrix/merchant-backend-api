<?php

namespace App\Helpers;

use App\DataTransferObjects\Transaction\ChargeData;
use App\Events\TransactionCharged;
use App\Exceptions\CustomBadRequestException;
use App\Models\BrijMfsRevenueTransaction;
use App\Models\ChargeConfiguration;
use App\Models\MerchantPaymentChannelSetting;
use App\Models\MfsRateConfig;
use App\Models\RevenueTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletType;
use App\Types\Money;
use Domain\Fund\Models\Fund;

class MerchantTransaction
{
    public static function fee(
        Money|float $money,
        string $code = '',
        ?MerchantPaymentChannelSetting $chargeConfig = null
    ): Money {
        if (!$money instanceof Money) {
            $money = new Money($money);
        }

        if (!$chargeConfig) {
            return new Money(0);
        }

        if ($chargeConfig->charge_type == 'percentage') {
            return $money->percent($chargeConfig->amount);
        }

        if ($chargeConfig->charge_type == 'flat') {
            return new Money($chargeConfig->amount);
        }

        return new Money(0);
    }

    public static function charge(
        WalletTransaction|Fund $transaction,
        string $feeCode,
        ChargeData $chargeData,
        bool $notifyUser = false,
    ): RevenueTransaction|bool {

        $money = $transaction->amount_in_figures;

        if (!$money instanceof Money) {
            $money = new Money($money);
        }

        $wallet = Wallet::whereId($transaction->wallet_id)->first();

        $channelId = $transaction->channel_id;
        $clientId = $transaction->source_client_id;

        $chargeConfig = MerchantPaymentChannelSetting::whereFeeCode($feeCode)
            ->whereChannelId($channelId)
            ->whereClientId($clientId)
            ->whereWalletTypeId($wallet->wallet_type_id)
            ->where('min_amount', '<=', $money->tofloat())
            ->where(function ($query) use ($money) {
                $query->where('max_amount', '>=', $money->tofloat())
                    ->orWhereNull('max_amount');
            })
            ->orderBy('min_amount', 'desc')
            ->first();

        $feeWalletType = WalletType::whereIsInternal(1)
            ->whereCurrency($wallet->currency)
            ->whereCategory('fees')
            ->first();

        if ($feeWalletType) {
            $fee = self::fee($money, $feeCode, $chargeConfig);
            $feeWallet = Wallet::whereWalletTypeId($feeWalletType->id)->first();

            $feeWalletPreviousBalance = $feeWallet->balance;

            $amount = $fee->tofloat();

            if ($amount == 0) {
                //No charge applicable
                return true;
            }

            // Rounded up because processors don't account for cent values.
            // The initial request was rounded up, so the charge should also use ceil().
            if( in_array($feeWalletType->currency, ['KSH', 'TZS', 'NGN', 'USD']) ){
                $amount = ceil( $amount );
            }


            if( in_array($chargeConfig->feature, ['collection-api', 'collection']) ) {
                if( $chargeConfig->charge_bearer === 'merchant' ) {

                    safelyDebit($wallet->id, $amount);

                    safelyCredit($feeWallet->id, $amount);

                    // necessary because amount_in_figures
                   // was already added to wallet_balance in paymerchant success response handlers
                   $netCollectionAmount = $transaction->amount_in_figures - $amount;
                   $transaction->amount_in_figures = $netCollectionAmount;
                   $transaction->transaction_amount = makeStringMoney( $netCollectionAmount );
                   $transaction->wallet_balance -= $amount;

                } else {

                    // since customer beared the transaction fee, the fee amount was already accounted by external aggregator
                    // during the request so we just need to update credit fee account and maintain
                    safelyCredit($feeWallet->id, $amount);
                }

            } else{

                safelyDebit($wallet->id, $amount);

                safelyCredit($feeWallet->id, $amount);
            }

            $transaction->app_fee = $amount;
            $transaction->fee_bearer = $chargeConfig->charge_bearer; //log who beared fee
            $transaction->fee_value =  $chargeConfig->amount;
            $transaction->fee_type = $chargeConfig->charge_type;
            $transaction->fee_code_applied = $chargeConfig->fee_code;
            $transaction->brij_marked_up_rate = $chargeConfig->brij_marked_up_rate;
            $transaction->service_provider_rate = $chargeConfig->service_provider_rate;
            $transaction->save();
            $transaction->refresh();

            $revenueTrx = RevenueTransaction::create([
                'amount' => $amount,
                'wallet_id' => $feeWallet->id,
                'fee_code' => $feeCode,
                'balance_before' => $feeWalletPreviousBalance,
                'balance' => $feeWallet->refresh()->balance,
                'position' => 'credit',
                'description' => $chargeData->revenueDescription,
                'currency' => $wallet->currency,
                'charged_on_trx_id' => $transaction->transaction_id,
            ]);

            //fee wallet owner transaction
            $trx = WalletTransaction::create([
                'transaction_id' => makeGenericId(),
                'amount_in_figures' => $amount,
                'transaction_amount' => makeStringMoney($amount),
                'wallet_id' => $feeWallet->id,
                'balance_before' => $feeWalletPreviousBalance,
                'wallet_balance' => $feeWallet->balance,
                'position' => 'credit',
                'currency' => $feeWallet->currency,
                'status_reason' => $chargeData->statusReason,
                'remark' => $chargeData->remark,
                'status' => 'successful',
                'source_client_id' => $wallet->client_id,
                'target_client_id' => $feeWallet->client_id,
                'revenue_transaction_id' => $revenueTrx->id,
                'transaction_method' => $chargeData->transactionMethod,
                'transaction_channel' => $chargeData->transactionChannel,
            ]);

            //User charge transaction
            WalletTransaction::create([
                'transaction_id' => makeGenericId(),
                'amount_in_figures' => $amount,
                'transaction_amount' => makeStringMoney($amount),
                'wallet_id' => $wallet->id,
                'balance_before' => $wallet->balance,
                'wallet_balance' => $wallet->refresh()->balance,
                'position' => 'debit',
                'currency' => $wallet->currency,
                'status_reason' => $chargeData->statusReason,
                'remark' => 'charge',
                'status' => 'successful',
                'source_client_id' => $wallet->client_id,
                'target_client_id' => $wallet->client_id,
                'revenue_transaction_id' => $revenueTrx->id,
                'transaction_method' => $chargeData->transactionMethod,
                'transaction_channel' => $chargeData->transactionChannel,
            ]);

            if ($notifyUser) {
                TransactionCharged::dispatch($chargeData, $wallet, $trx);
            }

            if( in_array($chargeConfig->feature, ['collection-api', 'collection']) ) {
                self::storeRevenueSplit($transaction, $chargeConfig, $amount);
            }

            return $revenueTrx;
        }

        return false;
    }

    public static function recordMfsCharge(
        Wallet $wallet,
        Money|float $money,
        string $feeCode,
        WalletTransaction $walletTx
    ) {
        if (!$money instanceof Money) {
            $money = new Money($money);
        }

        $feeWalletType = WalletType::whereIsInternal(1)
            ->whereCurrency($wallet->currency)
            ->whereCategory('fees')
            ->first();

        if ($feeWalletType) {
            $amount = $money->tofloat();

            $feeWallet = Wallet::whereWalletTypeId($feeWalletType->id)->first();
            $feeWalletPreviousBalance = $feeWallet->balance;

            if ($feeCode == 'BMFSP01') {
                $sendingAmount = $walletTx->amount_in_figures;

                $config = MfsRateConfig::where('from', '<=', $sendingAmount)
                    ->where('to', '>=', $amount)
                    ->first();

                $brijShare = ($config->brij_share / 100) * $amount;
                $mfsShare = ($config->mfs_share / 100) * $amount;

                $amount = $brijShare;

                BrijMfsRevenueTransaction::create([
                    'transaction_id' => $walletTx->transaction_id,
                    'amount' => $walletTx->app_fee,
                    'brij_share' => $brijShare,
                    'mfs_share' => $mfsShare,
                ]);
            }

            safelyCredit($feeWallet->id, $amount);

            $revenueTrx = RevenueTransaction::create([
                'amount' => $amount,
                'wallet_id' => $feeWallet->id,
                'fee_code' => $feeCode,
                'balance_before' => $feeWalletPreviousBalance,
                'balance' => $feeWallet->refresh()->balance,
                'position' => 'credit',
                'currency' => $wallet->currency,
                'description' => 'Revenue generated from MFS Payout',
            ]);

            $trx = WalletTransaction::create([
                'transaction_id' => makeGenericId(),
                'amount_in_figures' => $amount,
                'transaction_amount' => makeStringMoney($amount),
                'wallet_id' => $feeWallet->id,
                'balance_before' => $feeWalletPreviousBalance,
                'wallet_balance' => $feeWallet->balance,
                'position' => 'credit',
                'currency' => $feeWallet->currency,
                'status_reason' => 'Revenue generated from MFS Payout',
                'remark' => 'charge',
                'status' => 'successful',
                'source_client_id' => $wallet->client_id,
                'target_client_id' => $feeWallet->client_id,
                'revenue_transaction_id' => $revenueTrx->id,
                'transaction_method' => '',
                'transaction_channel' => '',
            ]);
        }
    }

    public static function feeConfig(string $feeCode): ChargeConfiguration
    {
        $charge = ChargeConfiguration::query()
            ->orWhere('code', $feeCode)
            ->first();

        throw_if(
            !$charge,
            new CustomBadRequestException('No charge found with the code specified.')
        );

        return $charge;
    }

    public static function reverseCharge(
        Wallet $wallet,
        Money|float $money,
        Money|float $fee
    ) {
        if (!$money instanceof Money) {
            $money = new Money($money);
        }

        $feeWalletType = WalletType::whereIsInternal(1)->whereCurrency($wallet->currency)->first();

        if ($feeWalletType) {
            $feeWallet = Wallet::whereWalletTypeId($feeWalletType->id)->first();

            safelyDebit($feeWallet->id, $fee->tofloat());

            safelyCredit($wallet->id, $fee->tofloat());
        }
    }

    public static function storeRevenueSplit(WalletTransaction|Fund $transaction, MerchantPaymentChannelSetting $setting, $feeCharge): void
    {
        if ($setting->charge_type === 'percentage') {
            $processShare = $transaction->amount_in_figures * $setting->service_provider_rate / 100;
            $brijShare = $feeCharge - $processShare;
        } else {
            $processShare = $setting->service_provider_rate;
            $brijShare = $feeCharge - $processShare;
        }

        $transaction->revenueSplit()->create([
            'fee_code' => $setting->fee_code,
            'brij_share' => $brijShare,
            'processor' => $setting->processor,
            'amount' => $feeCharge,
            'processor_share' => $processShare,
            'currency' => $transaction->currency,
            'feature' => $setting->feature,
            'service_provider_rate' => $setting->service_provider_rate,
            'brij_marked_up_rate' => $setting->brij_marked_up_rate,
            'charge_type' => $setting->charge_type
        ]);
    }
}
