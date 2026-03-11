<?php

namespace Domain\PayMerchant\SaanaPay\ResponseHandlers;

use App\Models\Wallet;
use Illuminate\Support\Arr;
use App\Models\WalletTransaction;
use App\Exceptions\CustomModelNotFoundException;
use Domain\PayMerchant\SaanaPay\Strategies\ResponseHandler;

class PendingResponseHandler extends ResponseHandler
{
    public function canHandlePayload(): bool
    {
        return $this->provider === 'saanapay' && $this->status === 'pending';
    }

    public function handle(Wallet $wallet): array
    {
        $fund = WalletTransaction::whereTransactionId($this->transactionId)->first();

        if (blank($fund)) {
            throw new CustomModelNotFoundException('No fund transaction found.');
        }

        $fund->meta = array_merge($fund->meta ?? [], $this->providerResponse);

        $fund->save();

        return [];
    }
}
