<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Recording\Reason;

/**
 * @property string $id
 * @property string $reference
 * @property Reason $reason
 * @property string $method
 * @property string $url
 * @property string $path
 * @property string|null $route_uri
 * @property string|null $route_name
 * @property string|null $action
 * @property string|null $subject
 * @property int $status
 * @property string|null $user_id
 * @property string|null $tenant_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $hostname
 * @property string|null $revision
 * @property int $duration_ms
 * @property int $memory_peak_kb
 * @property int $query_count
 * @property int $query_time_ms
 * @property list<array{sql: string, time_ms: float, connection: string, caller: string|null}>|null $queries
 * @property string|null $request_headers
 * @property string|null $request_body
 * @property int $request_bytes
 * @property bool $request_truncated
 * @property string $response_kind
 * @property string|null $response_headers
 * @property string|null $response_body
 * @property int $response_bytes
 * @property bool $response_truncated
 * @property string|null $exception_class
 * @property string|null $exception_message
 * @property string|null $exception_location
 * @property string|null $exception_hash
 * @property string|null $exception_snippet
 * @property list<array{file: string|null, line: int|null, class: string|null, function: string|null, type: string|null}>|null $exception_trace
 * @property string|null $context
 * @property CarbonImmutable $created_at
 *
 * @method static LoggedRequestQuery query()
 */
class LoggedRequest extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * Times carry their UTC offset, so a timestamptz column stores the right
     * instant whatever the database session time zone is.
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = [
        'reference',
        'reason',
        'method',
        'url',
        'path',
        'route_uri',
        'route_name',
        'action',
        'subject',
        'status',
        'user_id',
        'tenant_id',
        'ip',
        'user_agent',
        'hostname',
        'revision',
        'duration_ms',
        'memory_peak_kb',
        'query_count',
        'query_time_ms',
        'queries',
        'request_headers',
        'request_body',
        'request_bytes',
        'request_truncated',
        'response_kind',
        'response_headers',
        'response_body',
        'response_bytes',
        'response_truncated',
        'exception_class',
        'exception_message',
        'exception_location',
        'exception_hash',
        'exception_snippet',
        'exception_trace',
        'context',
        'created_at',
    ];

    /**
     * Format a time for comparisons against created_at.
     */
    public static function formatTime(CarbonInterface $time): string
    {
        return $time->format('Y-m-d H:i:sP');
    }

    public function getConnectionName(): ?string
    {
        $connection = Config::get('request-log.storage.connection');

        return is_string($connection) && $connection !== '' ? $connection : parent::getConnectionName();
    }

    public function getTable(): string
    {
        return Config::string('request-log.storage.table');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): LoggedRequestQuery
    {
        return new LoggedRequestQuery($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => Reason::class,
            'status' => 'integer',
            'duration_ms' => 'integer',
            'memory_peak_kb' => 'integer',
            'query_count' => 'integer',
            'query_time_ms' => 'integer',
            'queries' => 'array',
            'request_bytes' => 'integer',
            'request_truncated' => 'boolean',
            'response_bytes' => 'integer',
            'response_truncated' => 'boolean',
            'exception_trace' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
