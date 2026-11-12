---
description: Pruning old records with request-log:prune and scanning stored data for unredacted secrets with request-log:audit.
---

# Retention and audit

## Pruning

```bash
php artisan request-log:prune
php artisan request-log:prune --days=7 --error-days=30
```

| Option | Default | Deletes |
|---|---|---|
| `--days` | `retention.days` (30) | Records older than this, status < 500 |
| `--error-days` | `retention.error_days` (90) | All records older than this, 5xx included |

Records are deleted in primary-key chunks (`retention.chunk`), which behaves the same on every database.

The package schedules `request-log:prune` daily at 03:15. Set `retention.schedule` to `false` to schedule it yourself, and make sure your scheduler runs (`php artisan schedule:work` or a cron entry for `schedule:run`).

## Auditing stored data

Redaction works on keys and known value formats. Your application may still send secrets under names nobody thought of. During the beta, and after any change to routes or payloads, scan what was actually stored:

```bash
php artisan request-log:audit --since=24h
```

It scans `url`, headers, bodies, `exception_message` and `context` for:

| Rule | Finds |
|---|---|
| `jwt` | JSON Web Tokens |
| `bearer`, `basic-auth` | Credentials in `Authorization`-style values |
| `secret-key` | JSON keys that look secret (`*passw*`, `*secret*`, `*token*`, `*api_key*`, …) but still have a value |
| `secret-query` | Such parameters in query strings |
| `private-key` | `-----BEGIN … PRIVATE KEY-----` |
| `card-number` | 13–19 digit numbers that pass the Luhn check |

It prints masked excerpts (never the full value) and exits with status `1` when it finds anything, so you can run it in a scheduled job or CI. To fix a finding, add the key to `redact.extra_keys` (or a pattern to `redact.value_patterns`), then prune the affected rows.

```text
+----------------------------+------------+--------------+-------------+----------------------------+
| id                         | reference  | field        | rule        | excerpt                    |
+----------------------------+------------+--------------+-------------+----------------------------+
| 01m771b5w7t5bfj7mm6z3wzfq9 | TASHGJ465T | request_body | card-number | 4111 1111 …(19 chars)      |
+----------------------------+------------+--------------+-------------+----------------------------+
1 finding(s) in 214 record(s). Add the keys to request-log.redact.extra_keys or a value pattern, then prune affected rows.
```
