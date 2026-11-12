<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

use Illuminate\Support\Facades\Config;
use Stringable;

/**
 * Shortens data instead of discarding it. Content that fits the byte limit is
 * stored whole. Otherwise structured data stays valid JSON: long strings keep
 * their head plus "…[+N chars]", long arrays keep their first items plus
 * "…[+N items]", and the limits shrink until it fits. As a last resort the
 * text is cut on a UTF-8 boundary with a "…[truncated: kept X of Y bytes]"
 * marker.
 */
final class Truncator
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    private const MAX_ROUNDS = 10;

    private readonly int $maxString;

    private readonly int $maxItems;

    private readonly int $maxDepth;

    public function __construct()
    {
        $this->maxString = max(16, Config::integer('request-log.capture.max_string'));
        $this->maxItems = max(1, Config::integer('request-log.capture.max_items'));
        $this->maxDepth = max(1, Config::integer('request-log.capture.max_depth'));
    }

    public static function encode(mixed $data): string
    {
        $encoded = json_encode($data, self::JSON_FLAGS);

        return is_string($encoded) ? $encoded : '"[unencodable]"';
    }

    /**
     * @return array{0: string, 1: bool} encoded JSON and whether anything was cut
     */
    public function json(mixed $data, int $maxBytes): array
    {
        $truncated = false;
        $encoded = self::encode($this->shape($data, PHP_INT_MAX, PHP_INT_MAX, 0, $truncated));

        if (strlen($encoded) <= $maxBytes) {
            return [$encoded, $truncated];
        }

        // Start from limits that would roughly fill the budget, so as much
        // as possible is kept, then tighten until the encoding fits.
        [$longestList, $longestString] = $this->measure($data, 0);
        $scale = $maxBytes / strlen($encoded);
        $maxItems = max($this->maxItems, (int) floor($longestList * $scale * 0.9));
        $maxString = max($this->maxString, (int) floor($longestString * $scale * 0.9));
        $truncated = true;

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $encoded = self::encode($this->shape($data, $maxString, $maxItems, 0, $truncated));

            if (strlen($encoded) <= $maxBytes) {
                return [$encoded, $truncated];
            }

            if ($maxItems > $this->maxItems || $maxString > $this->maxString) {
                $maxItems = max($this->maxItems, (int) floor($maxItems * 0.7));
                $maxString = max($this->maxString, (int) floor($maxString * 0.7));
            } else {
                $maxString = max(16, intdiv($maxString, 4));
                $maxItems = max(1, intdiv($maxItems, 4));
            }
        }

        return [$this->text($encoded, $maxBytes), true];
    }

    /**
     * Cut plain text to at most $maxBytes, keeping valid UTF-8.
     */
    public function text(string $text, int $maxBytes): string
    {
        $total = strlen($text);
        if ($total <= $maxBytes) {
            return $text;
        }

        $marker = "\n…[truncated: kept %d of %d bytes]";
        $budget = max(0, $maxBytes - strlen(sprintf($marker, $maxBytes, $total)));
        $kept = mb_strcut($text, 0, $budget, 'UTF-8');

        return $kept.sprintf($marker, strlen($kept), $total);
    }

    /**
     * Longest array and longest string anywhere in the data (within depth).
     *
     * @return array{0: int, 1: int}
     */
    private function measure(mixed $value, int $depth): array
    {
        if (is_string($value)) {
            return [0, strlen($value)];
        }

        if (! is_array($value) || $depth >= $this->maxDepth) {
            return [0, 0];
        }

        $longestList = count($value);
        $longestString = 0;

        foreach ($value as $item) {
            [$list, $string] = $this->measure($item, $depth + 1);
            $longestList = max($longestList, $list);
            $longestString = max($longestString, $string);
        }

        return [$longestList, $longestString];
    }

    private function shape(mixed $value, int $maxString, int $maxItems, int $depth, bool &$truncated): mixed
    {
        if (is_string($value)) {
            return $this->shortenString($value, $maxString, $truncated);
        }

        if (is_array($value)) {
            if ($depth >= $this->maxDepth) {
                $truncated = true;

                return '[depth limit]';
            }

            return $this->shapeArray($value, $maxString, $maxItems, $depth, $truncated);
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return $this->shortenString((string) $value, $maxString, $truncated);
        }

        return is_object($value) ? '['.$value::class.']' : '['.gettype($value).']';
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function shapeArray(array $value, int $maxString, int $maxItems, int $depth, bool &$truncated): array
    {
        $count = count($value);
        $isList = array_is_list($value);
        $shaped = [];
        $index = 0;

        foreach ($value as $key => $item) {
            if ($index === $maxItems) {
                break;
            }

            $shaped[$key] = $this->shape($item, $maxString, $maxItems, $depth + 1, $truncated);
            $index++;
        }

        if ($count > $maxItems) {
            $truncated = true;
            $marker = sprintf('…[+%d %s]', $count - $maxItems, $isList ? 'items' : 'keys');

            if ($isList) {
                $shaped[] = $marker;
            } else {
                $shaped['…'] = $marker;
            }
        }

        return $shaped;
    }

    private function shortenString(string $value, int $maxString, bool &$truncated): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $truncated = true;

            return sprintf('[binary: %d bytes]', strlen($value));
        }

        $length = mb_strlen($value, 'UTF-8');
        if ($length <= $maxString) {
            return $value;
        }

        $truncated = true;

        return mb_substr($value, 0, $maxString, 'UTF-8').sprintf('…[+%d chars]', $length - $maxString);
    }
}
