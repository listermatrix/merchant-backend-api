<?php

namespace Domain\BXBank\MoneyTransfer\Transact\Factories;

use Domain\BXBank\MoneyTransfer\Transact\Dtos\RemittanceData;
use Domain\BXBank\MoneyTransfer\Transact\Strategies\Destination;
use Domain\BXBank\MoneyTransfer\Transact\Networks\DefaultBankNetwork;

class BankNetworkFactory
{
    protected static array $bankNetworks = [];

    public static function getNetwork(RemittanceData $data): Destination
    {
        return collect(static::$bankNetworks)
            ->map(fn (string $bankNetworkClass) => new $bankNetworkClass($data))
            ->first(
                fn (Destination $bankNetwork) => $bankNetwork->canHandlePayload()
            );
    }
}
