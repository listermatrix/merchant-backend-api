<?php

namespace Domain\Disbursement\Payout\PayoutAction\Processors;

use App\Models\Wallet;
use Domain\Disbursement\Payout\PayoutAction\Strategies\PayoutActionStrategy;
use Domain\Disbursement\Payout\PayoutAction\DataTransferObjects\NetworkData;
use Domain\Disbursement\Payout\PayoutAction\Handlers\SaanaPay\Factories\NetworkFactory;

 class SaanaPay extends PayoutActionStrategy
{
    public function canHandlePayload(): bool
    {
       return $this->provider === 'saanapay';
    }

    public function handle(Wallet $wallet): array
    {
         $dto = NetworkData::toDTO( $this->buildPayload() );
        return NetworkFactory::getNetwork($dto)->handle($wallet);
    }

    private function buildPayload(): array
    {
         return [
            'amount' => $this->amount,
            'accountId' => $this->accountId,
            'payeeName' => $this->payeeName,
            'momoNumber' => $this?->momoNumber,
            'description' => $this->description,
            'cashOutMethod' => $this->cashOutMethod,
            'countryCurrency' => $this->countryCurrency,
        ];
    }
}
