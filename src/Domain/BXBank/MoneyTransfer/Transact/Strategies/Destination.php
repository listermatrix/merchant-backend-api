<?php

namespace Domain\BXBank\MoneyTransfer\Transact\Strategies;

use App\Models\BrijxIntentFulfillmentRequest;
use App\Models\Wallet;
use App\Models\CashOutMethod;
use App\Models\BrijXServiceTransaction;
use App\Models\MoniesHeldForSwapTransfer;
use App\Models\RemittanceDestinationRail;
use Domain\BXBank\MoneyTransfer\Transact\Dtos\RemittanceData;

abstract class Destination
{
    protected ?BrijxIntentFulfillmentRequest $intent; 

    protected ?MoniesHeldForSwapTransfer $moneyHeldForSwapTransfer;

    protected ?BrijXServiceTransaction $serviceTransaction;

    protected ?RemittanceDestinationRail $railConfig;

    protected ?CashOutMethod $cashOutMethod;

    protected RemittanceData $payload;

    protected Wallet $wallet;

    public function __construct(RemittanceData $payload)
    {
        $this->moneyHeldForSwapTransfer = $payload->moneyHeldForSwap;

        $this->serviceTransaction = $payload->serviceTransaction;

        $this->railConfig = $payload->railConfig;

        $this->cashOutMethod = $payload->cashOutMethod;

        $this->payload = $payload;

        $this->wallet = $payload->wallet;

        $this->intent = $payload->intent; 
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(BrijXServiceTransaction $service);
}
