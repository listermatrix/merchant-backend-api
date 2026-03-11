<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\Factories;

use Domain\Disbursement\Payout\PayoutAction\Handlers\DefaultPayoutChannel;
use Domain\Disbursement\Payout\PayoutAction\DataTransferObjects\NetworkData;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\Networks\BankTransfer;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\Strategies\NetworkStrategy;


class NetworkFactory
{
    protected static array $banks = [
        BankTransfer::class,

        //should be the last option
        DefaultPayoutChannel::class,
    ];

    public static function getNetwork(NetworkData $data): NetworkStrategy
    {

        return collect(static::$banks)
            ->map(fn (string $networkClass) => new $networkClass($data))
            ->first(fn (NetworkStrategy $networkStrategy) => $networkStrategy->canHandlePayload());
    }

}
