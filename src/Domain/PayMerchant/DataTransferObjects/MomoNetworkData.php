<?php

namespace Domain\PayMerchant\DataTransferObjects;

use App\Models\CashInMethod;
use Spatie\DataTransferObject\DataTransferObject;

class MomoNetworkData extends DataTransferObject
{
    public string $momoNumber;

    public string $amount;

    public string $userNumber;

    public string $network;

    public string $customerFirstName;

    public string $customerLastName;

    public string $customerEmail;

    public ?array $meta;

    public ?string $description;

    public CashInMethod $paymentMethod;

    public static function toDTO(array $data = [])
    {
        return new self($data);
    }
}
