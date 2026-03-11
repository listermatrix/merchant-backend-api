<?php

namespace App\Helpers\AccountNameValidations\Strategies;

use App\Models\CashOutMethod;
use App\Helpers\AccountNameValidations\DataTransferObjects\ExternalNameValidationProviderData;

abstract class ExternalNameValidationProviderStrategy
{
    protected CashOutMethod $cashOutMethod;
    protected mixed $accountId;

    public function __construct(ExternalNameValidationProviderData $providerData)
    {
        $this->cashOutMethod = $providerData->cashOutMethod;
        $this->accountId = $providerData->accountId;
    }

    abstract public function canHandlePayload(): bool;

    abstract public function validate(): array;
}
