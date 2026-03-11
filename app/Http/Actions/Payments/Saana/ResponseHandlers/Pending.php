<?php

namespace App\Http\Actions\Payments\Saana\ResponseHandlers;

use App\Http\Actions\Payments\Saana\Sources\ResponseHandler;

class Pending extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
        return  $this->status == 'pending';
    }

    public function handle()
    {
        $dto = $this->dto;
        $dto->trx->status = $this->status;
        $dto->trx->status_reason = $this->statusDescription;
        $dto->trx->meta = array_merge($dto->trx->meta ?? [], ['processor_response' => $this->rawResponse]);
        $dto->trx->save();

        $dto->saanaPayLog->status = 'pending';
        $dto->saanaPayLog->response_payload = $this->rawResponse;
        $dto->saanaPayLog->save();

        return $this->response;
    }
}
