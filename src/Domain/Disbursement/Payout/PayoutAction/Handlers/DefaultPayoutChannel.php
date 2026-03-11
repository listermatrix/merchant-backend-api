<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers;

use App\Models\Wallet;
use App\Exceptions\CustomBadRequestException;
use Domain\Disbursement\Payout\PayoutAction\Handlers\EmergentPay\Strategies\NetworkStrategy;


class DefaultPayoutChannel extends NetworkStrategy
{
    public function canHandlePayload(): bool
    {
       return true;
    }

    public function handle(Wallet $wallet): array
    {
        throw new CustomBadRequestException("Unknown Payment channel: The specified payment channel does not exist");
    }

}

