<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Support;

use OrsOmes\RequestLog\Models\LoggedRequest;

/**
 * Plain-text report of one record for issues and chat. Code fences grow to
 * one backtick more than the longest run in their content, so stored data
 * can never break out of a block.
 */
final class Markdown
{
    public static function render(LoggedRequest $entry, ?string $userLabel): string
    {
        $lines = [
            '# Request log '.$entry->reference,
            '',
            '- Reference: '.self::code($entry->reference),
            '- Time: '.self::code($entry->created_at->toIso8601String()),
            '- Request: '.self::code($entry->method.' '.$entry->url),
            '- Route: '.self::optional($entry->route_uri).($entry->route_name === null ? '' : ' ('.self::code($entry->route_name).')'),
            '- Action: '.self::optional($entry->action),
            '- Subject: '.self::optional($entry->subject),
            '- Status: '.self::code((string) $entry->status).' · reason '.self::code($entry->reason->value).' · response '.self::code($entry->response_kind),
            '- User: '.($entry->user_id === null ? '_null_' : self::code($userLabel !== null && $userLabel !== $entry->user_id ? $userLabel.' ('.$entry->user_id.')' : $entry->user_id)),
            '- Tenant: '.self::optional($entry->tenant_id),
            '- Duration: '.$entry->duration_ms.' ms · queries '.$entry->query_count.' ('.$entry->query_time_ms.' ms) · memory '.$entry->memory_peak_kb.' KB',
            '- Revision: '.self::optional($entry->revision),
            '- Host: '.self::optional($entry->hostname).' · IP: '.self::optional($entry->ip),
        ];

        self::section($lines, 'Request body'.self::sizeNote($entry->request_bytes, $entry->request_truncated), Display::pretty($entry->request_body), 'json');
        self::section($lines, 'Response body'.self::sizeNote($entry->response_bytes, $entry->response_truncated), Display::pretty($entry->response_body), 'json');

        if ($entry->exception_class !== null) {
            $lines[] = '';
            $lines[] = '## Exception';
            $lines[] = '';
            $lines[] = self::code($entry->exception_class).' at '.self::optional($entry->exception_location);
            self::block($lines, (string) $entry->exception_message, 'text');
        }

        self::section($lines, 'Code snippet', $entry->exception_snippet, 'text');
        self::section($lines, 'Trace', self::trace($entry->exception_trace), 'text');
        self::section($lines, 'Slow queries', self::queries($entry->queries), 'sql');
        self::section($lines, 'Context', Display::pretty($entry->context), 'json');
        self::section($lines, 'Request headers', Display::pretty($entry->request_headers), 'json');
        self::section($lines, 'Response headers', Display::pretty($entry->response_headers), 'json');

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array{file: string|null, line: int|null, class: string|null, function: string|null, type: string|null}  $frame
     */
    public static function frame(array $frame): string
    {
        $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
        $location = $frame['file'] === null ? '' : $frame['file'].':'.($frame['line'] ?? 0);

        return trim($location.' '.$call);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function section(array &$lines, string $title, ?string $content, string $language): void
    {
        if ($content === null || $content === '') {
            return;
        }

        $lines[] = '';
        $lines[] = '## '.$title;
        self::block($lines, $content, $language);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function block(array &$lines, string $content, string $language): void
    {
        preg_match_all('/`+/', $content, $runs);
        $longest = max([0, ...array_map(strlen(...), $runs[0])]);
        $fence = str_repeat('`', max(3, $longest + 1));

        $lines[] = '';
        $lines[] = $fence.$language;
        $lines[] = $content;
        $lines[] = $fence;
    }

    private static function code(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        preg_match_all('/`+/', $value, $runs);
        $ticks = str_repeat('`', max([0, ...array_map(strlen(...), $runs[0])]) + 1);
        $padding = str_starts_with($value, '`') || str_ends_with($value, '`') ? ' ' : '';

        return $ticks.$padding.$value.$padding.$ticks;
    }

    private static function optional(?string $value): string
    {
        return $value === null || $value === '' ? '_null_' : self::code($value);
    }

    private static function sizeNote(int $bytes, bool $truncated): string
    {
        return $bytes === 0 ? '' : ' ('.Display::bytes($bytes).($truncated ? ', shortened' : '').')';
    }

    /**
     * @param  list<array{file: string|null, line: int|null, class: string|null, function: string|null, type: string|null}>|null  $trace
     */
    private static function trace(?array $trace): ?string
    {
        if ($trace === null || $trace === []) {
            return null;
        }

        return implode("\n", array_map(static fn (array $frame): string => self::frame($frame), $trace));
    }

    /**
     * @param  list<array{sql: string, time_ms: float, connection: string, caller: string|null}>|null  $queries
     */
    private static function queries(?array $queries): ?string
    {
        if ($queries === null || $queries === []) {
            return null;
        }

        return implode("\n\n", array_map(
            static fn (array $query): string => sprintf('-- %s ms on %s%s', $query['time_ms'], $query['connection'], $query['caller'] === null ? '' : ' from '.$query['caller'])."\n".$query['sql'],
            $queries,
        ));
    }
}
