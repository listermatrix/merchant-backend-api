<?php

namespace Domain\PayMerchant\DataTransferObjects;

use App\Models\Wallet;
use Spatie\DataTransferObject\DataTransferObject;

class BrijNetworkData extends DataTransferObject
{
    public string $amount;

    public string $network;

    public string $currency;

    public string $userNumber;

    public string $customerContact;

    public Wallet $payingWallet;

    public static function toDTO(array $data = [])
    {
        return new self($data);
    }
}
