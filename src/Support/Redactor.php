<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Replaces secrets before anything is stored. Keys match case-insensitively
 * at any depth (with "-" read as "_"), values are replaced even when empty,
 * and value patterns (JWT, Bearer, Basic) are applied to every string.
 */
final class Redactor
{
    /** @var list<string> */
    private readonly array $keyPatterns;

    /** @var list<string> */
    private readonly array $requestHeaders;

    /** @var list<string> */
    private readonly array $responseHeaders;

    /** @var list<string> */
    private readonly array $valuePatterns;

    private readonly string $replacement;

    private readonly int $maxDepth;

    public function __construct()
    {
        $this->keyPatterns = array_map(
            self::normalizeKey(...),
            [...self::strings('request-log.redact.keys'), ...self::strings('request-log.redact.extra_keys')],
        );
        $this->requestHeaders = array_map(self::normalizeKey(...), self::strings('request-log.redact.request_headers'));
        $this->responseHeaders = array_map(self::normalizeKey(...), self::strings('request-log.redact.response_headers'));
        $this->valuePatterns = self::strings('request-log.redact.value_patterns');
        $this->replacement = Config::string('request-log.redact.replacement');
        $this->maxDepth = Config::integer('request-log.capture.max_depth');
    }

    public function replacement(): string
    {
        return $this->replacement;
    }

    public function isSensitiveKey(int|string $key): bool
    {
        return is_string($key) && Str::is($this->keyPatterns, self::normalizeKey($key));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function data(array $data, int $depth = 0): array
    {
        if ($depth >= $this->maxDepth) {
            return ['[depth limit]'];
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            $redacted[$key] = $this->isSensitiveKey($key) ? $this->replacement : $this->value($value, $depth);
        }

        return $redacted;
    }

    public function string(string $value): string
    {
        foreach ($this->valuePatterns as $pattern) {
            $value = preg_replace($pattern, $this->replacement, $value) ?? $this->replacement;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $headers  Symfony HeaderBag::all() shape
     * @return array<string, string>
     */
    public function headers(array $headers, bool $response): array
    {
        $listed = $response ? $this->responseHeaders : $this->requestHeaders;
        $redacted = [];

        foreach ($headers as $name => $values) {
            $name = strtolower((string) $name);
            $normalized = self::normalizeKey($name);

            if (in_array($normalized, $listed, true) || Str::is($this->keyPatterns, $normalized)) {
                $redacted[$name] = $this->replacement;

                continue;
            }

            $redacted[$name] = $this->string(implode(', ', array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                is_array($values) ? $values : [$values],
            )));
        }

        return $redacted;
    }

    private static function normalizeKey(string $key): string
    {
        return str_replace('-', '_', strtolower($key));
    }

    /**
     * @return list<string>
     */
    private static function strings(string $key): array
    {
        return array_values(array_filter(
            Config::array($key),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
    }

    private function value(mixed $value, int $depth): mixed
    {
        if (is_array($value)) {
            return $this->data($value, $depth + 1);
        }

        if (is_string($value)) {
            return $this->string($value);
        }

        return $value;
    }
}
