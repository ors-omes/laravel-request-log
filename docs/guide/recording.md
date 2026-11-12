---
description: The recording profile — which requests are recorded and why, ignored paths, per-route modes and the filterUsing override.
---

# What gets recorded

Every recorded row has a `reason`:

| Reason | When | Default |
|---|---|---|
| `error` | Any method, status ≥ `record.min_error_status` (400) | On |
| `mutation` | Method in `record.methods` (POST, PUT, PATCH, DELETE) | On |
| `slow` | Slower than `record.slow_ms` (`REQUEST_LOG_SLOW_MS`) | Off |
| `sampled` | A random share `record.sample_rate` of the remaining requests | Off |
| `forced` | The route uses `request-log:always`, or `filterUsing()` returned `true` | Per route |

`error` takes precedence over `mutation`, so a failed POST is recorded as `error`.

## Ignored paths, statuses and actions

```php
// config/request-log.php
'record' => [
    'ignore_paths' => ['up', 'telescope*', 'horizon*', 'pulse*', '_debugbar*', '_boost*', 'livewire*', 'broadcasting/auth', '.well-known*'],
    'only_paths' => [],
    'ignore_statuses' => [],          // e.g. [401] if expired tokens are noisy
    'ignore_actions' => [],           // e.g. ['*HeartbeatController*']
    'metadata_only_paths' => [],      // record without bodies
],
```

- Path patterns use `Str::is()` against the path without its leading slash.
- Action patterns match both the fully-qualified `App\Http\Controllers\OrderController@store` and the short `OrderController@store`.
- Ignored paths are not considered at all: no reference, no Context entry, no query counting.
- The viewer and API paths are always ignored.

::: tip Noise from 4xx
With the default profile every 4xx is recorded. That includes 401s from expired tokens and 404s from bots scanning for `/wp-admin`. If they are noisy, add the status to `ignore_statuses` or the path to `ignore_paths`.
:::

## Per route

```php
Route::get('reports/{report}', ShowReport::class)->middleware('request-log:always');   // also record GET 200
Route::post('heartbeat', Heartbeat::class)->middleware('request-log:off');             // never record
Route::post('login', Login::class)->middleware('request-log:metadata');                // record, without bodies
```

`request-log:metadata` stores everything except the request and response bodies, which are replaced by `[omitted: N bytes]`.

## Logging GET traffic

GET requests are recorded when they fail. To see more:

- set `REQUEST_LOG_SLOW_MS=1000` to record every request slower than one second;
- set `REQUEST_LOG_SAMPLE_RATE=0.01` to record 1% of the remaining traffic;
- add `request-log:always` to routes you want in full;
- add `GET` to `record.methods` to record every GET (rarely what you want).

## Overriding the decision

`filterUsing()` receives the request, the response and the default decision. Return whether to record:

```php
use OrsOmes\RequestLog\RequestLog;

// Also record legacy envelopes that report failure with HTTP 200.
RequestLog::filterUsing(fn (Request $request, Response $response, bool $default): bool =>
    $default || ($response instanceof JsonResponse && ($response->getData(true)['success'] ?? true) === false));
```

Requests recorded only because of the callback get the `forced` reason.
