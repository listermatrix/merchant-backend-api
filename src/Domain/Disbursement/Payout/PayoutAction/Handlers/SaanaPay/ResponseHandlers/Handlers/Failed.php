<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\Handlers;

use App\Models\Wallet;
use App\Helpers\Transaction;
use App\Models\CashOutMethod;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CustomModelNotFoundException;
use App\Http\Services\Traits\TransactionNotificationTrait;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\ResponseHandler;

class Failed extends ResponseHandler
{
    use TransactionNotificationTrait;

    public function canHandlePayload(): bool
    {
        return strtolower($this->processor) === 'saanapay' && $this->status == 'failed';
    }

    public function handle(Wallet $wallet): array
    {
        $walletTransaction = $this->getWalletTransaction();

         //when transaction is blank or final status already determine
        if( blank($walletTransaction ) || in_array($walletTransaction->status, [WalletTransaction::SUCCESSFUL, WalletTransaction::FAILED])  ) {
            return [];
        }

        DB::beginTransaction();

         try {
        
            $cashOutMethod = CashOutMethod::where('id', $walletTransaction->channel_id)->first();

            if( blank( $cashOutMethod ) ) {
                throw new CustomModelNotFoundException('No cash out method found for id specified');
            }

            // handle reversal
            $status = Transaction::rollbackFailedDisbursement( $walletTransaction,  $cashOutMethod);

            if(  $status != true ) {
                return [];
            }

            $message = 'Payout Attempt Failed';
            $walletTransaction->settled_at = now();
            $walletTransaction->status_reason = $message;
            $walletTransaction->status = WalletTransaction::FAILED;
            $walletTransaction->payment_provider_status_message = $this->statusDescription;
            $walletTransaction->meta = array_merge($walletTransaction->meta ?? [], ['processor_response' => $this->rawResponse]);
            $walletTransaction->save();

            DB::commit();

            $this->sendPushNotification($walletTransaction);

            return [
                'statusCode' => FAILED_PAYOUT_STATUS_CODE,
                'data' => [
                    'walletTransaction' => $walletTransaction,
                ],
                'wallet' => $wallet->refresh(),
                'description' => $message,
                'httpCode' => HTTP_UNPROCESSED_STATUS_ENTITY_CODE,
            ];

        } catch (\Exception $exception) {
            logger()->channel('saanapay')->error('Payout initiation: ', [$exception]);
            throw new CustomModelNotFoundException('Payout Transaction Failed');
        }
    }

}
