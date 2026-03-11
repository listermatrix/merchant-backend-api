<?php

namespace Domain\Disbursement\Payout\DataTransferObjects;

use App\Models\CashOutMethod;
use Spatie\DataTransferObject\DataTransferObject;

class ExternalPaymentProviderData extends DataTransferObject
{
     public float $amount;
    public ?string $description;
    public ?string $payeeName;
    public ?string $accountId;
    public ?string $momoNumber;
    public ?string $accountNumber;
    public mixed $countryCurrency;
    public CashOutMethod $cashOutMethod;

    public static function toDTO(array $data): ExternalPaymentProviderData
    {
        $data = (object) $data;

        return new self(
            amount: $data->amount,
            accountId: $data?->accountId,
            payeeName: $data?->payeeName,
            momoNumber: $data->momoNumber,
            description: $data?->description,
            cashOutMethod: $data->cashOutMethod,
            countryCurrency: $data->countryCurrency,
        );

    }
}
