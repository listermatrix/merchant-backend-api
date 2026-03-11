<?php

namespace App\Http\Actions\Wallet;

use App\Events\WalletTransactionOccuredSmsEvent;
use App\Exceptions\CustomBadRequestException;
use App\Exceptions\SomethingWentWrongException;
use App\Http\Actions\Helpers\Converters;
use App\Models\InternalEscrow;
use App\Models\Notification;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletType;
use Exception;
use Illuminate\Support\Facades\DB;

class RedeemPaymentInEscrow
{
    private $client;

    private $converters;

    public function execute($client)
    {
        $this->client = $client;

        $this->converters = new Converters;

        $payments = $this->getUnexpiredPaymentInEscrow();

        $this->claim($payments);
    }

    private function claim($payments)
    {
        //TODO: use a Job at some point

        foreach ($payments as $payment) {
            $currency = $payment->wallettransaction->currency;
            $senders_contact = '';
            $senders_name = '';

            try {
                DB::transaction(function () use ($currency, $payment, &$senders_contact, &$senders_name) {
                    if (! $receiving_wallet = $this->clientHasRequiredCurrencyWallet($currency)) {
                        $receiving_wallet = $this->createNewWallet($currency);
                    }

                    $receiving_wallet = $this->creditWallet($receiving_wallet, $payment->amount);

                    [$receiving_wallet_transaction, $sending_wallet_transaction] = $this->logPaymentReceived($receiving_wallet, $payment);

                    $this->updateInternalEscrow($payment, $receiving_wallet);

                    [$senders_message, $receivers_message] = $this->updateNotification($receiving_wallet_transaction, $sending_wallet_transaction);

                    //TODO: dispatch an sms Job directly

                    $senders_contact = $sending_wallet_transaction->sourceclient->user->phone;
                    $senders_name = $receiving_wallet_transaction->targetclient->user->fullname;
                });
            } catch (Exception $e) {
                throw new SomethingWentWrongException('User could not claim pending payments at the moment');
            }

            event(new WalletTransactionOccuredSmsEvent($senders_contact, $this->buildSenderMessage($currency, $payment->amount)));

            event(new WalletTransactionOccuredSmsEvent(request()->phone, $this->buildReceiverMessage($currency, $senders_name, $payment->amount)));
        }
    }

    private function updateInternalEscrow($payment, $receiving_wallet)
    {
        $payment->target_wallet_id = $receiving_wallet->id;
        $payment->status = 'succesful';
        $payment->save();

        return $payment->refresh();
    }

    private function buildSenderMessage($currency, $amount)
    {
        $message = "Your payment of {$currency}{$amount} has been completed successfully.";

        return $message;
    }

    private function buildReceiverMessage($currency, $senders_name, $amount)
    {
        $message = "{$senders_name} sent you {$currency}{$amount}";

        if (blank($senders_name)) {
            $message = "A Brij User sent you {$currency}{$amount}";
        }

        return $message;
    }

    private function updateNotification($receiving_wallet_transaction, $sending_wallet_transaction)
    {
        $sending_user = $sending_wallet_transaction->sourceclient->user;
        $sending_client = $sending_wallet_transaction->sourceclient;

        $receiving_user = $receiving_wallet_transaction->targetclient->user;
        $receiving_client = $receiving_wallet_transaction->targetclient;

        $transaction_id = $sending_wallet_transaction->transaction_id;

        $senders_message = $sending_wallet_transaction->status_reason;
        $receivers_message = $receiving_wallet_transaction->status_reason;

        Notification::create([
            'status' => 'resolved',
            'transaction_id' => $transaction_id,
            'user_id' => $sending_user->id,
            'notification_type' => 'sent',
            'message' => $senders_message,
            'meta' => [
                'transaction_id' => $transaction_id,
                'receiver_id' => $receiving_client->uuid,
                'receiver_name' => $receiving_user->fullname,
                'receiver_selfie_url' => $receiving_client->selfie_url,
            ],
        ]);

        Notification::create([
            'status' => 'resolved',
            'transaction_id' => $transaction_id,
            'user_id' => $receiving_user->id,
            'notification_type' => 'received',
            'message' => $receivers_message,
            'meta' => [
                'transaction_id' => $transaction_id,
                'sender_id' => $sending_client->uuid,
                'sender_name' => $sending_user->fullname,
                'sender_selfie_url' => $sending_client->selfie_url,
            ],
        ]);

        return [$senders_message, $receivers_message];
    }

    private function logPaymentReceived(Wallet $receiving_wallet, InternalEscrow $payment)
    {
        $sending_wallet_transaction = $payment->wallettransaction;

        $sending_wallet_transaction->remark = 'sent';
        $sending_wallet_transaction->position = 'debit';
        $sending_wallet_transaction->transaction_method = 'sent';
        $sending_wallet_transaction->status = 'successful';
        $sending_wallet_transaction->status_reason = "Being payment sent to {$receiving_wallet->client->user->fullname}";
        $sending_wallet_transaction->target_client_id = $this->client->id;
        $sending_wallet_transaction->save();

        $sending_wallet = Wallet::whereId($sending_wallet_transaction->wallet_id)->whereStatus('active')->first();

        if (blank($sending_wallet)) {
            throw new CustomBadRequestException('The wallet is temporarily inactive');
        }

        $receiving_wallet_transaction = WalletTransaction::create([
            'transaction_id' => $sending_wallet_transaction->transaction_id,
            'transaction_amount' => $this->converters->convertDecimalToStringMoney($payment->amount),
            'amount_in_figures' => $payment->amount,
            'remark' => 'received',
            'currency' => $receiving_wallet->wallettype->currency,
            'transaction_method' => 'received',
            'transaction_channel' => 'orobo',
            'target_client_id' => $this->client->id,
            'source_client_id' => $sending_wallet_transaction->source_client_id,
            'wallet_id' => $receiving_wallet->id,
            'status' => 'successful',
            'internal_escrow_id' => $sending_wallet_transaction->internal_escrow_id,
            'app_fee' => 0,
            'status_reason' => "Being payment received from {$sending_wallet->client->user->fullname}",
            'wallet_balance' => $receiving_wallet->balance, //would be reversed if payment fails
            'balance_before' => 0,
            'position' => 'credit',
        ]);

        return [$receiving_wallet_transaction, $sending_wallet_transaction];
    }

    private function creditWallet($receiving_wallet, $amount)
    {
        $receiving_wallet->balance = $amount;
        $receiving_wallet->save();

        return $receiving_wallet->refresh();
    }

    private function createNewWallet($currency)
    {
        $wallettype = WalletType::where('currency', $currency)
            ->where('is_internal', 0)->first();

        return (new CreateNewWallet)->execute($wallettype);
    }

    private function clientHasRequiredCurrencyWallet($currency)
    {
        $wallets = $this->client->wallets;

        foreach ($wallets as $wallet) {
            if ($currency == $wallet->wallettype->currency) {
                return $wallet;
            }
        }

        return false;
    }

    private function getUnexpiredPaymentInEscrow()
    {
        return InternalEscrow::where('receivers_mobile', request()->phone)
            ->where('transaction_type', 'sent_payment_to_mobile')
            ->where('status', 'pending') //TODO: also check if the date has expired
            ->with(['wallettransaction'])
            ->get();
    }
}
