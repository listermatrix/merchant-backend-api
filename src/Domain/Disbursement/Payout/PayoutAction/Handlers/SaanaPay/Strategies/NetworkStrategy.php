<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\Strategies;

use App\Models\Wallet;
use App\Models\CashOutMethod;

abstract class NetworkStrategy
{
    protected float $amount;
    protected mixed $payeeName;
    protected mixed $momoNumber;
    protected mixed $accountId;
    protected mixed $description;
    protected mixed $countryCurrency;
    protected CashOutMethod $cashOutMethod;

    public function __construct(mixed $data)
    {
        $this->amount = $data->amount;
        $this->accountId = $data?->accountId;
        $this->payeeName = $data?->payeeName;
        $this->momoNumber = $data?->momoNumber;
        $this->description = $data?->description;
        $this->cashOutMethod = $data->cashOutMethod;
        $this->countryCurrency = $data?->countryCurrency;

    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(Wallet $wallet): array;
}
