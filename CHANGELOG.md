# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0-beta.1] - 2026-11-12

First public beta, consolidating several in-house HTTP mutation loggers and
adding techniques from Laravel Telescope.

### Added
- Global terminable middleware: cheap decision during the request, capture and
  storage after the response is sent; failures never affect the response.
- Recording profile: every POST/PUT/PATCH/DELETE and every 4xx/5xx (any
  method); optional slow-request threshold and sampling; per-route
  `request-log:always|off|metadata`; `filterUsing` override.
- `request_logs` table (configurable connection/table) with indexed columns for
  reference, status, user, tenant, action, subject, exception group and time.
- 10-character Crockford reference codes in the Laravel Context, the
  `X-Request-Reference` header and `RequestLog::referenceFor($status)`.
- Redaction of keys at any depth (case-insensitive, empty values included),
  JWT/Bearer/Basic value patterns, query strings, sensitive route parameters,
  response bodies and request/response headers.
- Structure-preserving truncation (`…[+N items]`, `…[+N chars]`) with original
  sizes, defaults 512 KB request / 256 KB response.
- Exception capture: class, message (QueryException without bound values),
  relative location, grouping hash, source snippet and trimmed trace for 5xx.
- Query statistics with the slowest queries and their calling file:line.
- JavaScript-free Blade viewer with strict CSP, keyset pagination and Markdown
  export; optional JSON API with cursor pagination and suggestions.
- `request-log:prune` (scheduled daily, longer retention for 5xx) and
  `request-log:audit` to find unredacted secrets in stored data.
- Telescope integration: entries tagged `ref:<reference>` and linked from the
  viewer.
