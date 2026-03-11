<?php

namespace Domain\BXBank\MoneyTransfer\Lookup\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class LookupDto extends DataTransferObject
{
    public string $service_id;

    public float $amount;

    public string $rate_provider;

    public string $source_currency;

    public string $destination_currency;

    public string $destination_country_code;

    public ?string $source_country_code;

    public static function fromArray(array $data)
    {
        return new self($data);
    }
}
