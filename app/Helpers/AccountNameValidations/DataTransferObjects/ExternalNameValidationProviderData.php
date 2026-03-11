<?php
namespace App\Helpers\AccountNameValidations\DataTransferObjects;

use App\Models\CashOutMethod;
use Spatie\DataTransferObject\DataTransferObject;

class ExternalNameValidationProviderData extends DataTransferObject
{
    public CashOutMethod $cashOutMethod;
    public mixed $accountId;

    public static function toDTO(array $data): self
    {
        $data = (object) $data;
        
        return new self(
            cashOutMethod: $data->cashOutMethod,
            accountId: $data->accountId
        );
    }
}
