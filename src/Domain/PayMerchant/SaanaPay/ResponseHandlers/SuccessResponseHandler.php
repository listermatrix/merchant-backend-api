<?php

namespace Domain\PayMerchant\SaanaPay\ResponseHandlers;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Models\PaymentReceipt;
use App\Models\WalletTransaction;
use App\Helpers\MerchantTransaction;
use Domain\PayMerchant\Events\FundSmsEvent;
use App\Http\Resources\PaymentReceiptResource;
use App\Exceptions\CustomModelNotFoundException;
use Domain\PayMerchant\Events\MerchantPaidEvent;
use App\DataTransferObjects\Transaction\ChargeData;
use Domain\PayMerchant\SaanaPay\Strategies\ResponseHandler;
use App\Http\Services\Traits\CustomerTransactionNotificationTrait;
use App\Http\Services\Traits\UserTransactionNotificationContructionTrait;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;

class SuccessResponseHandler extends ResponseHandler
{
    use CustomerTransactionNotificationTrait, UserTransactionNotificationContructionTrait;
    public function canHandlePayload(): bool
    {
        return $this->provider === 'saanapay' && $this->status === 'successful';
    }

    public function handle(Wallet $wallet): array
    {
        $fund = WalletTransaction::whereTransactionId($this->transactionId)->lockForUpdate()->first();

        if (blank($fund)) {
            throw new CustomModelNotFoundException('No wallet transaction found.');
        }

        if ( ! in_array($fund->status, ['pending', 'timeout']) ) {            
            logger()->channel('saanapay')->alert('SaanaPay, Transaction ID '.$this->transactionId.' already processed. Last updated at '.$fund->updated_at->format('Y-m-d H:i:s'), [ $this->providerResponse ]);
            return [];
        }

        $this->amount = $fund->amount_in_figures;

        $newBalance = $fund->balance_before + $this->amount;

        $fund->status = 'successful';
        $fund->meta = array_merge($fund->meta ?? [], $this->providerResponse);
        $fund->status_reason = 'Payment collection successful.';
        $fund->wallet_balance = $newBalance;
        $fund->settled_at = now();
        $fund->save();

        safelyCredit($wallet->id, $this->amount);

        if($fund->invoicePaymentLog){
            $fund->invoicePaymentLog->update(['status' => 'successful']);
            $fund->invoicePaymentLog->invoice->update(['status' => 'successful']);
        }

        $this->charge($fund->id);

        $this->customerTrxnNotifyReceipt($fund->uuid);

        $merchant = $this->getMerchantInfo($fund);

        $sms = $this->constructSMS($fund->refresh());
        
        $receipt = $this->getReceipt($fund);

        $receiptResource = new PaymentReceiptResource($receipt);

        //convert resource to assoc array
        $receipt = json_decode($receiptResource->toResponse(1)->getContent(), true);

        event(new FundSmsEvent($this->getSmsContact($fund), $sms, $fund->refresh(), $merchant));
        
        event(new MerchantPaidEvent(200,
            Arr::get($receipt, 'data'),
            Arr::get($receipt, 'data.receipt_number'),
            'Payment completed successfully'
        ));

        return [];
    }

    private function getReceipt($transaction)
    {
        $receipt = PaymentReceipt::whereWalletTransactionId(
            $transaction->id
        )->first();

        $receipt->status = 'successful';
        $receipt->save();

        return $receipt->refresh();
    }

    private function getMerchantInfo($fund)
    {
        return $fund->wallet->client->user;
    }

    private function getSmsContact($fund)
    {
        return $fund->wallet->client->user->phone;
    }

    /**
     * @throws UnknownProperties
     */
    private function charge($fundId)
    {
        $transaction = WalletTransaction::where('id', $fundId)->first();

        $map = [
            'card' => 'BFCS002',
            'directbanktransfer' => 'BFCS001',
        ];

        $feeCode = Arr::get($map, $transaction->transaction_channel, '');

        $channel = $transaction->transaction_channel;

        $chargeData = new ChargeData([
            'remark' => 'charge',
            'transactionChannel' => $transaction->transaction_channel,
            'transactionMethod' => $transaction->transaction_method,
            'statusReason' => "Charge for successful {$transaction->remark}.",
            'revenueDescription' => "Service fee charged for {$transaction->remark} transaction on ($channel) - processed by SaanaPay.",
        ]);

        MerchantTransaction::charge($transaction, $feeCode, $chargeData);
    }
}