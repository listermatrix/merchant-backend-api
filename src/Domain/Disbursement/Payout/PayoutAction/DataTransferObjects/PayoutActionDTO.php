<?php
namespace Domain\Disbursement\Payout\PayoutAction\DataTransferObjects;

use App\Models\CashOutMethod;
use Spatie\DataTransferObject\DataTransferObject;

class PayoutActionDTO extends DataTransferObject
{
    public float $amount;
    public mixed $provider;
    public ?string $description;
    public ?string $payeeName;
    public ?string $accountId;
    public ?string $momoNumber;
    public ?string $accountNumber;
    public mixed $countryCurrency;
    public CashOutMethod $cashOutMethod;

    public static function toDTO(array $data): self
    {
        $data = (object) $data;

        return new self(
            amount: $data->amount,
            provider: $data?->provider,
            accountId: $data?->accountId,
            payeeName: $data?->payeeName,
            momoNumber: $data?->momoNumber,
            description: $data?->description,
            cashOutMethod: $data?->cashOutMethod,
            countryCurrency: $data?->countryCurrency,
        );

    }
}
