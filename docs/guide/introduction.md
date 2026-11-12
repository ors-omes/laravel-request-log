---
description: What Laravel Request Log records, why it exists, how it works and how it differs from Laravel Telescope.
---

# Introduction

Laravel Request Log is a structured, redacted and queryable HTTP request log for Laravel 12 and 13. By default it records:

- **every POST, PUT, PATCH and DELETE**;
- **every request answered with 4xx or 5xx, including GET**.

Each recorded request becomes one row with indexed columns. Payloads and responses are redacted and, when too large, shortened rather than dropped. The row also carries the exception (with a source snippet and a grouping hash), query statistics, and who did what to which record.

::: warning Beta
The package is in beta (`0.x`). The table layout and configuration may still change before `1.0`. Run [`request-log:audit`](./retention-and-audit#auditing-stored-data) on real traffic before you trust a configuration.
:::

## Why

The package replaces hand-copied "HTTP mutation log" middlewares that had drifted apart across several applications. It keeps what worked:

- indexed columns;
- reference codes;
- exception snippets.

It fixes what didn't:

- payloads were replaced with the word `truncated`;
- successful responses were not stored;
- 7-character codes collided;
- slow `COUNT`/`DISTINCT` scans in the viewer;
- no retention.

It also borrows the useful parts of [Laravel Telescope](https://laravel.com/docs/telescope): recording after the response is sent, cheap user resolution, exception grouping, query statistics, keyset pagination, and pruning that keeps errors longer.

## How it works

One global middleware is prepended automatically, so there is nothing to add to your routes.

1. **Request arrives.** A reference code is assigned and put in the Laravel Context. Query counting starts.
2. **Your application runs.** Controllers, validation and exception rendering run as usual. Your error responses can include `RequestLog::referenceFor($status)`.
3. **Cheap decision.** Is it a mutation, an error, slow or sampled? Recorded responses get the `X-Request-Reference` header.
4. **Response sent.** The client already has its answer; under PHP-FPM, `fastcgi_finish_request()` has run.
5. **Capture and store.** Bodies are redacted and shortened, the exception and slow queries are captured, and one row is inserted. Any failure is swallowed and logged as a warning without payload data.

## Compared with Telescope

| | Request Log | Telescope |
|---|---|---|
| Purpose | Always-on audit trail | Deep debugging |
| Storage | Indexed columns | One JSON blob per entry |
| Query by status, user, action, time | Yes | Tags only |
| Oversized response | Shortened, original size kept | "Purged By Telescope" |
| Redaction | Any depth, values, headers, URLs | Listed dot-paths |
| Production | Designed for it | Usually development only |

They work together: Telescope entries are tagged `ref:<reference>`, and the viewer links to the Telescope batch of a request. See [Telescope](./telescope).

## Requirements

- PHP 8.2 – 8.5
- Laravel 12 or 13
- SQLite, PostgreSQL or MySQL/MariaDB

There are no dependencies on Passport, Sanctum, Telescope or any action library.
