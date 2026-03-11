<?php

namespace Domain\PayMerchant\DataTransferObjects;

use App\Models\CashInMethod;
use Spatie\DataTransferObject\DataTransferObject;

class CardNetworkData extends DataTransferObject
{
    public string $amount;

    public string $network;

    public string $cardCvv;

    public string $currency;

    public string $userNumber;

    public string $nameOnCard;

    public ?string $clientEmail;

    public string $cardNumber;

    public string $expiryMonth;

    public string $expiryYear;

    public string $customerFirstname;

    public string $customerLastname;

    public string $customerEmail;

    public ?string $billingCity;

    public ?string $billingCountryCode;

    public ?array $meta;

    public CashInMethod $paymentMethod;

    public static function toDTO(array $data = [])
    {
        return new self($data);
    }
}
