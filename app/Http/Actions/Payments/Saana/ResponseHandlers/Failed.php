<?php

namespace App\Http\Actions\Payments\Saana\ResponseHandlers;

use App\Helpers\Transaction;
use App\Http\Actions\Payments\Saana\Sources\ResponseHandler;
use Domain\BRaaS\Inbound\Dcm\Helpers\TransactionReversalService;
use Illuminate\Support\Str;

class Failed extends ResponseHandler
{

    public function canHandlePayload(): bool
    {
        return  $this->status == 'failed';
    }

    public function handle()
    {

        $dto = $this->dto;
        Transaction::reversePayoutTransactionAndCharge($dto);

        $dto->trx->status = 'failed';
        $dto->trx->status_reason = $this->statusDescription;
        $dto->trx->wallet_balance = $dto->wallet->refresh()->balance;
        $dto->trx->save();
        $dto->saanaPayLog->status = 'failed';
        $dto->saanaPayLog->response_payload = $this->rawResponse;
        $dto->saanaPayLog->save();

        return $this->response;
    }

}
