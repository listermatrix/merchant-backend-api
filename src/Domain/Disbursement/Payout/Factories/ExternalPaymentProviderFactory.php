<?php

namespace Domain\Disbursement\Payout\Factories;


use Domain\Disbursement\Defaults\DefaultExternalPaymentProvider;
use Domain\Disbursement\Payout\ExternalPaymentProviders\Cellulant;
use Domain\Disbursement\Payout\ExternalPaymentProviders\SaanaPay;
use Domain\Disbursement\Payout\ExternalPaymentProviders\EmergentPay;
use Domain\Disbursement\Payout\Strategies\ExternalPaymentProviderSource;
use Domain\Disbursement\Payout\DataTransferObjects\ExternalPaymentProviderData;

class ExternalPaymentProviderFactory
{
     // Add external payment provider classes here
    protected static $externalPaymentProviderSources = [
        EmergentPay::class,
        Cellulant::class,
        SaanaPay::class,

        

        // DefaultExternalPaymentProvider should be the last option
        // as it always resolved to true
        DefaultExternalPaymentProvider::class,
    ];

    public static function getExternalPaymentProvider(ExternalPaymentProviderData $data): ExternalPaymentProviderSource
    {
        $externalPaymentProviderSource = collect(static::$externalPaymentProviderSources)
            ->map(fn (string $externalPaymentProviderSourceClass) => new $externalPaymentProviderSourceClass($data))
            ->first(fn (ExternalPaymentProviderSource $externalPaymentProviderSource) => $externalPaymentProviderSource->canHandlePayload());

        return $externalPaymentProviderSource;
    }

}
