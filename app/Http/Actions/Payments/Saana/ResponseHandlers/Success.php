<?php

namespace App\Http\Actions\Payments\Saana\ResponseHandlers;

use App\DataTransferObjects\Transaction\ChargeData;
use App\Events\TransactionMailEvent;
use App\Helpers\Transaction;
use App\Http\Actions\Payments\Saana\Sources\ResponseHandler;
use App\Models\Wallet;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;

class Success extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
        return  $this->status == 'successful' || $this->status == 'success';
    }

    /**
     * @throws UnknownProperties
     */
    public function handle()
    {

        $dto = $this->dto;
        $rawResponse = $this->rawResponse;

        $dto->trx->status = $this->status;
        $dto->trx->status_reason = 'Transaction completed successfully';
        $dto->trx->settled_at = now();
        $dto->trx->wallet_balance = $dto->wallet->refresh()->balance;
        $dto->trx->meta = array_merge($dto->trx->meta ?? [], ['processor_response' => $rawResponse]);
        $dto->trx->save();

        $dto->saanaPayLog->status = 'successful';
        $dto->saanaPayLog->response_payload = $rawResponse;
        $dto->saanaPayLog->settled_at = now();
        $dto->saanaPayLog->save();

        TransactionMailEvent::dispatch($dto->wallet->user, $dto->trx);
        sendsms($this->getSmsContact($dto->wallet), $this->getSmsMessage($dto->trx));

        return $this->response;
    }

    private function getSmsMessage($tx): string
    {
        $transactionId = $tx->transaction_id;
        $dateTime = $tx->created_at->format('Y-m-d H:i:s');
        $currency = $tx->currency;
        $amount = $tx->amount_in_figures;
        $payeeName = request('payee_name');

        return "Payment made for {$currency}{$amount} to {$payeeName} with reference {$transactionId} on {$dateTime}";

    }

    private function getSmsContact($wallet)
    {
        return $wallet->user->phone;
    }
}
