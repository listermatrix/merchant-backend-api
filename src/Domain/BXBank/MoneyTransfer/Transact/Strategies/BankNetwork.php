<?php

namespace Domain\BXBank\MoneyTransfer\Transact\Strategies;

use App\Models\BraasTransaction;
use App\Models\CashOutMethod;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Domain\BXBank\MoneyTransfer\Transact\Dtos\RemittanceData;

abstract class BankNetwork
{
    protected BraasTransaction $braasTrx;

    protected WalletTransaction $walletTrx;

    protected CashOutMethod $cashOutMethod;

    public function __construct(RemittanceData $data)
    {
        $this->braasTrx = $data->braasTrx;

        $this->walletTrx = $data->walletTrx;

        $this->cashOutMethod = $data->cashOutMethod;
    }

    abstract public function canHandlePayload(): bool;

    abstract public function handle(Wallet $wallet);
}
