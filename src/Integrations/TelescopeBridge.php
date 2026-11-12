<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Integrations;

use Laravel\Telescope\Telescope;
use OrsOmes\RequestLog\RequestLog;

/**
 * Tags every Telescope entry of a considered request with "ref:<reference>",
 * so the viewer can open the request's whole Telescope batch.
 */
final class TelescopeBridge
{
    public static function register(): void
    {
        if (! class_exists(Telescope::class)) {
            return;
        }

        Telescope::tag(static function (): array {
            $reference = RequestLog::reference();

            return $reference === null ? [] : ['ref:'.$reference];
        });
    }
}
