<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

final class ReferenceCode
{
    public const LENGTH = 10;

    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $bytes = random_bytes(self::LENGTH);
        $code = '';

        for ($index = 0; $index < self::LENGTH; $index++) {
            $code .= self::ALPHABET[ord($bytes[$index]) & 31];
        }

        return $code;
    }

    /**
     * Turns a pasted value such as "[abcd-efgh-jk]" back into a stored code.
     * Crockford base32 decoding: I and L read as 1, O reads as 0.
     */
    public static function normalize(string $value): string
    {
        $alphanumeric = preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '';

        return strtr(strtoupper($alphanumeric), ['I' => '1', 'L' => '1', 'O' => '0']);
    }
}
