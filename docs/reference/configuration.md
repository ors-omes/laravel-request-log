---
description: Environment variables and the complete, commented config/request-log.php file.
---

# Configuration

## Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `REQUEST_LOG_ENABLED` | `true` | Master switch |
| `REQUEST_LOG_DB_CONNECTION` | default connection | Storage connection |
| `REQUEST_LOG_TABLE` | `request_logs` | Table name |
| `REQUEST_LOG_HEADER` | `X-Request-Reference` | Response header (empty disables it) |
| `REQUEST_LOG_SLOW_MS` | off | Also record requests slower than this |
| `REQUEST_LOG_SAMPLE_RATE` | `0` | Also record this fraction of other requests |
| `REQUEST_LOG_REQUEST_BODY_BYTES` | `524288` | Request body limit |
| `REQUEST_LOG_RESPONSE_BODY_BYTES` | `262144` | Response body limit |
| `REQUEST_LOG_MAX_PARSE_BYTES` | `4194304` | Bodies larger than this are not decoded |
| `REQUEST_LOG_IP` | `full` | `full`, `masked` (last IPv4 octet / IPv6 beyond /48) or `none` |
| `REQUEST_LOG_REVISION` / `APP_GIT_SHA_1` | read from `.git` | Deployed revision |
| `REQUEST_LOG_RETENTION_DAYS` | `30` | Retention |
| `REQUEST_LOG_ERROR_RETENTION_DAYS` | `90` | Retention for status ≥ 500 |
| `REQUEST_LOG_VIEWER_ENABLED` | `true` | Viewer routes |
| `REQUEST_LOG_VIEWER_PATH` | `request-logs` | Viewer path |
| `REQUEST_LOG_VIEWER_DOMAIN` | – | Viewer domain |
| `REQUEST_LOG_API_ENABLED` | `false` | JSON API routes |
| `REQUEST_LOG_API_PREFIX` | `api/request-logs` | JSON API prefix |

## config/request-log.php

Publish it with `php artisan vendor:publish --tag=request-log-config`. This is the file as shipped:

<<< ../../config/request-log.php
