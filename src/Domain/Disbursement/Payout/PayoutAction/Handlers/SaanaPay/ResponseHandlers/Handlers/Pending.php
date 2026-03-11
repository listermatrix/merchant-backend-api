<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\Handlers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\ResponseHandler;

class Pending extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
        return strtolower($this->processor) === 'saanapay' && $this->status == 'pending';
    }

    public function handle(Wallet $wallet): array
    {
        $walletTransaction = $this->getWalletTransaction();

        if( blank($walletTransaction ) || in_array($walletTransaction->status, [WalletTransaction::SUCCESSFUL, WalletTransaction::FAILED])  ) {
             return [];
        }

        $walletTransaction->status_reason = 'Transaction initiated';
        $walletTransaction->wallet_balance = $wallet->refresh()->balance;

        if( filled( $this->processorTransactionId )) {
            $walletTransaction->processor_transaction_id = $this->processorTransactionId;
        }

        $walletTransaction->payment_provider_status_message =  $this->statusDescription;
        $walletTransaction->meta = array_merge($walletTransaction->meta ?? [], ['processor_response' => $this->response]);
        $walletTransaction->save();

        return [
            'statusCode' => PENDING_PAYOUT_STATUS_CODE,
            'data' => [
                'walletTransaction' => $walletTransaction,
            ],
            'wallet' => $wallet->refresh(),
            'description' => 'Payout is being processed.',
            'httpCode' => HTTP_SUCCESSFUL_STATUS_CODE,
        ];
    }
}
