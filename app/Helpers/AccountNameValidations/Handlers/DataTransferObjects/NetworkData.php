<?php

namespace App\Helpers\AccountNameValidations\Handlers\DataTransferObjects;

use App\Models\CashOutMethod;
use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;

class NetworkData extends DataTransferObject
{
    public mixed $accountId;
    public CashOutMethod $cashOutMethod;

    /**
     * @throws UnknownProperties
     */
    public static function toDTO(array $data): self
    {
        $data = (object) $data;

        return new self(
            accountId: $data->accountId,
            cashOutMethod: $data->cashOutMethod
        );
    }
}
