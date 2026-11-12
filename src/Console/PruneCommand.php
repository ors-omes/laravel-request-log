<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Models\LoggedRequestQuery;

final class PruneCommand extends Command
{
    protected $signature = 'request-log:prune
        {--days= : Delete records older than this many days (default: retention.days)}
        {--error-days= : Delete 5xx records older than this many days (default: retention.error_days)}';

    protected $description = 'Delete request log records past their retention period';

    public function handle(): int
    {
        $days = $this->days('days', 'request-log.retention.days');
        $errorDays = $this->days('error-days', 'request-log.retention.error_days');

        if ($days === null || $errorDays === null) {
            $this->error('Retention days must be zero or a positive integer.');

            return self::INVALID;
        }

        $now = CarbonImmutable::now();
        $deleted = $this->delete(static fn (LoggedRequestQuery $query): LoggedRequestQuery => $query
            ->createdBefore($now->subDays($days))
            ->where('status', '<', 500));
        $deleted += $this->delete(static fn (LoggedRequestQuery $query): LoggedRequestQuery => $query
            ->createdBefore($now->subDays($errorDays)));

        $this->info("Deleted {$deleted} request log record(s).");

        return self::SUCCESS;
    }

    /**
     * Deletes in primary-key chunks, which works the same on every driver.
     *
     * @param  callable(LoggedRequestQuery): LoggedRequestQuery  $constraint
     */
    private function delete(callable $constraint): int
    {
        $chunk = max(1, Config::integer('request-log.retention.chunk'));
        $deleted = 0;

        do {
            $ids = $constraint(LoggedRequest::query())->orderBy('id')->limit($chunk)->pluck('id')->all();
            $result = $ids === [] ? 0 : LoggedRequest::query()->whereKey($ids)->delete();
            $count = is_int($result) ? $result : 0;
            $deleted += $count;
        } while ($count === $chunk);

        return $deleted;
    }

    private function days(string $option, string $configKey): ?int
    {
        $value = $this->option($option) ?? Config::get($configKey);

        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }
}
