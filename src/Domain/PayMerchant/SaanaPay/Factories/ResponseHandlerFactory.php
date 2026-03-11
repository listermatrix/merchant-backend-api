<?php

namespace Domain\PayMerchant\SaanaPay\Factories;

use Domain\PayMerchant\SaanaPay\Strategies\ResponseHandler;
use Domain\PayMerchant\SaanaPay\ResponseHandlers\FailedResponseHandler;
use Domain\PayMerchant\SaanaPay\ResponseHandlers\DefaultResponseHandler;
use Domain\PayMerchant\SaanaPay\ResponseHandlers\InstantResponseHandler;
use Domain\PayMerchant\SaanaPay\ResponseHandlers\PendingResponseHandler;
use Domain\PayMerchant\SaanaPay\ResponseHandlers\SuccessResponseHandler;
use Domain\PayMerchant\SaanaPay\ResponseHandlers\CardAuthorizationURLHandler;

class ResponseHandlerFactory
{
    protected static $handlers = [
        CardAuthorizationURLHandler::class,
        SuccessResponseHandler::class,
        PendingResponseHandler::class,
        FailedResponseHandler::class,
        InstantResponseHandler::class,
        DefaultResponseHandler::class,
       
    ];

    public static function getHandler(array $payload): ResponseHandler
    {
        $handler = collect(static::$handlers)
            ->map(fn (string $tellerHandlerClass) => new $tellerHandlerClass($payload))
            ->first(fn (ResponseHandler $handler) => $handler->canHandlePayload());

        return $handler ?? new DefaultResponseHandler($payload);
    }
}


      
