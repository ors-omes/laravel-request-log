---
description: Artisan commands shipped by the package — request-log:prune and request-log:audit.
---

# Artisan commands

## request-log:prune

```text
php artisan request-log:prune [--days=] [--error-days=]
```

Deletes records past their retention period. It is scheduled daily at 03:15 unless `retention.schedule` is `false`. See [Retention](/guide/retention-and-audit#pruning).

## request-log:audit

```text
php artisan request-log:audit [--since=24h] [--limit=200]
```

Scans stored records for values that look like unredacted secrets, prints masked excerpts and exits with `1` when anything is found. `--since` accepts `30m`, `6h`, `7d`. See [Auditing stored data](/guide/retention-and-audit#auditing-stored-data).
