---
description: Security model — redaction rules, what is never stored, exception scrubbing, access control, viewer hardening, isolation and operator recommendations.
---

# Security and privacy

A request log is a copy of your traffic. The defaults are chosen so that turning it on does not create a second copy of your users' secrets.

## Redaction

**Keys.** Keys are matched case-insensitively (`-` reads as `_`) at any depth. This applies to request bodies, query strings, route parameters in the path, response bodies, headers and context. Matching values are replaced with `[REDACTED]`, **including empty values**. The default patterns:

```text
*password*  *passwd*  *secret*  *token*  *api_key*  *apikey*  *private_key*
signature  authorization  proxy_authorization  cookie  set_cookie
otp  *_otp  otp_*  pin  *_pin  pin_*  cvv  cvc  *card_number*  credit_card  ssn
```

Add your own with `redact.extra_keys`:

```php
'redact' => [
    'extra_keys' => ['national_id', '*_iban'],
],
```

**Values.** JWTs, `Bearer …` and `Basic …` are removed from every stored string, even under harmless keys. A token pasted into a `note` field is caught. Add regular expressions with `redact.value_patterns`.

**Headers.** Request and response headers have separate lists:

| Request headers | Response headers |
|---|---|
| `authorization`, `proxy-authorization`, `cookie`, `x-api-key`, `x-auth-token`, `x-csrf-token`, `x-xsrf-token`, `php-auth-pw` | `set-cookie`, `authorization` |

Header names that match a key pattern (for example `x-access-token`) are redacted too.

**URL headers.** Some headers carry a URL whose parameters the package cannot know: the redirect target, the previous page, the original URI behind a proxy. An OAuth `code`, a reset token in the path or a signed URL would otherwise be stored as sent. These headers keep their path and parameter names, but every query and fragment value is replaced, and so is any path segment that looks like a token (a run of 16 or more mixed letters and digits that is not a ULID):

```text
Referer:  https://app.test/reset-password/[REDACTED]?email=[REDACTED]
Location: https://client.test/callback?code=[REDACTED]&state=[REDACTED]
```

The default list is `location`, `content-location`, `refresh`, `referer`, `x-forwarded-uri`, `x-original-uri`, `x-original-url` and `x-rewrite-url`. The redirect target stored as the response body follows the `location` entry. To keep a header as sent, remove it from the list:

```php
'redact' => [
    'url_headers' => ['location', 'refresh'], // Referer and proxy headers stored as sent
],
```

**Paths and URLs.** Values of sensitive route parameters are replaced in `path` and `url`: parameters named like a secret (`/reset-password/{token}`), and parameters looked up by a secret column (`/invitations/{invitation:token}`, or a model whose route key is `token`). The same parameters are never used as `subject`. Signed URL `signature` parameters are redacted as well.

![Password reset with its path token redacted](/screenshots/reset-password.png){.screenshot}

## Never stored

- **Bodies that cannot be redacted by key** (XML, CSV, plain text, HTML): only the type and size are stored, unless you enable `capture.raw_text_bodies`.
- **Uploaded file contents**: only the name, size and MIME type are stored.
- **Streamed and file downloads**: they are never read.
- **Bodies on `request-log:metadata` routes**, including the redirect target, which is stored as `[omitted: redirect]`.

## Exceptions

- Database errors (`QueryException`, `PDOException`) keep the driver message and the SQL with its `?` placeholders, but not the values. Drivers quote the offending value in the message itself (`invalid input syntax for type integer: "…"`, `Incorrect integer value: '…'`), so quoted text after `:`, `=`, `value` or `entry` is replaced, SQL Server's `duplicate key value is (…)` too, and PostgreSQL `DETAIL:` and `CONTEXT:` lines are dropped. Identifiers (`relation "orders"`, `for column 'qty'`) stay. To store Laravel's full message with bound values, set `exceptions.query_bindings` to `true`.
- SQL written with literal values instead of bindings is stored as written.
- Paths are stored relative to the project. Trace frames have no arguments.
- Source snippets and traces are stored only when the status is ≥ `exceptions.debug_min_status` (500).

## Access control

- The viewer is allowed in `local` (if `viewer.allow_local` is true) or through the `viewRequestLogs` gate. Outside `local`, the package's own gate denies everyone until you define it.
- The JSON API is disabled by default. When enabled, it always enforces the gate on top of your authentication middleware, and it does not allow `local` by default.
- `RequestLog::scopeViewerUsing()` limits every read (list, detail, Markdown, API) to what the current user may see.
- Users are exposed as `{id, label}` only. The default label never uses the e-mail address.

## Viewer hardening

- No JavaScript, and all output is escaped.
- A Content-Security-Policy with `default-src 'none'` and a per-request style nonce, plus `frame-ancestors 'none'`.
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer` and `Cache-Control: no-store`.
- GET-only routes.
- LIKE wildcards in filters are escaped. There are no `COUNT(*)` or `DISTINCT` scans that a crafted filter could abuse.

## Isolation

- The recorder only decides during the request. Capturing and storing happen after the response is sent.
- Size checks run before decoding; depth and item limits apply; streams are never read.
- A storage failure is logged as a warning without payload data (`SQLSTATE[…]` only for database errors), and the response is unaffected.
- The package does not record its own viewer and API requests, or the queries it runs to store a record.

## Recommendations for operators

1. Store logs on a dedicated connection with its own credentials and backups (`REQUEST_LOG_DB_CONNECTION`).
2. Grant `viewRequestLogs` to platform operators only.
3. Keep retention short (the defaults are 30 days, or 90 for 5xx).
4. Run `php artisan request-log:audit --since=24h` after enabling the package, after upgrading it and after changing routes or payload shapes. Besides secret-looking values, it reports URL headers, redirect targets and database messages that the current redaction would still change (records written before a fix, or with a narrower configuration).
5. Use `request-log:metadata` on routes whose bodies you never want to keep.

## Reporting a vulnerability

Please report security issues privately through [GitHub's vulnerability reporting](https://github.com/ors-omes/laravel-request-log/security/advisories/new). See the [security policy](https://github.com/ors-omes/laravel-request-log/security/policy).
