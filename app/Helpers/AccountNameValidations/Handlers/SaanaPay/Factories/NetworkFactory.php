<?php

namespace App\Helpers\AccountNameValidations\Handlers\SaanaPay\Factories;

use App\Helpers\AccountNameValidations\Handlers\DataTransferObjects\NetworkData;
use App\Helpers\AccountNameValidations\Handlers\SaanaPay\Strategies\NetworkStrategy;
use App\Helpers\AccountNameValidations\Handlers\SaanaPay\Networks\SaanaPayAccountResolution;

class NetworkFactory
{
     protected static $networks = [
         SaanaPayAccountResolution::class,
    ];

    public static function getNetwork(NetworkData $networkData): NetworkStrategy
    {
        $provider = collect(static::$networks)
            ->map(fn (string $networkClass) => new $networkClass($networkData))
            ->first(fn (NetworkStrategy $networkStrategy) => $networkStrategy->canHandlePayload());

        return $provider;
    }
}
