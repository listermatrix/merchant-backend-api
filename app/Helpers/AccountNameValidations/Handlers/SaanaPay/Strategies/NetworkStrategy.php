<?php

namespace App\Helpers\AccountNameValidations\Handlers\SaanaPay\Strategies;

use App\Models\CashOutMethod;

abstract class NetworkStrategy
{
    protected CashOutMethod $cashOutMethod;
    protected mixed $accountId;

    public function __construct(mixed $data)
    {
        $this->cashOutMethod = $data->cashOutMethod;
        $this->accountId = $data->accountId;
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(): array;
}
