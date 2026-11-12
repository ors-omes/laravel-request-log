<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Support\Redactor;

/**
 * Scans stored records for values that look like secrets the redaction did
 * not catch. Meant for auditing real traffic before trusting a configuration;
 * exits with status 1 when anything is found.
 */
final class AuditCommand extends Command
{
    private const FIELDS = ['url', 'request_headers', 'request_body', 'response_headers', 'response_body', 'exception_message', 'context'];

    private const RULES = [
        'jwt' => '/eyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}/',
        'bearer' => '/\bBearer\s+[A-Za-z0-9._~+\/=-]{8,}/i',
        'basic-auth' => '/\bBasic\s+[A-Za-z0-9+\/=]{8,}/i',
        'secret-key' => '/"[^"]*(?:passw|secret|token|api[_-]?key|private[_-]?key|authorization|cookie)[^"]*"\s*:\s*"(?!\[REDACTED\])[^"]+"/i',
        'secret-query' => '/[?&][^=&]*(?:passw|secret|token|api[_-]?key)[^=&]*=(?!\[REDACTED\])[^&\s]+/i',
        'private-key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    ];

    protected $signature = 'request-log:audit
        {--since=24h : Look back this far (e.g. 30m, 6h, 7d)}
        {--limit=200 : Stop after this many findings}';

    protected $description = 'Report stored request log values that look like unredacted secrets';

    public function handle(): int
    {
        $option = $this->option('since');
        $since = is_string($option) ? $this->since($option) : null;
        if ($since === null) {
            $this->error('Invalid --since; use e.g. 30m, 6h or 7d.');

            return self::INVALID;
        }

        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : 200;
        $replacement = Config::string('request-log.redact.replacement');
        $redactor = new Redactor();
        $findings = [];
        $scanned = 0;

        LoggedRequest::query()
            ->createdSince($since)
            ->select(['id', 'reference', 'response_kind', ...self::FIELDS])
            ->chunkById(200, function ($entries) use (&$findings, &$scanned, $limit, $replacement, $redactor): bool {
                foreach ($entries as $entry) {
                    $scanned++;

                    foreach (self::FIELDS as $field) {
                        $value = $entry->getAttribute($field);
                        if (! is_string($value) || $value === '') {
                            continue;
                        }

                        foreach ($this->matches($value, $replacement) as $rule => $excerpt) {
                            $findings[] = [$entry->id, $entry->reference, $field, $rule, $excerpt];
                        }
                    }

                    foreach (self::unredacted($entry, $redactor) as [$field, $rule, $excerpt]) {
                        $findings[] = [$entry->id, $entry->reference, $field, $rule, $excerpt];
                    }

                    if (count($findings) >= $limit) {
                        $findings = array_slice($findings, 0, $limit);

                        return false;
                    }
                }

                return true;
            });

        if ($findings === []) {
            $this->info("No suspicious values in {$scanned} record(s).");

            return self::SUCCESS;
        }

        $this->table(['id', 'reference', 'field', 'rule', 'excerpt'], $findings);
        $this->warn(count($findings)." finding(s) in {$scanned} record(s). Add the keys to request-log.redact.extra_keys or a value pattern (url-header and sql-value findings are already redacted in new records), then prune affected rows.");

        return self::FAILURE;
    }

    private static function mask(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        return mb_strlen($value) <= 12 ? mb_substr($value, 0, 3).'…' : mb_substr($value, 0, 10).'…('.mb_strlen($value).' chars)';
    }

    /**
     * Values the current redaction would still change: URL headers and
     * redirect targets with query values or token-like path segments, and
     * database error messages that quote values. Catches records written
     * before a fix or with a narrower configuration.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function unredacted(LoggedRequest $entry, Redactor $redactor): array
    {
        $found = [];

        foreach (['request_headers', 'response_headers'] as $field) {
            $stored = $entry->getAttribute($field);
            $headers = is_string($stored) ? json_decode($stored, true) : null;

            foreach (is_array($headers) ? $headers : [] as $name => $value) {
                if (is_string($value) && $redactor->isUrlHeader((string) $name) && $redactor->url($value) !== $value) {
                    $found[] = [$field, 'url-header', $name.': '.self::mask($value)];
                }
            }
        }

        $target = $entry->getAttribute('response_body');
        if ($entry->getAttribute('response_kind') === 'redirect' && is_string($target) && $redactor->isUrlHeader('location') && $redactor->url($target) !== $target) {
            $found[] = ['response_body', 'url-header', self::mask($target)];
        }

        $message = $entry->getAttribute('exception_message');
        if (is_string($message) && str_starts_with($message, 'SQLSTATE[')) {
            $driverMessage = Str::beforeLast($message, ' (Connection: ');

            if ($redactor->driverMessage($driverMessage) !== $driverMessage) {
                $found[] = ['exception_message', 'sql-value', self::mask($driverMessage)];
            }
        }

        return $found;
    }

    private static function luhn(string $digits): bool
    {
        $length = strlen($digits);
        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < $length; $index++) {
            $digit = (int) $digits[$length - 1 - $index];
            if ($index % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /**
     * @return array<string, string> rule => masked excerpt
     */
    private function matches(string $value, string $replacement): array
    {
        $matches = [];

        foreach (self::RULES as $rule => $pattern) {
            if (preg_match($pattern, $value, $found) === 1 && ! str_contains($found[0], $replacement)) {
                $matches[$rule] = self::mask($found[0]);
            }
        }

        if (preg_match_all('/(?<![\d.])\d(?:[ -]?\d){12,18}(?![\d.])/', $value, $numbers) > 0) {
            foreach ($numbers[0] as $number) {
                if (self::luhn(preg_replace('/\D/', '', $number) ?? '')) {
                    $matches['card-number'] = self::mask($number);

                    break;
                }
            }
        }

        return $matches;
    }

    private function since(string $value): ?CarbonImmutable
    {
        if (preg_match('/^(\d+)([mhd])$/', trim($value), $parts) !== 1) {
            return null;
        }

        $amount = (int) $parts[1];

        return match ($parts[2]) {
            'm' => CarbonImmutable::now()->subMinutes($amount),
            'h' => CarbonImmutable::now()->subHours($amount),
            default => CarbonImmutable::now()->subDays($amount),
        };
    }
}
