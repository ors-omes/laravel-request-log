<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http;

use OrsOmes\RequestLog\Models\LoggedRequest;

/**
 * JSON shape of the API. Users are exposed as id + label only.
 */
final class Presenter
{
    /**
     * @param  array<string, string>  $labels
     * @return array<string, mixed>
     */
    public static function summary(LoggedRequest $entry, array $labels): array
    {
        return [
            'id' => $entry->id,
            'reference' => $entry->reference,
            'reason' => $entry->reason->value,
            'method' => $entry->method,
            'path' => $entry->path,
            'route_uri' => $entry->route_uri,
            'action' => $entry->action,
            'subject' => $entry->subject,
            'status' => $entry->status,
            'user' => $entry->user_id === null ? null : ['id' => $entry->user_id, 'label' => $labels[$entry->user_id] ?? $entry->user_id],
            'tenant_id' => $entry->tenant_id,
            'duration_ms' => $entry->duration_ms,
            'query_count' => $entry->query_count,
            'response_kind' => $entry->response_kind,
            'exception_class' => $entry->exception_class,
            'request_bytes' => $entry->request_bytes,
            'request_truncated' => $entry->request_truncated,
            'response_bytes' => $entry->response_bytes,
            'response_truncated' => $entry->response_truncated,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }

    /**
     * Bodies, headers and context are returned as stored text (JSON when
     * parseable; placeholders and last-resort cuts are plain text).
     *
     * @param  array<string, string>  $labels
     * @return array<string, mixed>
     */
    public static function detail(LoggedRequest $entry, array $labels): array
    {
        return [
            ...self::summary($entry, $labels),
            'url' => $entry->url,
            'route_name' => $entry->route_name,
            'ip' => $entry->ip,
            'user_agent' => $entry->user_agent,
            'hostname' => $entry->hostname,
            'revision' => $entry->revision,
            'memory_peak_kb' => $entry->memory_peak_kb,
            'query_time_ms' => $entry->query_time_ms,
            'queries' => $entry->queries,
            'request_headers' => $entry->request_headers,
            'request_body' => $entry->request_body,
            'response_headers' => $entry->response_headers,
            'response_body' => $entry->response_body,
            'exception' => $entry->exception_class === null ? null : [
                'class' => $entry->exception_class,
                'message' => $entry->exception_message,
                'location' => $entry->exception_location,
                'hash' => $entry->exception_hash,
                'snippet' => $entry->exception_snippet,
                'trace' => $entry->exception_trace,
            ],
            'context' => $entry->context,
        ];
    }
}
