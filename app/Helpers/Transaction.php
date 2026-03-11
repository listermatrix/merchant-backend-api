<?php

namespace App\Helpers;

use App\Types\Money;
use App\Models\Wallet;
use App\Models\WalletType;
use App\Models\CashOutMethod;
use App\Models\WalletTransaction;
use App\Models\RevenueTransaction;
use App\Models\ChargeConfiguration;
use App\DataTransferObjects\Transaction\ChargeData;

class Transaction
{


    public static function fee(
        Money|float $money,
        ?CashOutMethod $rail = null,
        string $code = ''
    ): Money {
        if (! $money instanceof Money) {
            $money = new Money($money);
        }
        $charge = ChargeConfiguration::where('code', $code)
            ->where('min_amount', '<=', $money->tofloat())
            ->where(function ($query) use ($money) {
                $query->where('max_amount', '>=', $money->tofloat())
                    ->orWhereNull('max_amount');
            })
            ->orderBy('min_amount', 'desc')
            ->first();

        if (! $charge) {
            return new Money(0);
        }

        if ($charge->charge_type == 'percentage') {
            return $money->percent($charge->amount);
        }

        if ($charge->charge_type == 'flat') {
            return new Money($charge->amount);
        }

        return new Money(0);
    }


    public static function charge(
        Wallet $wallet,
        Money|float $money,
        string $feeCode,
        ?ChargeData $chargeData = null,
        ?bool $notifyUser = true,
    ): RevenueTransaction|bool {
        if (! $money instanceof Money) {
            $money = new Money($money);
        }

        $feeWalletType = WalletType::whereIsInternal(1)
            ->whereCurrency($wallet->currency)
            ->whereCategory('fees')
            ->first();

        if ($feeWalletType) {
            $fee = Transaction::fee($money, code: $feeCode);
            $feeWallet = Wallet::whereWalletTypeId($feeWalletType->id)->first();
            $feeWalletPreviousBalance = $feeWallet->balance;

            $amount = $fee->tofloat();

            if ($amount == 0) {
                //No charge applicable
                return true;
            }

            safelyDebit($wallet->id, $amount);

            safelyCredit($feeWallet->id, $amount);

            $revenueTrx = RevenueTransaction::create([
                'amount' => $amount,
                'wallet_id' => $feeWallet->id,
                'fee_code' => $feeCode,
                'balance_before' => $feeWalletPreviousBalance,
                'balance' => $feeWallet->refresh()->balance,
                'position' => 'credit',
                'currency' => $wallet->currency,
            ]);

            //fee wallet owner transaction
            $trx = WalletTransaction::create([
                'transaction_id' => makeGenericId(),
                'amount_in_figures' => $amount,
                'transaction_amount' => makeStringMoney($amount),
                'wallet_id' => $feeWallet->id,
                'balance_before' => $feeWalletPreviousBalance,
                'wallet_balance' => $feeWallet->balance,
                'position' => 'credit',/** changed it from debit to credit because the fee wallet is receiving money, needs re-discussion */
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

            return $revenueTrx;
        }

        return false;
    }


}
