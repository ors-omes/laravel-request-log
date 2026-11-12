<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Capture;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Support\Redactor;
use OrsOmes\RequestLog\Support\Settings;

/**
 * Counts queries of the current request and keeps the slowest ones. Bound as
 * a scoped instance; start() resets it at the beginning of each request.
 */
final class QueryCollector
{
    private const SQL_BYTES = 2000;

    private bool $active = false;

    private int $count = 0;

    private float $timeMs = 0.0;

    /** @var list<array{sql: string, time_ms: float, connection: string, caller: string|null}> */
    private array $slow = [];

    public function start(): void
    {
        $this->active = true;
        $this->count = 0;
        $this->timeMs = 0.0;
        $this->slow = [];
    }

    public function stop(): void
    {
        $this->active = false;
    }

    public function record(QueryExecuted $event): void
    {
        if (! $this->active) {
            return;
        }

        $time = $event->time ?? 0.0;
        $this->count++;
        $this->timeMs += $time;

        if ($time < Settings::number('request-log.queries.slow_ms', 100.0)) {
            return;
        }

        $maxSlow = Config::integer('request-log.queries.max_slow');
        if ($maxSlow <= 0) {
            return;
        }

        if (count($this->slow) >= $maxSlow) {
            $fastest = 0;
            foreach ($this->slow as $index => $query) {
                if ($query['time_ms'] < $this->slow[$fastest]['time_ms']) {
                    $fastest = $index;
                }
            }

            if ($this->slow[$fastest]['time_ms'] >= $time) {
                return;
            }

            array_splice($this->slow, $fastest, 1);
        }

        $this->slow[] = [
            'sql' => $this->sql($event),
            'time_ms' => round($time, 2),
            'connection' => $event->connectionName,
            'caller' => $this->caller(),
        ];
    }

    /**
     * @return array{count: int, time_ms: int, slow: list<array{sql: string, time_ms: float, connection: string, caller: string|null}>}
     */
    public function summary(): array
    {
        $slow = $this->slow;
        usort($slow, static fn (array $left, array $right): int => $right['time_ms'] <=> $left['time_ms']);

        return ['count' => $this->count, 'time_ms' => (int) round($this->timeMs), 'slow' => $slow];
    }

    private function sql(QueryExecuted $event): string
    {
        $sql = Config::boolean('request-log.queries.bindings') ? app(Redactor::class)->string($event->toRawSql()) : $event->sql;

        return mb_strcut($sql, 0, self::SQL_BYTES, 'UTF-8');
    }

    private function caller(): ?string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $file = $frame['file'] ?? '';

            if ($file !== '' && ExceptionCapture::isProjectFile($file)) {
                return ExceptionCapture::relative($file).':'.($frame['line'] ?? 0);
            }
        }

        return null;
    }
}
