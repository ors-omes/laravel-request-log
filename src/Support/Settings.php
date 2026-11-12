<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

use Illuminate\Support\Facades\Config;

final class Settings
{
    /**
     * Numeric config value that may be written as int, float or string.
     */
    public static function number(string $key, float $default = 0.0): float
    {
        $value = Config::get($key);

        return is_numeric($value) ? (float) $value : $default;
    }
}
