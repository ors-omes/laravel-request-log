<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

final class Display
{
    /**
     * Pretty-print stored JSON; text that is not valid JSON (placeholders,
     * last-resort cuts) is returned unchanged.
     */
    public static function pretty(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return $stored;
        }

        $decoded = json_decode($stored, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $stored;
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($pretty) ? $pretty : $stored;
    }

    public static function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
