<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use OrsOmes\RequestLog\Support\ReferenceCode;

/**
 * @extends Builder<LoggedRequest>
 */
class LoggedRequestQuery extends Builder
{
    public const RANGES = ['15m' => 15, '1h' => 60, '6h' => 360, '24h' => 1440, '7d' => 10080];

    public const STATUS_CLASSES = ['2xx', '3xx', '4xx', '5xx'];

    /**
     * Escape LIKE wildcards with "!", which every supported driver accepts in
     * an ESCAPE clause (a backslash is not portable between SQLite and MySQL).
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * @param  array<array-key, mixed>  $filters  validated input, see Filters::rules()
     */
    public function filter(array $filters): self
    {
        $reference = self::stringFilter($filters, 'reference');
        if ($reference !== null) {
            $this->where('reference', ReferenceCode::normalize($reference));
        }

        foreach (['method' => 'method', 'reason' => 'reason', 'action' => 'action', 'route' => 'route_uri', 'user' => 'user_id', 'tenant' => 'tenant_id', 'subject' => 'subject', 'exception' => 'exception_hash'] as $input => $column) {
            $value = self::stringFilter($filters, $input);
            if ($value !== null) {
                $this->where($column, $input === 'method' ? strtoupper($value) : $value);
            }
        }

        $status = self::stringFilter($filters, 'status');
        if ($status !== null) {
            $this->whereStatus($status);
        }

        $path = self::stringFilter($filters, 'path');
        if ($path !== null) {
            $this->whereRaw("path like ? escape '!'", [self::escapeLike('/'.ltrim($path, '/')).'%']);
        }

        $this->whereTime($filters);

        return $this;
    }

    public function whereStatus(string $status): self
    {
        if (in_array($status, self::STATUS_CLASSES, true)) {
            $floor = (int) $status[0] * 100;

            return $this->whereBetween('status', [$floor, $floor + 99]);
        }

        return $this->where('status', (int) $status);
    }

    public function createdSince(CarbonImmutable $time): self
    {
        return $this->where('created_at', '>=', LoggedRequest::formatTime($time));
    }

    public function createdBefore(CarbonImmutable $time): self
    {
        return $this->where('created_at', '<', LoggedRequest::formatTime($time));
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('id');
    }

    public function olderThan(?string $id): self
    {
        return $id === null ? $this : $this->where('id', '<', strtolower($id));
    }

    /**
     * @param  array<array-key, mixed>  $filters
     */
    private static function stringFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<array-key, mixed>  $filters
     */
    private function whereTime(array $filters): void
    {
        $from = self::stringFilter($filters, 'from');
        $to = self::stringFilter($filters, 'to');

        if ($from !== null) {
            $this->createdSince(CarbonImmutable::parse($from));
        }

        if ($to !== null) {
            $this->where('created_at', '<=', LoggedRequest::formatTime(CarbonImmutable::parse($to)));
        }

        $range = self::stringFilter($filters, 'range');
        if ($from === null && $to === null && $range !== null && isset(self::RANGES[$range])) {
            $this->createdSince(CarbonImmutable::now()->subMinutes(self::RANGES[$range]));
        }
    }
}
