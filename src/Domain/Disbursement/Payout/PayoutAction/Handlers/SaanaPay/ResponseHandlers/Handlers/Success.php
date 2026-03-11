<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\Handlers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Events\TransactionMailEvent;
use App\Http\Services\Traits\TransactionNotificationTrait;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\ResponseHandler;

class Success extends ResponseHandler
{
    use TransactionNotificationTrait;

    public function canHandlePayload(): bool
    {
        return strtolower($this->processor) === 'saanapay' &&
                ($this->status == WalletTransaction::SUCCESSFUL || $this->status == 'success');
    }

    public function handle(Wallet $wallet): array
    {
        $walletTransaction = $this->getWalletTransaction();

        if( blank($walletTransaction ) || in_array($walletTransaction->status, [WalletTransaction::SUCCESSFUL, WalletTransaction::FAILED])  ) {
             return [];
        }

        $walletTransaction->settled_at = now();
        $walletTransaction->status = $this->status;
        $walletTransaction->status_reason = 'Payout successful';
        $walletTransaction->wallet_balance = $wallet->refresh()->balance;
        $walletTransaction->payment_provider_status_message = $this->statusDescription;
        $walletTransaction->meta = array_merge($walletTransaction->meta ?? [], ['processor_response' => $this->rawResponse]);
        $walletTransaction->save();

         $this->sendPaymentNotification($wallet, $walletTransaction);

        $this->sendPushNotification($walletTransaction);

        return [
            'statusCode' => SUCCESSFUL_PAYOUT_STATUS_CODE,
            'data' => [
                'walletTransaction' => $walletTransaction,
            ],
            'wallet' => $wallet->refresh(),
            'description' => 'Payout completed successfully',
            'httpCode' => HTTP_SUCCESSFUL_STATUS_CODE,
        ];
    }

    private function sendPaymentNotification(Wallet $wallet, WalletTransaction $walletTransaction): void
    {
        TransactionMailEvent::dispatch($wallet->user, $walletTransaction);

        $this->SendPaymentReceiptSmsToUser((string) $walletTransaction->uuid);
    }
}
