<?php

namespace App\Helpers\NameResolution\Factories;
use App\Helpers\NameResolution\SaanaPayNgNameResolution;
use App\Helpers\NameResolution\Strategies\Source;
use App\Helpers\NameResolution\DefaultNameResolution;

class NameResolutionFactory
{
    public static array $handlers = [

        SaanaPayNgNameResolution::class
    ];

    public static function getHandler(array $data): Source
    {
        $handler = collect(static::$handlers)
        ->map(fn (string $handlerClass) => new $handlerClass($data))
        ->first(fn (Source $handler) => $handler->canHandlePayload());

        return $handler ?? new DefaultNameResolution($data);
    }
}
