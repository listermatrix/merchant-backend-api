<?php

use App\Exceptions\CustomBadRequestException;
use App\Exceptions\KycFailedException;
use App\Exceptions\SomethingWentWrongException;

return [
    'before_send' => function (Sentry\Event $event): ?Sentry\Event {
        $dontReportExceptions = [
            Exception::class,
            CustomBadRequestException::class,
            KycFailedException::class,
            SomethingWentWrongException::class,
        ];

        if ($event->getExceptions()) {
            $exception = $event->getExceptions()[0];

            if (in_array($exception->getType(), $dontReportExceptions)) {
                return null;
            }
        }

        return $event;
    },
];
