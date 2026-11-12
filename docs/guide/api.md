---
description: The optional JSON API — enabling it, authentication, endpoints, filters, cursor pagination and response shapes.
---

# JSON API

The JSON API lets you build your own admin screens, for example in Vue or React. It is **disabled by default**.

```dotenv
REQUEST_LOG_API_ENABLED=true
```

```php
// config/request-log.php
'api' => [
    'enabled' => env('REQUEST_LOG_API_ENABLED', false),
    'prefix' => 'api/request-logs',
    'middleware' => ['api', 'auth:sanctum'],   // or auth:api for Passport
    'allow_local' => false,
    'per_page' => 50,
    'max_per_page' => 100,
],
```

The `viewRequestLogs` gate is always enforced on top of your middleware.

## Endpoints

| Method | Path | Returns |
|---|---|---|
| GET | `/api/request-logs` | Cursor-paginated list |
| GET | `/api/request-logs/{id}` | Full record |
| GET | `/api/request-logs/{id}/markdown` | Markdown export (`text/markdown`) |
| GET | `/api/request-logs/suggest?kind=action\|user&q=` | Up to 20 suggestions |

Responses are plain JSON with no envelope. Validation errors are thrown as `ValidationException`, so your own exception renderer formats them.

## List

The list accepts the same filters as the viewer (`reference`, `method`, `status`, `reason`, `action`, `route`, `path`, `user`, `tenant`, `subject`, `exception`, `from`, `to`, `range`), plus `per_page` (at most `max_per_page`) and `cursor`.

```json
{
  "data": [
    {
      "id": "01m771b5w7t5bfj7mm6z3wzfq9",
      "reference": "TASHGJ465T",
      "reason": "error",
      "method": "POST",
      "path": "/api/work-orders/12/complete",
      "route_uri": "api/work-orders/{workOrder}/complete",
      "action": "App\\Http\\Controllers\\Production\\WorkOrderController@complete",
      "subject": "workOrder:12",
      "status": 500,
      "user": { "id": "2", "label": "Ben Turner" },
      "tenant_id": null,
      "duration_ms": 8,
      "query_count": 2,
      "response_kind": "json",
      "exception_class": "RuntimeException",
      "request_bytes": 38,
      "request_truncated": false,
      "response_bytes": 33,
      "response_truncated": false,
      "created_at": "2026-11-12T11:48:03+07:00"
    }
  ],
  "per_page": 50,
  "next_cursor": "eyJpZCI6IjAxbTc3MWI1dzd0NWJmajdtbTZ6M3d6ZnE5IiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ",
  "prev_cursor": null
}
```

Lists never return the whole table, and bodies are left out of list items.

## Record

The detail response adds:

- `url`, `route_name`, `ip`, `user_agent`, `hostname`, `revision`, `memory_peak_kb`, `query_time_ms` and `queries`;
- `request_headers`, `request_body`, `response_headers`, `response_body` and `context`, as stored text (JSON when parseable);
- `exception`: `{class, message, location, hash, snippet, trace}`, or `null`.

Users are exposed as `{id, label}` only.

## Suggestions

```json
{ "data": [ { "value": "App\\Http\\Controllers\\OrderController@store", "label": "OrderController@store" } ] }
```

`kind=action` searches your route list. `kind=user` calls your [`searchUsersUsing`](./hooks#user-labels-and-search) callback and returns nothing if no callback is set.
