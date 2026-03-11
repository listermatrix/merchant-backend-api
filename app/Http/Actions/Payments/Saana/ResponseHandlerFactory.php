<?php

namespace App\Http\Actions\Payments\Saanaj;

use App\Http\Actions\Payments\Saana\Sources\ResponseHandler;
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

    public static function getResponseHandler($data): ResponseHandler
    {
        $responseHandler = collect(static::$responseHandlers)
            ->map(fn (string $responseHandlerClass) => new $responseHandlerClass($data))
            ->first(fn (ResponseHandler $responseHandler) => $responseHandler->canHandlePayload());

        return $responseHandler ?? new DefaultHandler($data);
    }
}
