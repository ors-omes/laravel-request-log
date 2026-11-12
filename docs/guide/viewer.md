---
description: The server-rendered viewer — access control, authentication middleware, filters, detail pages and Markdown export.
---

# Viewer

The viewer lives at `/request-logs` (`viewer.path`). It is server-rendered, has no build step, and ships no JavaScript.

![The viewer list](/screenshots/list.png){.screenshot}

[Open the live demo](https://ors-omes.github.io/laravel-request-log/demo/index.html) to click through it with sample data.

## Access

Access is allowed when either:

- the app runs in the `local` environment and `viewer.allow_local` is `true`; or
- the current user passes the `viewRequestLogs` gate.

The package defines the gate to deny everyone, so you have to define it yourself:

```php
Gate::define('viewRequestLogs', fn (User $user) => $user->can('request-logs.view'));
```

::: warning Multi-tenant applications
Exception snippets contain source code. Grant the gate to platform operators, not to customer administrators, and add a [viewer scope](./hooks#viewer-scope).
:::

## Authentication

The viewer uses the `web` middleware group by default, so session authentication works out of the box. Applications that authenticate their APIs with tokens only can use another guard:

```php
// config/request-log.php
'viewer' => [
    'middleware' => ['web', 'auth.basic'],
    // ...
],
```

The gate check is always appended to whatever middleware you configure.

## Filters

| Filter | Example |
|---|---|
| Reference | `7KQ2M9XHTA`; pasted values such as `[7kq2-m9xh-ta]` also work |
| Method, reason | `POST`, `error` |
| Status | `500` or `5xx` |
| Action | Picked from your route list |
| Route | `api/orders/{order}` |
| Path prefix | `/api/orders` |
| User, tenant, subject | `42`, `tenant-7`, `Order:01J…` |
| Exception group | Click the hash on a detail page |
| Time | Last 15m / 1h / 6h / 24h / 7d, or from–to |

Pagination is keyset-based and the viewer never runs `COUNT(*)` or `DISTINCT` over the table, so it stays fast on large tables.

## Detail page

The detail page shows:

- the summary, exception, source snippet and trace;
- request and response bodies with their original size and a "shortened" marker;
- headers, context and slow queries;
- other occurrences of the same exception;
- a link to the request's Telescope batch, if Telescope is installed;
- a **Markdown** export for issues and chat.

![A validation error](/screenshots/validation-422.png){.screenshot}

## Hardening

- Everything is escaped. JSON is pretty-printed on the server.
- `Content-Security-Policy: default-src 'none'` with a per-request style nonce.
- `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer` and `Cache-Control: no-store`.
- GET-only routes; LIKE wildcards in filters are escaped.

## Customising

```bash
php artisan vendor:publish --tag=request-log-views
```

The views are published to `resources/views/vendor/request-log`.
