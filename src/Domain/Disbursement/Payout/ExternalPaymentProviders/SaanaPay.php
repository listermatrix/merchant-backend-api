<?php

namespace Domain\Disbursement\Payout\ExternalPaymentProviders;

use App\Models\Wallet;
use Domain\Disbursement\Payout\Strategies\ExternalPaymentProviderSource;

class SaanaPay extends ExternalPaymentProviderSource
{
    public function canHandlePayload(): bool
    {
       return strtolower($this->cashOutMethod->processor) === 'saanapay' && $this->countryCurrency == 'NG_NGN';
    }

    public function handle(Wallet $wallet): ?string
    {
        return 'saanapay';
    }
}
