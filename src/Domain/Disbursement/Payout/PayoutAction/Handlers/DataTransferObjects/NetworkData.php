<?php

namespace Domain\Disbursement\Payout\PayoutAction\Handlers\DataTransferObjects;

use App\Models\CashOutMethod;
use Spatie\DataTransferObject\DataTransferObject;

class NetworkData extends DataTransferObject
{
    public float $amount;
    public ?string $reason;
    public ?string $payeeName;
    public mixed $momoNumber;
    public mixed $countryCurrency;
    public CashOutMethod $cashOutMethod;

    public static function toDTO(array $data): NetworkData
    {
        $data = (object) $data;

         return new self(
            amount: $data->amount,
            reason: $data?->reason,
            payeeName: $data?->payeeName,
            momoNumber: $data?->momoNumber,
            cashOutMethod: $data->cashOutMethod,
            countryCurrency: $data->countryCurrency,
         );
    }
}
