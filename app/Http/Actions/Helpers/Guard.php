<?php

namespace App\Http\Actions\Helpers;

use App\Exceptions\CustomBadRequestException;
use App\Exceptions\TransactionLimitException;
use App\Models\CashOutMethod;
use App\Models\ChargeConfiguration;
use App\Models\MerchantChargeConfiguration;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Domain\SafetyMonitors\DTO\MonitorData;
use Domain\SafetyMonitors\Factories\MonitorFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class Guard
{
    public static function againstMaxLimit(
        CashOutMethod $paymentMethod,
        Wallet $wallet,
        float $amount,
        string $trxType
    ) {
        $authClient = Auth::user()->client_id;
        $trxMethod = $paymentMethod->fund_type;
        $trxChannel = $paymentMethod->channel;
        $walletType = $wallet->wallettype->id;

        $merchantChargeOverride = MerchantChargeConfiguration::query()
            ->where('client_id', $authClient)
            ->first();

        if ($merchantChargeOverride) {
            $chargeConfig = MerchantChargeConfiguration::query()
                ->join(
                    'charge_configurations',
                    'merchant_charge_configurations.charge_configuration_id',
                    'charge_configurations.id'
                )
                ->where('merchant_charge_configurations.client_id', $authClient)
                ->where('charge_configurations.transaction_method', $trxMethod)
                ->where('charge_configurations.wallet_type_id', $walletType)
                ->select('merchant_charge_configurations.*')
                ->first();
        } else {

            if ($walletType == 3 && $trxMethod == 'kebank') {
                $trxChannel = 'kcbeft';
            }

            $chargeConfig = ChargeConfiguration::query()
                ->where('transaction_type', $trxType)
                ->where('transaction_method', $trxMethod)
                ->where('transaction_channel', $trxChannel)
                ->where('wallet_type_id', $walletType)
                ->where('status', 'active')
                ->first();
        }

        if ($chargeConfig) {
            if ($amount > $chargeConfig->transaction_limit) {
                throw new TransactionLimitException(
                    'You have a transaction limit of '.$chargeConfig->transaction_limit.' per transaction'
                );
            }

            $limitData = [
                'clientId' => $authClient,
                'trxMethod' => $trxMethod,
                'trxChannel' => $trxChannel,
                'amount' => $amount,
                'walletId' => $wallet->id,
                'monitorType' => 'DAILY_TRX_LIMIT',
                'meta' => [],
            ];

            $sumToday = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->whereDate('created_at', Carbon::now()->toDateString())
                ->whereRemark($trxType)
                ->whereIn('status', ['success', 'successful'])
                ->sum('amount_in_figures');

            self::handleLimitReached($sumToday, $limitData, $amount, $chargeConfig);
        }
    }

    private static function handleLimitReached(
        float $daysSum,
        array $limitData,
        float $amount,
        MerchantChargeConfiguration|ChargeConfiguration $chargeConfig
    ) {
        $dailyLimit = $chargeConfig->daily_limit;
        $exceedDaysLimit = $daysSum + $amount;

        if ($exceedDaysLimit > $dailyLimit) {
            MonitorFactory::makeHandler(new MonitorData(...$limitData))->handle();

            throw new TransactionLimitException(
                'Failed! You cant exceed daily transaction limit of '.$dailyLimit
            );
        }
    }

    public static function againstFullWithdrawal(
        Wallet $wallet,
    ) {
        $balanceReservedConfig = [
            6467 => 10000,
            //6396 => 3518331.5,
            //6386 => 9700000,
            //6390 => 50000000,
            //6392 => 25000000
        ];

        $clientId = Auth::user()->client_id;

        $reservedBalance = Arr::get($balanceReservedConfig, $clientId);

        if ($reservedBalance) {
            throw_if(
                $wallet->balance <= $reservedBalance,
                new CustomBadRequestException('Sorry! You have reached your payout limit. Kindly reach out to customer support.')
            );
        }
    }
}
