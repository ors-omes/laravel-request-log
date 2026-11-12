# Laravel Request Log

Structured, redacted and queryable HTTP request log for Laravel 12 and 13.

By default it records **every POST, PUT, PATCH and DELETE** and **every request answered with 4xx or 5xx (including GET)**. For each one it stores:

- the request payload and response body, redacted and shortened (never dropped);
- the exception, with source snippet, trimmed trace and a grouping hash;
- query statistics;
- the user, tenant, subject, route and action.

Each row is written to its own table with indexed columns, **after** the response has been sent. Every request gets a short reference code. The code goes into the Laravel `Context`, a response header and your error responses, so support can jump from a user's error message straight to the record.

> **Status: beta (`0.x`).** The table layout and configuration may still change before `1.0`. Run `request-log:audit` on real traffic before trusting a configuration (see [Auditing the data](#auditing-the-data)).

## Contents

- [Why](#why)
- [Requirements](#requirements)
- [Installation](#installation)
- [What gets recorded](#what-gets-recorded)
- [What is stored](#what-is-stored)
- [Security and privacy](#security-and-privacy)
- [Reference codes](#reference-codes)
- [Application hooks](#application-hooks)
- [Viewer and JSON API](#viewer-and-json-api)
- [Telescope](#telescope)
- [Retention](#retention)
- [Auditing the data](#auditing-the-data)
- [Configuration reference](#configuration-reference)
- [Development](#development)
- [Tiếng Việt](#tiếng-việt)

## Why

This package replaces the hand-copied "HTTP mutation log" middlewares that had drifted apart across several applications. It keeps their strengths: indexed columns, reference codes, exception snippets. It also borrows the useful parts of [Laravel Telescope]:

- recording after the response is sent;
- cheap user resolution;
- exception grouping;
- query statistics;
- keyset pagination;
- pruning with longer retention for errors.

Unlike Telescope, it is meant to run in production, with every field queryable and redaction on by default.

[Laravel Telescope]: https://laravel.com/docs/telescope

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- SQLite, PostgreSQL or MySQL/MariaDB

There is no dependency on Passport, Sanctum, Telescope or any action library.

## Installation

The package is installed from Git until it is published on Packagist:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/ors-omes/laravel-request-log" }
    ]
}
```

```bash
composer require ors-omes/laravel-request-log:^0.1@beta
php artisan migrate
```

The service provider is auto-discovered. It prepends the recorder to the global middleware stack, so there is nothing to add to your routes. To publish the config:

```bash
php artisan vendor:publish --tag=request-log-config
```

We recommend a dedicated database connection, which keeps log volume out of your application database and its backups:

```dotenv
REQUEST_LOG_DB_CONNECTION=pgsql_log
```

The migration runs on that connection. If you keep an old `http_mutation_logs` middleware, remove it from your routes; it is replaced, not extended.

## What gets recorded

| Reason (`reason` column) | When |
|---|---|
| `error` | Any method, status ≥ `record.min_error_status` (default 400) |
| `mutation` | Method listed in `record.methods` (default POST, PUT, PATCH, DELETE) |
| `slow` | Any request slower than `record.slow_ms` (off by default) |
| `sampled` | A random fraction `record.sample_rate` of the remaining requests (off by default) |
| `forced` | The route has `request-log:always`, or your `filterUsing` callback said so |

Paths in `record.ignore_paths` are skipped entirely: no reference, no Context, no query counting. The defaults are health checks, Telescope, Horizon, Pulse, Debugbar, Livewire, `broadcasting/auth`, and the viewer/API themselves.

Per route:

```php
Route::get('reports/{report}', ShowReport::class)->middleware('request-log:always');   // also log GET 200
Route::post('heartbeat', Heartbeat::class)->middleware('request-log:off');             // never log
Route::post('login', Login::class)->middleware('request-log:metadata');                // log, but no bodies
```

`record.metadata_only_paths` does the same as `request-log:metadata` by path pattern.

## What is stored

One row per recorded request in `request_logs`:

| Group | Columns |
|---|---|
| Identity | `id` (ULID, time-ordered), `reference`, `reason`, `created_at` (with time zone) |
| Request | `method`, `url` (redacted query), `path`, `route_uri` (`api/orders/{order}`), `route_name`, `action` (`App\Http\Controllers\OrderController@store`), `subject` (`Order:01J…`, from route model binding) |
| Who / where | `user_id`, `tenant_id`, `ip`, `user_agent`, `hostname`, `revision` (git SHA) |
| Performance | `status`, `duration_ms`, `memory_peak_kb`, `query_count`, `query_time_ms`, `queries` (slowest queries, SQL without bindings, with the calling file:line) |
| Bodies | `request_headers`, `request_body`, `request_bytes`, `request_truncated`, `response_kind`, `response_headers`, `response_body`, `response_bytes`, `response_truncated` |
| Exception | `exception_class`, `exception_message`, `exception_location`, `exception_hash`, `exception_snippet`, `exception_trace` |
| Extra | `context` (whatever your `contextUsing` callback returns) |

### Large payloads are shortened, not discarded

Defaults: request bodies up to **512 KB**, response bodies up to **256 KB**. Content within the limit is stored whole. Content above it keeps its shape:

```json
{
    "lines": [ {"sku": "A-1"}, {"sku": "A-2"}, "…[+1498 items]" ],
    "description": "The first 8192 characters…[+20113 chars]"
}
```

The rules:

- The stored JSON stays valid.
- `request_bytes` / `response_bytes` keep the original size.
- `*_truncated` tells you something was cut.
- If the limit is still exceeded after shrinking, the text is cut on a UTF-8 boundary and ends with `…[truncated: kept X of Y bytes]`.
- Bodies above `capture.max_parse_bytes` (4 MB) are not decoded at all.

### Response types

| `response_kind` | Stored |
|---|---|
| `json` | Redacted, shortened body. This includes RFC 9457 problem responses, so validation errors stay readable. |
| `redirect` | Target URL, query redacted |
| `html` | `[html text/html: N bytes]`. HTML forms carry CSRF tokens. |
| `text` | Placeholder, unless `capture.raw_text_bodies` is on |
| `stream`, `file`, `binary`, `empty` | Description only; streams are never read |

Uploaded files are stored as `{file, size, mime}`. File contents are never stored.

## Security and privacy

The defaults are chosen so that turning the package on does not create a second copy of your users' secrets.

**Redaction**
- Keys are matched case-insensitively (`-` reads as `_`) at any depth, in request bodies, query strings, route parameters in the path, response bodies, headers and context.
- The default patterns cover `*password*`, `*secret*`, `*token*`, `*api_key*`, `*private_key*`, `signature`, `authorization`, `cookie`, `otp`, `pin`, `cvv`, `*card_number*` and more.
- Matching values are replaced with `[REDACTED]`, **including empty values**.
- Value patterns (JWT, `Bearer …`, `Basic …`) are removed from every stored string, even under harmless keys. A token pasted into a `note` field is caught.
- Request and response headers have separate lists (`authorization`, `cookie`, `x-api-key`, … / `set-cookie`, …).
- Bodies that cannot be redacted by key (XML, CSV, plain text, HTML) are not stored by default.
- Add your own keys in `redact.extra_keys`.

**Exceptions**
- Paths are stored relative to the project.
- Frames have no arguments.
- The snippet and trace are only stored for status ≥ 500.
- `QueryException` messages are stored **without bound values**: the SQL keeps its `?` placeholders, and PostgreSQL `DETAIL:` lines and MySQL duplicate-entry values are removed.

**Access**
- The viewer is allowed in the `local` environment or through the `viewRequestLogs` gate. Outside `local`, the package's own gate denies everyone until you define it.
- The JSON API is **disabled by default** and always checks the same gate.
- Exception snippets contain source code. In multi-tenant applications, grant the gate to platform operators only, not to customer admins.

**Viewer hardening**
- No JavaScript.
- Everything is escaped.
- A strict Content-Security-Policy with a per-request style nonce, plus `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer` and `Cache-Control: no-store`.
- LIKE wildcards in filters are escaped.
- No `COUNT(*)` or `DISTINCT` scans over the table.

**Isolation**
- Recording happens after the response is sent.
- Every failure is swallowed and logged as a warning with no payload, so logging can never break or slow down a request.

**Retention**
- Records are pruned daily: 30 days, 90 days for 5xx (see [Retention](#retention)).

Report vulnerabilities privately, see [SECURITY.md](SECURITY.md).

## Reference codes

Every considered request gets a 10-character Crockford base32 code (about 50 bits), for example `7KQ2M9XHTA`. It appears in four places:

- the **Laravel Context** under `request_reference`, so it is attached to every log record and carried into queued jobs dispatched by the request;
- the **response header** `X-Request-Reference`, for recorded requests only;
- your **error responses**, via `RequestLog::referenceFor($status)`;
- **Telescope** entries as the tag `ref:<code>`, when Telescope is installed.

Adding it to an RFC 9457 problem renderer:

```php
use OrsOmes\RequestLog\RequestLog;

$problem['reference_code'] = RequestLog::referenceFor($status);
```

`referenceFor()` returns `null` when the request will **not** be recorded with that status (ignored status, `request-log:off`, …). So a code shown to a user always resolves to a row.

Lookups accept pasted values: `[7kq2-m9xh-ta]` finds `7KQ2M9XHTA`. `I`/`L` read as `1` and `O` reads as `0`.

## Application hooks

Register these in a service provider's `boot()` method. They are closures, so `config:cache` keeps working.

```php
use OrsOmes\RequestLog\RequestLog;

// Who is the tenant? (stored in tenant_id)
RequestLog::resolveTenantUsing(fn (Request $request) => $request->user()?->organization_id);

// What record did the request act on? Default: last route-model-bound parameter.
RequestLog::resolveSubjectUsing(fn (Request $request) => ...);

// Extra data for the context column (redacted like everything else).
RequestLog::contextUsing(fn (Request $request, Response $response) => ['feature' => 'checkout']);

// Override the decision. $default is what the profile decided; e.g. also record
// legacy envelopes that report failure with HTTP 200.
RequestLog::filterUsing(fn (Request $request, Response $response, bool $default): bool =>
    $default || ($response instanceof JsonResponse && ($response->getData(true)['success'] ?? true) === false));

// Viewer/API: labels for user ids, user search, and a scope (e.g. per tenant).
RequestLog::resolveUserLabelsUsing(fn (array $ids) => User::whereKey($ids)->pluck('name', 'id')->all());
RequestLog::searchUsersUsing(fn (string $q, int $limit) => User::where('name', 'like', "$q%")->limit($limit)->pluck('name', 'id')->all());
RequestLog::scopeViewerUsing(fn ($query, Request $request) => $query->where('tenant_id', $request->user()->organization_id));

// When your renderer catches exceptions the framework does not attach to the response:
RequestLog::captureException($throwable);
```

## Viewer and JSON API

**Viewer** — `GET /request-logs`. Server-rendered, no build step.

- Filters: reference, method, status (`500` or `5xx`), reason, action, route, path prefix, user, tenant, subject, exception group, and time range.
- Keyset pagination.
- The detail page shows bodies, headers, exception, slow queries, "same exception", a link into Telescope, and a **Markdown** export for issues and chat.

Grant access:

```php
Gate::define('viewRequestLogs', fn (User $user) => $user->can('request-logs.view'));
```

The viewer uses the `web` middleware group by default. Applications that authenticate APIs with tokens only can put it behind another guard:

```php
// config/request-log.php
'viewer' => ['middleware' => ['web', 'auth.basic'], ...],
```

**JSON API** — enable it with `REQUEST_LOG_API_ENABLED=true` and add your auth middleware (`'api' => ['middleware' => ['api', 'auth:sanctum']]`).

| Endpoint | Returns |
|---|---|
| `GET /api/request-logs` | Cursor-paginated list; `per_page` ≤ 100; same filters as the viewer |
| `GET /api/request-logs/{id}` | Full record; bodies as stored text |
| `GET /api/request-logs/{id}/markdown` | Markdown export |
| `GET /api/request-logs/suggest?kind=action\|user&q=` | Up to 20 suggestions |

Users are exposed as `{id, label}` only.

## Telescope

When `laravel/telescope` is installed, every Telescope entry of a considered request is tagged `ref:<reference>`. The viewer then links to `/telescope/requests?tag=ref:…`, which shows the request's whole batch: queries, logs, jobs and outgoing HTTP calls.

Telescope's default filter keeps only the request entry of a failed request and drops its queries. To keep the whole batch whenever something failed:

```php
Telescope::filterBatch(fn (Collection $entries) => $entries->contains(
    fn (IncomingEntry $entry) => $entry->isReportableException() || $entry->isFailedRequest() || $entry->isFailedJob()
));
```

The two tools split the work:

| Package | Purpose | Storage |
|---|---|---|
| Request log | Always-on audit trail | Indexed columns, kept for weeks |
| Telescope | Deep debugging | Everything, kept for hours |

## Retention

```bash
php artisan request-log:prune                    # retention.days (30) / retention.error_days (90, status ≥ 500)
php artisan request-log:prune --days=7 --error-days=30
```

The package schedules `request-log:prune` daily at 03:15. Set `retention.schedule` to `false` to schedule it yourself.

## Auditing the data

During the beta, run this on real traffic before trusting a configuration:

```bash
php artisan request-log:audit --since=24h
```

It scans stored URLs, headers, bodies, exception messages and context for:

- JWTs;
- `Bearer` / `Basic` credentials;
- secret-looking keys that still have a value;
- secret query parameters;
- private keys;
- card numbers (Luhn-checked).

It prints masked excerpts and exits with status 1 if it finds anything. Add the missing keys to `redact.extra_keys`, then prune the affected rows.

## Configuration reference

All options live in [`config/request-log.php`](config/request-log.php), with comments.

| Variable | Default | Meaning |
|---|---|---|
| `REQUEST_LOG_ENABLED` | `true` | Master switch |
| `REQUEST_LOG_DB_CONNECTION` | default connection | Storage connection |
| `REQUEST_LOG_TABLE` | `request_logs` | Table name |
| `REQUEST_LOG_HEADER` | `X-Request-Reference` | Response header (empty to disable) |
| `REQUEST_LOG_SLOW_MS` | off | Also record requests slower than this |
| `REQUEST_LOG_SAMPLE_RATE` | `0` | Also record this fraction of other requests |
| `REQUEST_LOG_REQUEST_BODY_BYTES` | `524288` | Request body limit |
| `REQUEST_LOG_RESPONSE_BODY_BYTES` | `262144` | Response body limit |
| `REQUEST_LOG_MAX_PARSE_BYTES` | `4194304` | Larger bodies are not decoded |
| `REQUEST_LOG_IP` | `full` | `full`, `masked` (last IPv4 octet / IPv6 /48) or `none` |
| `REQUEST_LOG_REVISION` / `APP_GIT_SHA_1` | read from `.git` | Deployed revision |
| `REQUEST_LOG_RETENTION_DAYS` | `30` | Retention |
| `REQUEST_LOG_ERROR_RETENTION_DAYS` | `90` | Retention for status ≥ 500 |
| `REQUEST_LOG_VIEWER_ENABLED` / `_PATH` / `_DOMAIN` | `true` / `request-logs` / – | Viewer |
| `REQUEST_LOG_API_ENABLED` / `_PREFIX` | `false` / `api/request-logs` | JSON API |

## Development

```bash
composer install
composer test          # Pest + Testbench on SQLite
composer analyse       # PHPStan level max, strict rules, Larastan
composer format:test   # Pint
REQUEST_LOG_TEST_DB=pgsql DB_DATABASE=request_log_test DB_USERNAME=postgres composer test   # against PostgreSQL
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Tiếng Việt

Package ghi nhật ký request cho Laravel. Mặc định nó ghi mọi POST/PUT/PATCH/DELETE và mọi request trả 4xx/5xx, kể cả GET. Mỗi dòng gồm:

- payload và response (đã che dữ liệu nhạy cảm, quá dài thì rút gọn có đánh dấu `…[+N]` thay vì bỏ cả);
- exception kèm đoạn mã nguồn, trace và mã nhóm lỗi;
- thống kê truy vấn;
- người dùng, tenant, đối tượng bị tác động.

Mã tham chiếu 10 ký tự được đưa vào `Context`, header `X-Request-Reference` và `reference_code` của response lỗi (qua `RequestLog::referenceFor($status)`).

Viewer ở `/request-logs` mở cho môi trường `local` hoặc cho người qua gate `viewRequestLogs`. API JSON tắt mặc định.

Trong giai đoạn beta, hãy chạy `php artisan request-log:audit --since=24h` trên dữ liệu thật để kiểm tra còn bí mật nào bị lọt không.

## License

MIT. See [LICENSE.md](LICENSE.md).
