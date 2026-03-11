<?php

namespace App\Helpers\AccountNameValidations\Factories;

use App\Helpers\AccountNameValidations\Processors\SaanaPay;
use App\Helpers\AccountNameValidations\Processors\EmergentPay;
use App\Helpers\AccountNameValidations\Strategies\ExternalNameValidationProviderStrategy;
use App\Helpers\AccountNameValidations\DataTransferObjects\ExternalNameValidationProviderData;

class ExternalNameValidationProviderFactories
{
    protected static array $providers = [
       EmergentPay::class,
       SaanaPay::class
    ];

    public static function getProvider(ExternalNameValidationProviderData $providerData): ExternalNameValidationProviderStrategy
    {
        $provider = collect(static::$providers)
            ->map(fn (string $providerClass) => new $providerClass($providerData))
            ->first(fn (ExternalNameValidationProviderStrategy $providerStrategy) => $providerStrategy->canHandlePayload());

        return $provider;
    }
}
