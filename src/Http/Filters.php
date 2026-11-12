<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Models\LoggedRequestQuery;
use OrsOmes\RequestLog\Recording\Reason;
use OrsOmes\RequestLog\RequestLog;

/**
 * Validation rules shared by the viewer and the API.
 */
final class Filters
{
    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /**
     * @return array<string, mixed>
     */
    public static function rules(int $maxPerPage): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:64'],
            'method' => ['nullable', 'string', Rule::in([...self::METHODS, ...array_map(strtolower(...), self::METHODS)])],
            'status' => ['nullable', 'string', 'regex:/^[1-5](xx|\d\d)$/'],
            'reason' => ['nullable', Rule::enum(Reason::class)],
            'action' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:255'],
            'user' => ['nullable', 'string', 'max:64'],
            'tenant' => ['nullable', 'string', 'max:64'],
            'subject' => ['nullable', 'string', 'max:255'],
            'exception' => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'range' => ['nullable', Rule::in(array_keys(LoggedRequestQuery::RANGES))],
            'before' => ['nullable', 'string', 'ulid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
        ];
    }

    /**
     * Base query for reading: applies the application's viewer scope.
     */
    public static function scoped(Request $request): LoggedRequestQuery
    {
        $query = LoggedRequest::query();

        $scope = RequestLog::viewerScopeCallback();
        if ($scope !== null) {
            $scope($query, $request);
        }

        return $query;
    }

    public static function perPage(mixed $value, string $area): int
    {
        $default = Config::integer("request-log.{$area}.per_page");

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }

    /**
     * Columns needed for list rows; bodies stay in the database.
     *
     * @return list<string>
     */
    public static function listColumns(): array
    {
        return [
            'id', 'reference', 'reason', 'method', 'path', 'route_uri', 'action', 'subject', 'status',
            'user_id', 'tenant_id', 'duration_ms', 'query_count', 'response_kind', 'exception_class',
            'request_bytes', 'request_truncated', 'response_bytes', 'response_truncated', 'created_at',
        ];
    }
}
