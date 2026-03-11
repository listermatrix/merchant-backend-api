<?php

namespace Domain\Disbursement\Payout\PayoutAction\Strategies;

use App\Models\Wallet;
use App\Models\CashOutMethod;
use Domain\Disbursement\Payout\PayoutAction\DataTransferObjects\PayoutActionDTO;

abstract class PayoutActionStrategy
{
    protected float $amount;
    protected mixed $description;
    protected mixed $provider;
    protected mixed $payeeName;
    protected mixed $accountId;
    protected mixed $momoNumber;
    protected mixed $countryCurrency;
    protected CashOutMethod $cashOutMethod;

    public function __construct(PayoutActionDTO $data)
    {
        $this->amount = $data->amount;
        $this->provider = $data?->provider;
        $this->accountId = $data?->accountId;
        $this->payeeName = $data?->payeeName;
        $this->momoNumber = $data?->momoNumber;
        $this->description = $data->description;
        $this->cashOutMethod = $data?->cashOutMethod;
        $this->countryCurrency = $data?->countryCurrency;
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(Wallet $wallet): array;
}
