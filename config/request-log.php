<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('REQUEST_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | A dedicated connection (e.g. "pgsql_log") keeps log volume out of the
    | application database and its backups. Null uses the default connection.
    |
    */

    'storage' => [
        'connection' => env('REQUEST_LOG_DB_CONNECTION'),
        'table' => env('REQUEST_LOG_TABLE', 'request_logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | When enabled the recorder is prepended to the global HTTP middleware
    | stack, so it sees every request, including 404s for unknown routes and
    | failures raised by other global middleware.
    |
    */

    'middleware' => [
        'auto_register' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference code
    |--------------------------------------------------------------------------
    |
    | Each considered request gets a random 10 character reference. It is put
    | in the Laravel Context (so it appears in log records and travels with
    | queued jobs) and, for recorded requests, in a response header.
    |
    */

    'reference' => [
        'header' => env('REQUEST_LOG_HEADER', 'X-Request-Reference'),
        'context_key' => 'request_reference',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recording profile
    |--------------------------------------------------------------------------
    |
    | Default profile: every request whose method is listed in "methods" and
    | every request (any method) answered with a status >= "min_error_status".
    | GET requests can additionally be recorded when slow, sampled, or forced
    | per route with the "request-log:always" middleware.
    |
    | Path patterns use Str::is() against the path without leading slash.
    | Action patterns match both "Fully\Qualified\Class@method" and
    | "Class@method".
    |
    */

    'record' => [
        'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
        'min_error_status' => 400,
        'ignore_statuses' => [],
        'slow_ms' => env('REQUEST_LOG_SLOW_MS'),
        'sample_rate' => (float) env('REQUEST_LOG_SAMPLE_RATE', 0),
        'only_paths' => [],
        'ignore_paths' => [
            'up',
            'telescope*',
            'horizon*',
            'pulse*',
            '_debugbar*',
            '_boost*',
            'livewire*',
            'broadcasting/auth',
            '.well-known*',
        ],
        'ignore_actions' => [],
        'metadata_only_paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capture limits
    |--------------------------------------------------------------------------
    |
    | Sizes are bytes. Bodies above "max_parse_bytes" are not decoded at all.
    | Content within the byte limit is stored whole. Oversized content is
    | shortened, never replaced: strings longer than "max_string" keep their
    | head plus "…[+N chars]", arrays longer than "max_items" keep their first
    | items plus "…[+N items]" (both limits shrink until the content fits),
    | and as a last resort the encoded text is cut on a UTF-8 boundary with
    | "…[truncated: kept X of Y bytes]".
    |
    | Non-JSON text bodies (XML, CSV, plain text, HTML) cannot be redacted by
    | key, so only their type and size are stored unless "raw_text_bodies"
    | is enabled.
    |
    */

    'capture' => [
        'request_headers' => true,
        'response_headers' => true,
        'request_body_bytes' => (int) env('REQUEST_LOG_REQUEST_BODY_BYTES', 524288),
        'response_body_bytes' => (int) env('REQUEST_LOG_RESPONSE_BODY_BYTES', 262144),
        'header_bytes' => 16384,
        'max_parse_bytes' => (int) env('REQUEST_LOG_MAX_PARSE_BYTES', 4194304),
        'max_string' => 8192,
        'max_items' => 500,
        'max_depth' => 12,
        'raw_text_bodies' => false,
        'ip' => env('REQUEST_LOG_IP', 'full'),
        'user_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Keys are compared lower-cased with "-" turned into "_", at any depth, in
    | request bodies, query strings, route parameters, response bodies,
    | headers and context. Matching values are replaced even when empty.
    | Value patterns are applied to every string that is stored.
    |
    | Headers in "url_headers" carry a URL whose parameters the package cannot
    | know (redirect target, previous page, original URI behind a proxy). They
    | keep their path and parameter names, but every query and fragment value
    | is replaced, and so is any path segment that looks like a token (a run
    | of 16+ mixed letters and digits that is not a ULID). The redirect target
    | stored as the response body follows the "location" entry. Remove a
    | header from the list to store it as sent.
    |
    */

    'redact' => [
        'replacement' => '[REDACTED]',
        'keys' => [
            '*password*',
            '*passwd*',
            '*secret*',
            '*token*',
            '*api_key*',
            '*apikey*',
            '*private_key*',
            'signature',
            'authorization',
            'proxy_authorization',
            'cookie',
            'set_cookie',
            'otp',
            '*_otp',
            'otp_*',
            'pin',
            '*_pin',
            'pin_*',
            'cvv',
            'cvc',
            '*card_number*',
            'credit_card',
            'ssn',
        ],
        'extra_keys' => [],
        'request_headers' => [
            'authorization',
            'proxy-authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
            'x-xsrf-token',
            'php-auth-pw',
        ],
        'response_headers' => [
            'set-cookie',
            'authorization',
        ],
        'url_headers' => [
            'location',
            'content-location',
            'refresh',
            'referer',
            'x-forwarded-uri',
            'x-original-uri',
            'x-original-url',
            'x-rewrite-url',
        ],
        'value_patterns' => [
            '/\beyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}/',
            '/\bBearer\s+[A-Za-z0-9._~+\/=-]{8,}/i',
            '/\bBasic\s+[A-Za-z0-9+\/=]{8,}/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    |
    | Class, message and location are stored for every recorded exception.
    | The source snippet and trace (no arguments, paths relative to the base
    | path) are stored only when the status is >= "debug_min_status".
    |
    | Database errors (QueryException, PDOException) keep the driver message
    | and the SQL with its "?" placeholders, but not the values: PostgreSQL
    | DETAIL/CONTEXT lines are dropped and quoted values such as
    | `invalid input syntax for type integer: "…"` or `Duplicate entry '…'`
    | are replaced. Identifiers (`relation "orders"`) stay. Enable
    | "query_bindings" to store Laravel's full message with bound values.
    |
    */

    'exceptions' => [
        'debug_min_status' => 500,
        'context_lines' => 5,
        'max_frames' => 30,
        'message_bytes' => 2000,
        'project_paths' => ['app', 'modules', 'src', 'routes', 'database'],
        'query_bindings' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query statistics
    |--------------------------------------------------------------------------
    */

    'queries' => [
        'enabled' => true,
        'slow_ms' => 100,
        'max_slow' => 10,
        'bindings' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Revision
    |--------------------------------------------------------------------------
    |
    | Deployed revision stored with each record. When empty the package reads
    | .git (HEAD, loose refs, packed-refs, worktrees) once per process.
    |
    */

    'revision' => env('REQUEST_LOG_REVISION', env('APP_GIT_SHA_1')),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | request-log:prune deletes records older than "days", and records with a
    | status >= 500 older than "error_days". It is scheduled daily unless
    | "schedule" is false.
    |
    */

    'retention' => [
        'days' => (int) env('REQUEST_LOG_RETENTION_DAYS', 30),
        'error_days' => (int) env('REQUEST_LOG_ERROR_RETENTION_DAYS', 90),
        'schedule' => true,
        'chunk' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewer
    |--------------------------------------------------------------------------
    |
    | Server-rendered pages without JavaScript. Access requires the "local"
    | environment (when "allow_local" is true) or the "viewRequestLogs" gate,
    | which denies everyone until the application defines it.
    |
    */

    'viewer' => [
        'enabled' => (bool) env('REQUEST_LOG_VIEWER_ENABLED', true),
        'path' => env('REQUEST_LOG_VIEWER_PATH', 'request-logs'),
        'domain' => env('REQUEST_LOG_VIEWER_DOMAIN'),
        'middleware' => ['web'],
        'allow_local' => true,
        'per_page' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    |
    | Disabled by default. Add your authentication middleware (for example
    | "auth:api" or "auth:sanctum"); the "viewRequestLogs" gate is always
    | enforced on top of it.
    |
    */

    'api' => [
        'enabled' => (bool) env('REQUEST_LOG_API_ENABLED', false),
        'prefix' => env('REQUEST_LOG_API_PREFIX', 'api/request-logs'),
        'middleware' => ['api'],
        'allow_local' => false,
        'per_page' => 50,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope
    |--------------------------------------------------------------------------
    |
    | When Laravel Telescope is installed, tag its entries with
    | "ref:<reference>" so the viewer can link to the whole Telescope batch
    | (queries, logs, jobs) of a recorded request.
    |
    */

    'telescope' => [
        'enabled' => true,
    ],

];
