---
description: The request_logs table — columns, types and indexes, created by the package migration.
---

# Database schema

The package creates one table, `request_logs` by default. It works on SQLite, PostgreSQL and MySQL/MariaDB, and runs on `REQUEST_LOG_DB_CONNECTION` when set.

## Indexes

| Index | Used by |
|---|---|
| `id` (ULID, primary) | Keyset pagination, newest first |
| `created_at` | Time ranges, pruning |
| `reference` | Reference lookup |
| `(status, created_at)` | Status and status-class filters |
| `(user_id, created_at)` | User filter |
| `(action, created_at)` | Action filter |
| `(subject, created_at)` | Subject filter ("everything done to Order:01J…") |
| `(exception_hash, created_at)` | Exception groups |
| `(tenant_id, created_at)` | Tenant scope |

## Migration

<<< ../../database/migrations/2026_11_12_000000_create_request_logs_table.php
