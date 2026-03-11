<?php

namespace Domain\PayMerchant\SaanaPay\ResponseHandlers;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Models\PaymentReceipt;
use App\Models\WalletTransaction;
use App\Http\Resources\WalletResource;
use App\Http\Resources\PaymentReceiptResource;
use App\Exceptions\CustomModelNotFoundException;
use Domain\PayMerchant\Events\MerchantPaidEvent;
use Domain\PayMerchant\SaanaPay\Strategies\ResponseHandler;


class FailedResponseHandler extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
        return $this->provider === 'saanapay' && $this->status === 'failed';
    }

    public function handle(Wallet $wallet): array
    {
        $fund = WalletTransaction::whereTransactionId($this->transactionId)->lockForUpdate()->first();

        if (blank($fund)) {
            throw new CustomModelNotFoundException('No fund transaction found.');
        }

        if ( in_array($fund->status, ['successful', 'failed']) ) {
            throw new CustomModelNotFoundException('Transaction already processed');
        }

        $meta = array_merge($fund->meta ?? [], $this->providerResponse);

        $fund->meta = $meta;
        $fund->status = 'failed';
        $fund->save();

        if($fund->invoicePaymentLog){
            $fund->invoicePaymentLog->update(['status' => 'failed']);
        }

        $receipt = $this->updateReceiptStatus($fund);

        $receiptResource = new PaymentReceiptResource($receipt);

        //convert resource to assoc array
        $receipt = json_decode($receiptResource->toResponse(1)->getContent(), true);

        event(new MerchantPaidEvent(200,
            Arr::get($receipt, 'data'),
            Arr::get($receipt, 'data.receipt_number'),
            'Payment completed successfully'
        ));

        return [
            'status' => THETELLER_ACCESS_DENIED_CODE,
            'httpStatus' => 400,
            'data' => [
                'wallet' => new WalletResource($wallet),
            ],
            'message' => 'Payment failed',
        ];
    }

    private function updateReceiptStatus($transaction)
    {
        $receipt = PaymentReceipt::whereWalletTransactionId(
            $transaction->id
        )->first();

        $receipt->status = 'failed';
        $receipt->save();

        return $receipt->refresh();
    }
}
