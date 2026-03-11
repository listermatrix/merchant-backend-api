<?php

namespace Domain\BXBank\MoneyTransfer\Transact\Destinations;

use Illuminate\Support\Str;
use App\Models\BrijXServiceTransaction;
use Domain\BXBank\MoneyTransfer\Transact\Strategies\Destination;
use Domain\BXBank\MoneyTransfer\Transact\Factories\BankNetworkFactory;

class Bank extends Destination
{
    public function canHandlePayload(): bool
    {
        return Str::endsWith($this->cashOutMethod->fund_type, 'bank');
    }

    public function handle(BrijXServiceTransaction $service): void
    {
        $payout = BankNetworkFactory::getNetwork($this->payload);

        $payout->handle($this->serviceTransaction);
    }
}
