---
description: How the package works alongside Laravel Telescope — reference tags, linking to batches and keeping whole batches of failed requests.
---

# Telescope

The two tools serve different purposes:

- **Request Log** is the always-on audit trail, with indexed columns, kept for weeks.
- **[Laravel Telescope](https://laravel.com/docs/telescope)** is for deep debugging. It records everything about a request, usually only in development or for a few hours.

## Reference tags

When `laravel/telescope` is installed and `telescope.enabled` is `true`, every Telescope entry recorded during a considered request is tagged `ref:<reference>`. The viewer's detail page then shows **Open in Telescope**. It links to `/telescope/requests?tag=ref:…`, where the request's whole batch is listed: queries with bindings, logs, cache, jobs and outgoing HTTP calls.

## Keep whole batches of failed requests

Telescope's default filter keeps only the entries that are themselves interesting (the failed request, the exception). It drops the queries of that same request. To keep the entire batch whenever something in it failed:

```php
// app/Providers/TelescopeServiceProvider.php
Telescope::filterBatch(fn (Collection $entries) => $entries->contains(
    fn (IncomingEntry $entry) => $entry->isReportableException()
        || $entry->isFailedRequest()
        || $entry->isFailedJob()
));
```

## What the package borrows from Telescope

- Buffering during the request and writing after the response is sent.
- Reading the user only when authentication already resolved it.
- Exception grouping by location, with a line-numbered source snippet.
- Query statistics, with the calling application frame for slow queries.
- Classifying responses (JSON, redirect, HTML, stream, file) and describing uploads by metadata.
- Keyset pagination, and pruning in chunks that keeps errors longer.

## What it does differently

- Every field is a column you can filter and index. Telescope keeps one JSON blob per entry.
- Oversized bodies are shortened with markers instead of being replaced by "Purged By Telescope".
- Redaction matches keys at any depth, case-insensitively. It also covers values (JWT, Bearer), query strings, route parameters and response bodies. Telescope only masks listed dot-paths.
- It is designed to run in production.
