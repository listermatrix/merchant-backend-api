<?php

namespace App\Helpers\AccountNameValidations\Processors;

use App\Helpers\AccountNameValidations\Handlers\DataTransferObjects\NetworkData;
use App\Helpers\AccountNameValidations\Handlers\SaanaPay\Factories\NetworkFactory;
use App\Helpers\AccountNameValidations\Strategies\ExternalNameValidationProviderStrategy;

class SaanaPay extends ExternalNameValidationProviderStrategy
{
    public function canHandlePayload(): bool
    {
       return $this->cashOutMethod->processor === 'saanapay';
    }

    public function validate(): array
    {
        $dto = NetworkData::toDTO( $this->buildPayload() );
        $network = NetworkFactory::getNetwork($dto);
        return $network->handle();
    }

    private function buildPayload(): array
    {
        return [
            'cashOutMethod' => $this->cashOutMethod,
            'accountId' => $this->accountId
        ];
    }
}
