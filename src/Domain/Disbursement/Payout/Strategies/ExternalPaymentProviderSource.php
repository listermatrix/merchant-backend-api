<?php

namespace Domain\Disbursement\Payout\Strategies;

use App\Models\CashOutMethod;
use App\Models\Wallet;
use Domain\Disbursement\Payout\DataTransferObjects\ExternalPaymentProviderData;

abstract class ExternalPaymentProviderSource
{
    protected mixed $countryCurrency;
    protected ?CashOutMethod $cashOutMethod;
    
   public function __construct(ExternalPaymentProviderData $data)
    {
        $this->countryCurrency = $data?->countryCurrency;
        $this->cashOutMethod = $data?->cashOutMethod;
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(Wallet $wallet);
}