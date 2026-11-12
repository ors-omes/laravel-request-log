# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0-beta.2] - 2026-11-12

### Changed
- Bodies, headers and context that fit their byte limit are stored whole;
  `capture.max_items` and `capture.max_string` now only apply when content
  exceeds the limit (beta.1 cut arrays above 500 items even when they fit).
- When content must be shortened, the kept part is sized to fill the byte
  limit (e.g. ~1,900 of 2,600 BOM rows in 512 KB instead of 500).

### Fixed
- `created_at` was built in UTC and stored without an offset, so it read
  back shifted by the application's UTC offset. It is now built in the
  application time zone and written and compared with an explicit offset, so
  PostgreSQL `timestamptz` stores the right instant whatever the session
  time zone is.
- Keys such as `operator_pin` / `pin_code` are redacted (`*_pin`, `pin_*`).

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
