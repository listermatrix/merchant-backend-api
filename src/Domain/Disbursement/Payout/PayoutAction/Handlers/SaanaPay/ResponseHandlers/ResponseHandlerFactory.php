<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers;

use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\Handlers\Failed;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\Handlers\Pending;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\ResponseHandlers\Handlers\Success;

class ResponseHandlerFactory
{
    protected static array $responseHandlers = [
        Success::class,
        Failed::class,
        Pending::class,
    ];

    public static function getResponseHandler(mixed $data): ResponseHandler
    {
        $responseHandler = collect(static::$responseHandlers)
            ->map(fn (string $responseHandlerClass) => new $responseHandlerClass($data))
            ->first(fn (ResponseHandler $responseHandler) => $responseHandler->canHandlePayload());

        return $responseHandler;
    }
}
