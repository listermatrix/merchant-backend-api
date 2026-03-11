<?php

namespace App\Helpers\AccountNameValidations\Handlers\SaanaPay\Networks;

use Illuminate\Support\Arr;
use Domain\ApiClients\SaanaPay;
use App\Helpers\AccountNameValidations\Handlers\SaanaPay\Strategies\NetworkStrategy;

class SaanaPayAccountResolution extends NetworkStrategy
{
    public function canHandlePayload(): bool
    {
        return strtolower($this->cashOutMethod->processor) === 'saanapay';
    }

    public function handle(): array
    {
        $response = $this->resolveAccountName();

        $name = $this->buildResponse($response);

        return [
            'accountId' => $this->accountId,
            'accountName' => $name,
        ];
    }

    private function buildResponse(array $response): ?string
    {
        $status  = Arr::get($response, 'status');
        $name = Arr::get($response, 'data.account_name');

        // Stop early if status is not successful
        if ($status != true) {
            return null;
        }
        
        return $name;
    }


    private function resolveAccountName(): array
    {
        $emergentPay = app(SaanaPay::class);

        return $emergentPay->resolveAccount([
            'bank_code'      => $this->cashOutMethod->code,
            'bank'           => $this->cashOutMethod->name,
            'account_number' => $this->accountId
        ]);
    }
}