<?php

namespace Domain\BXBank\MoneyTransfer\Lookup\Strategies;

abstract class BeneficiaryLookup
{
   abstract public function handlesPayload(): bool;
   
  abstract public function lookup(): array;
}