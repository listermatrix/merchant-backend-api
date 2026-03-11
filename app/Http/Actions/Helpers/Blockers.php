<?php

namespace App\Http\Actions\Helpers;

use App\Exceptions\CustomBadRequestException;
use App\Exceptions\CustomModelNotFoundException;
use App\Exceptions\InvalidTransactionAmountException;
use App\Exceptions\KycDetailNotSetException;
use App\Exceptions\KycFailedException;
use App\Exceptions\SendingMoneyToSelfUnsupportedException;
use App\Exceptions\SomethingWentWrongException;
use App\Exceptions\ValidationFailedException;
use App\Models\BraasTransaction;
use App\Models\Client;
use App\Models\User;
use App\Models\UserFeatureAuthorization;
use App\Models\UserIdentity;
use App\Models\Wallet;
use App\Models\WalletAbility;
use App\Models\WalletKycLevel;
use App\Models\WalletKycLevelWalletAbility;
use App\Models\WalletTransaction;
use App\Models\WalletType;
use Carbon\Carbon;
use Domain\BRaaS\Inbound\Exceptions\InvalidMpesaAmount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Blockers
{
    /**
     * Blocks users from inputting decimal
     * mpesa amounts
     *
     * @return void
     */
    public static function isValidMpesaAmt($amount)
    {
        $decimal = $amount - floor($amount);

        throw_if(
            $decimal !== 0.0,
            new InvalidMpesaAmount('Invalid Mpesa amount. Ensure there are no decimals.')
        );
    }

    /**
     * Blocks users from inputting negative
     * amount
     *
     * @return void
     */
    public static function preventNegative()
    {
        if (request()->amount <= 0) {
            throw new CustomBadRequestException('Invalid amount');
        }
    }

    public static function preventSendingSelfPayment($receiving_wallet = null)
    {
        $logged_in_phone = Auth::user()->phone;

        if (! $receiving_wallet) {
            $receiving_wallet = Wallet::whereUuid(request()->receiving_wallet)->whereStatus('active')->first();

            if (blank($receiving_wallet)) {
                throw new CustomBadRequestException('The wallet is temporarily inactive');
            }
        }

        if (blank($receiving_wallet)) {
            throw new CustomModelNotFoundException('No receiving wallet found with the id specified.');
        }

        $receiving_users_phone = $receiving_wallet->client->user->phone;

        if ($receiving_users_phone == $logged_in_phone) {
            throw new SendingMoneyToSelfUnsupportedException;
        }

        return false;
    }

    public static function validMinCashInAmount($wallet, $amount)
    {
        // $cedi_wallet = self::getWalletType($currency);

        // if($amount < $cedi_wallet->minimum_cashin ) throw new InvalidTransactionAmountException("Invalid cash in amount");

        return true;
    }

    public static function validMinCashOutAmount($wallet, $amount)
    {
        // $cedi_wallet = self::getWalletType($currency);

        // if($amount < $cedi_wallet->minimum_cashout ) throw new InvalidTransactionAmountException("Invalid cash out amount");

        return true;
    }

    public static function hasZeeepayWallet($wallet)
    {
        if (! validateZeepayAccount(Auth::user()) || $wallet->user_wallet_kyc_level < 1) {
            throw new CustomBadRequestException('User doe not have a valid GHS Account');
        }

        return true;
    }

    public static function validKycLevel()
    {
        if (Auth::user()->onboarding_stage < 4) {
            throw new KycFailedException('Please verify your account before you proceed');
        } else {
            return true;
        }
    }

    public static function validDOB()
    {
        if (Auth::user()->dob == null) {
            throw new KycDetailNotSetException('Please add your date of birth to cash out');
        } else {
            return true;
        }
    }

    public static function validWalletType()
    {
        $wallets = Auth::user()->client->wallets;
        dd($wallets);
    }

    private static function getWalletType($currency)
    {
        $wallet = WalletType::whereCurrency($currency)
            ->where('is_internal', 0)->first();

        if (blank($wallet)) {
            throw new CustomModelNotFoundException("$currency wallet type does not exist");
        }

        return $wallet;
    }

    public static function validDeviceId($device_id)
    {
        if (is_null(Auth::user()->device_id) || empty(Auth::user()->device_id)) {
            $user = User::query()->find(Auth::user()->id);
            $user->device_id = $device_id;
            $user->save();

            return true;
        } elseif ($device_id != Auth::user()->device_id) {
            throw new SomethingWentWrongException('Device does not match your logged in device');
        } else {
            return true;
        }
    }

    public static function validateTransactionTime()
    {
        // TODO: move to config
        $exemptedMerchants = [6467];
        $clientId = Auth::user()->client_id;

        //Remove merchants from payout velocity check
        if (in_array($clientId, $exemptedMerchants)) {
            return true;
        }

        $last_transaction = WalletTransaction::query()
            ->where('source_client_id', request()->user()->client->id)
            ->orderByDesc('updated_at')->first();

        //If there was a previous transaction and it belongs to an individual user

        if ($last_transaction) {
            if (Carbon::parse($last_transaction->updated_at)->diffInSeconds(Carbon::now()) < config('brij.delay_inbetween_request')) {
                throw new CustomBadRequestException('Transaction cannot be completed at this time');
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    public static function checkFeatureEnabled($type)
    {
        $diabled_feature = UserFeatureAuthorization::query()
            ->where('user_id', Auth::user()->id)
            ->where('transaction_type', $type)->first();

        if ($diabled_feature) {
            throw new SomethingWentWrongException('This feature is currently disabled. Contact admin');
        } else {
            return true;
        }
    }

    public static function checkSuspectUser($identity_doc)
    {
        $check_identity = UserIdentity::query()->where('value', $identity_doc)->first();
        if ($check_identity) {
            $exitsing_user = User::query()->find($check_identity->user_id);
            $existing_client = Client::query()->find($exitsing_user->client_id);
            $current_client = Client::query()->find(Auth::user()->client_id);

            if ($existing_client->active_state != 'active') {
                $current_client->active_state = 'declined';
                $current_client->save();

                throw new SomethingWentWrongException('User details have been flagged as suspicious');
            } else {
                return 'pass';
            }
        } else {
            return 'pass';
        }
    }

    public static function checkSuspectEmail()
    {
        $rm_mail = explode('@', Auth::user()->email);
        $rm_numerics = preg_replace('/\d+/u', '', $rm_mail[0]);
        $check_user_count = User::query()->where('email', 'like', '%'.$rm_numerics.'%')
            ->where('is_merchant', 'no')->count();

        $check_merchant_count = User::query()->where('email', 'like', '%'.$rm_numerics.'%')
            ->where('is_merchant', 'yes')->count();

        $current_client = Client::query()->find(Auth::user()->client_id);

        if ($check_user_count > 1 || $check_merchant_count > 1) {
            $current_client->active_state = 'declined';
            $current_client->save();

            throw new SomethingWentWrongException('Your email has been flagged as suspicious');
        } else {
            return 'pass';
        }
    }

    /**
     * Check if user is still in a transacting state
     *
     * @return bool
     */
    public static function checkUserTransactState($transaction_type)
    {
        $user_id = Auth::user()->id;
        if (Cache::has('trx_state_'.$transaction_type.'_'.$user_id)) {
            $check_state = Cache::get('trx_state_'.$transaction_type.'_'.$user_id);
            if ($check_state) {
                throw new SomethingWentWrongException('A transaction is still being processed');
            } else {
                Cache::put('trx_state_'.$transaction_type.'_'.$user_id, 1);

                return true;
            }
        } else {
            Cache::put('trx_state_'.$transaction_type.'_'.$user_id, 1);

            return true;
        }
    }

    /**
     * update transaction state after complete transaction
     *
     * @return bool
     */
    public static function updateUserIsTransactingState($transaction_type, $user_id)
    {
        if (Cache::has('trx_state_'.$transaction_type.'_'.$user_id)) {
            Log::channel('kcb')->info('RM TRX STATE');
            Cache::forget('trx_state_'.$transaction_type.'_'.$user_id);

            return true;
        } else {
            return false;
        }
    }

    public static function reconBeforeCashout($wallet)
    {
        $merchant_excempted_client_ids = [
            571, 762, 870, 1046, 558,
        ];

        if (in_array($wallet->client_id, $merchant_excempted_client_ids)) {
            return true;
        }

        $cash_out_sum_today = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereDate('created_at', '<=', Carbon::now()->toDateString())
            ->whereIn('remark', [
                'cashout', 'sent', 'sent_payment_to_mobile', 'brijx_escrow',
                'airtime', 'airtime_purchase', 'voucher', 'data', 'paid_with_brij', 'paidmerchant',
            ])
            ->whereIn('status', ['success', 'successful'])
            ->sum('amount_in_figures');

        $cash_out_fee_today = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereDate('created_at', '<=', Carbon::now()->toDateString())
            ->whereIn('remark', [
                'cashout', 'sent', 'sent_payment_to_mobile', 'brijx_escrow',
                'airtime', 'airtime_purchase', 'voucher', 'data', 'paid_with_brij', 'paidmerchant',
            ])
            ->whereIn('status', ['success', 'successful'])
            ->sum('app_fee');

        $total_out_today = $cash_out_sum_today + $cash_out_fee_today;
        $total_out = Cache::get('sum_transactions_out'.$wallet->id);

        $cash_in_sum_today = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereDate('created_at', '<=', Carbon::now()->toDateString())
            ->whereIn('remark', [
                'fund', 'received', 'merchant-payment', 'brijx_escrow',
                'received_with_brij', 'payproduct', 'receivedpayment', 'paymerchant', 'paywithbrij',
                'fund_brij_user', 'request',
            ])
            ->whereIn('status', ['success', 'successful'])
            ->sum('amount_in_figures');

        $cash_in_fee_today = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereDate('created_at', '<=', Carbon::now()->toDateString())
            ->whereDate('created_at', Carbon::now()->toDateString())
            ->whereIn('remark', [
                'fund', 'received', 'merchant-payment', 'brijx_escrow',
                'received_with_brij', 'payproduct', 'receivedpayment', 'paymerchant', 'paywithbrij',
                'fund_brij_user', 'request',
            ])
            ->whereIn('status', ['success', 'successful'])
            ->sum('app_fee');

        $total_in_today = $cash_in_sum_today + $cash_in_fee_today;
        $total_in = Cache::get('sum_transactions_in'.$wallet->id);

        $recon_val = ($total_in_today + $total_in) - ($total_out_today + $total_out);

        Log::channel('bal_recon_profile')->info(json_encode([
            'total_in' => $total_in_today,
            'total_out' => $total_out_today,
            'recon' => $recon_val,
            'wallet balance' => $wallet->balance,
            'wallet_id' => $wallet->id,
        ]));

        if ($recon_val < 0) {
            throw new ValidationFailedException('Transaction reconciliation failed for your account');
        }

        return true;
    }

    /**
     * @param  Wallet  $wallet
     * @param  string  $wallet_ability
     * @param  bool  $inherit  Set to TRUE if kyc level 1 can inherit all kyc level 0 wallet abilities
     * @return bool
     *
     * @throws CustomBadRequestException
     */
    public static function ensureWalletAbilityAllowed($wallet, $wallet_ability, $inherit = false)
    {
        $ability = WalletAbility::whereSlug($wallet_ability)->first();
        if ($inherit) {
            // get all related kyc levels so that we can check the wallet abilities in those kyc levels
            // higher levels would mean that lower levels are part of the list
            $inherited_wallet_kyc_level_ids = WalletKycLevel::byWalletTypeAndLevels($wallet->wallet_type_id, $wallet->user_wallet_kyc_level)->pluck('id');
            if ($inherited_wallet_kyc_level_ids->isEmpty() || ! $ability) {
                // show an error when ability is not found or kyc level mapping was found for the wallet based on the current wallet kyc level
                throw new CustomBadRequestException('Permission denied. No wallet kyc found for this wallet.');
            }
            if (WalletKycLevelWalletAbility::doesntHaveWalletAbility($inherited_wallet_kyc_level_ids, $ability->id)) {
                // show error when the related kyc levels don't have the specified wallet ability
                //TODO: correct the Exception message when access is denied to the required ability
                throw new CustomBadRequestException('Permission denied');
            }
        } else {
            $wallet_kyc_level = WalletKycLevel::byWalletTypeAndLevel($wallet->wallet_type_id, $wallet->user_wallet_kyc_level)->first();
            if (! $ability || ! $wallet_kyc_level) {
                //TODO: throw a different exception when no ability is found for the requested ability
                throw new CustomBadRequestException("Permission denied. Either wallet ability ({$wallet_ability}) or wallet kyc level ({$wallet->wallettype->currency} Level {$wallet->wallet_type_id}) does not exist.");
            }
            if (WalletKycLevelWalletAbility::doesntHaveWalletAbility($wallet_kyc_level->id, $ability->id)) {
                //TODO: correct the Exception message when access is denied to the required ability
                throw new CustomBadRequestException('Permission denied');
            }
        }

        // all checks done, permit the operation
        return true;
    }

    /**
     * Ensures the wallet has the wallet ability to cash out
     *
     * @returns bool
     *
     * @throws CustomBadRequestException
     */
    public static function ensureCashoutPermitted($wallet)
    {
        return self::ensureWalletAbilityAllowed($wallet, 'can-cashout');
    }

    /**
     * Ensures the wallet has the wallet ability to cash in
     *
     * @return bool
     *
     * @throws CustomBadRequestException
     */
    public static function ensureCashinPermitted($wallet)
    {
        return self::ensureWalletAbilityAllowed($wallet, 'can-cashin');
    }

    public static function braasTransactionInFinalState(BraasTransaction $tx)
    {
        throw_if(($tx->status == 'successful' || $tx->status == 'failed'), new CustomBadRequestException('The transaction is already in a final state and cannot be re-executed', null, BRAAS_TRANSACTION_UNAVAILABLE));
    }

    public static function transactionProcessing(WalletTransaction $tx)
    {
        throw_if(($tx->status == 'pending'), new CustomBadRequestException('This transaction is currently being processed and cannot be re-submitted', null, BRAAS_TRANSACTION_UNAVAILABLE));
    }

    public static function braasEnsureCallbackIsset()
    {
        throw_if(blank(Auth::user()->client->configuration?->callback_url), new CustomBadRequestException('No callback url found. Create one on from your merchant dashboard', null, BRAAS_TRANSACTION_UNAVAILABLE));
    }

    public static function ensureCorridorIsAllowedForOnafriq()
    {
        $blockedCountries = config('brij.blocked_braas_countries.onafriq');

        throw_if(
            in_array(request('meta.sourceCountry'), explode('|', $blockedCountries)),
            new CustomBadRequestException('This country is not supported for this transaction')
        );
    }

    public static function ensureCurrencyIsAllowedForOnafriq()
    {
        $blockedCurrencies = config('brij.blocked_braas_currencies.onafriq');

        throw_if(
            in_array(request('meta.sourceCurrency'), explode('|', $blockedCurrencies)),
            new CustomBadRequestException('This currency is not supported for this transaction')
        );
    }
}
