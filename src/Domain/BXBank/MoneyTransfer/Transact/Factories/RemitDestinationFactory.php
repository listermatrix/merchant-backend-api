<?php

namespace Domain\BXBank\MoneyTransfer\Transact\Factories;

use Domain\BXBank\MoneyTransfer\Transact\Destinations\Bank;
use Domain\BXBank\MoneyTransfer\Transact\Destinations\Momo;
use Domain\BXBank\MoneyTransfer\Transact\Dtos\RemittanceData;
use Domain\BXBank\MoneyTransfer\Transact\Strategies\Destination;

class RemitDestinationFactory
{
    private static array $destinations = [
        Momo::class,
        Bank::class,
    ];

    public static function getDestination(RemittanceData $data): Destination
    {
        return collect(static::$destinations)
            ->map(fn (string $destinationClass) => new $destinationClass($data))
            ->first(fn (Destination $destination) => $destination->canHandlePayload());
    }
}
