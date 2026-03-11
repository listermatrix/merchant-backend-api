<?php

namespace Domain\PayMerchant\Trait;

use App\Models\BrijXServiceTransaction;
use App\Models\Wallet;

trait ReversesPayments
{
    private function handleReversal(
        Wallet $wallet,
        BrijXServiceTransaction $service,
    ) {
        $this->apiUserTransaction->status = 'failed';
        $this->apiUserTransaction->status_reason = 'Bill payment failed.';
        $this->apiUserTransaction->save();

        $service->status = 'failed';
        $service->save();

        $service->refresh();

        safelyCredit($wallet, $this->apiUserTransaction->transaction_amount);
    }
}
