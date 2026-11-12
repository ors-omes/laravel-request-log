---
description: Columns stored for each request, how oversized payloads are shortened, response kinds, uploads and exception capture.
---

# What is stored

Each recorded request is one row in `request_logs`. The full column list is in the [schema reference](/reference/schema).

| Group | Columns |
|---|---|
| Identity | `id` (ULID, time-ordered), `reference`, `reason`, `created_at` (with time zone) |
| Request | `method`, `url` (query redacted), `path`, `route_uri`, `route_name`, `action`, `subject` |
| Who and where | `user_id`, `tenant_id`, `ip`, `user_agent`, `hostname`, `revision` |
| Performance | `status`, `duration_ms`, `memory_peak_kb`, `query_count`, `query_time_ms`, `queries` |
| Bodies | `request_headers`, `request_body`, `request_bytes`, `request_truncated`, `response_kind`, `response_headers`, `response_body`, `response_bytes`, `response_truncated` |
| Exception | `exception_class`, `exception_message`, `exception_location`, `exception_hash`, `exception_snippet`, `exception_trace` |
| Extra | `context` (your [`contextUsing`](./hooks#context) data) |

- **`subject`** is the record the request acted on. It is taken from the last route-model-bound parameter (`Order:01J…`), or else the last id-like route parameter (`order:42`). Parameters named like a secret (`{token}`), models whose primary key is named like one, and raw values looked up by a secret column (`{invitation:token}`) are never used. You can override it with [`resolveSubjectUsing`](./hooks#subject).
- **`user_id`** is read only if authentication already resolved a user during the request. Recording never triggers a guard or an extra query.

## Large payloads are shortened, not discarded

Request bodies up to **512 KB** and response bodies up to **256 KB** are stored whole. Above the limit, the content keeps its shape and stays valid JSON:

```json
{
  "product_code": "ARM-BRACKET-01",
  "rows": [
    { "line": 1, "component": "RM-0001", "qty": 1.25 },
    "…",
    { "line": 1695, "component": "RM-1695", "qty": 2118.75 },
    "…[+905 items]"
  ],
  "note": "The first characters of a long string…[+20113 chars]"
}
```

- The kept part is sized to fill the limit. In the demo, a 733 KB BOM import keeps 1,695 of its 2,600 rows in 471 KB.
- `request_bytes` / `response_bytes` hold the original size, and `*_truncated` tells you something was cut.
- If the structure cannot shrink enough, the text is cut on a UTF-8 boundary and ends with `…[truncated: kept X of Y bytes]`.
- Bodies above `capture.max_parse_bytes` (4 MB) are not decoded at all; they are recorded as `[body too large to parse: N bytes]`.

![A large BOM import, shortened](/screenshots/payload-shortened.png){.screenshot}

## Request bodies

| Content type | Stored |
|---|---|
| JSON | The original payload as sent, redacted and shortened |
| Form / multipart | Parsed fields, redacted; files as `{file, size, mime}` |
| XML, CSV, text, other | `[unparsed text/xml: N bytes]`, unless `capture.raw_text_bodies` is on |
| Invalid JSON | `[invalid json: N bytes]` |

## Response bodies

| `response_kind` | Stored |
|---|---|
| `json` | Redacted, shortened body. This includes RFC 9457 problem responses, so validation errors stay readable. |
| `redirect` | Target URL with every query and fragment value and token-like path segments redacted (see [URL headers](./security#redaction)); `[omitted: redirect]` on metadata-only routes |
| `html` | `[html text/html: N bytes]`. HTML forms carry CSRF tokens, so the markup is not stored. |
| `text` | Placeholder, unless `capture.raw_text_bodies` is on |
| `stream`, `file`, `binary`, `empty` | A description only; streams are never read |

## Exceptions

The exception is read from the rendered response, where Laravel attaches it. As a fallback it comes from the reported log record, or from your own call to `RequestLog::captureException()`.

| Column | Content |
|---|---|
| `exception_class` | Fully-qualified class |
| `exception_message` | Message, redacted. Database errors keep the driver message and the SQL with `?` placeholders, without the values the driver quotes (see [Security](./security#exceptions)). |
| `exception_location` | `app/Services/WorkOrderService.php:14`, relative to the project |
| `exception_hash` | `md5(class + location)`, which groups every occurrence of the same failure |
| `exception_snippet` | Source lines around the first project frame (5xx only) |
| `exception_trace` | Frames up to the controller dispatch, without arguments (5xx only) |

![A server error with its source snippet](/screenshots/error-500.png){.screenshot}

## Query statistics

`query_count` and `query_time_ms` cover every query of the request. `queries` keeps the slowest queries (≥ `queries.slow_ms`, up to `queries.max_slow`) with the calling `file:line` in your code. Bindings are omitted unless `queries.bindings` is enabled.
