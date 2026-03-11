<?php
namespace Domain\Disbursement\Payout\PayoutAction\Factories;

use Domain\Disbursement\Payout\PayoutAction\Strategies\PayoutActionStrategy;
use Domain\Disbursement\Payout\PayoutAction\DataTransferObjects\PayoutActionDTO;
use Domain\Disbursement\Payout\PayoutAction\Processors\SaanaPay;
class PayoutActionFactory
{
    protected static array $payoutActionHandlers = [

         SaanaPay::class,

    ];

    public static function getPayoutHandler(PayoutActionDTO $payload): PayoutActionStrategy
    {
        return collect(static::$payoutActionHandlers)
            ->map(fn (string $payoutActionHandlerClass) => new $payoutActionHandlerClass($payload))
            ->first(fn (PayoutActionStrategy $handler) => $handler->canHandlePayload());
    }
}
