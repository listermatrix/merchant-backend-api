<?php

namespace Domain\BXBank\MoneyTransfer\Transact\Networks;

use Domain\BXBank\MoneyTransfer\Transact\Strategies\BankNetwork;

class DefaultBankNetwork extends BankNetwork
{
    public function canHandlePayload(): bool
    {
        return true;
    }

    public function handle($wallet)
    {
        exit('No method of payment has been implemented yet');
    }
}
