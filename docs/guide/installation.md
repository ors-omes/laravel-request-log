---
description: Install the package from Git with Composer, run the migration, use a dedicated database connection and verify the first records.
---

# Installation

## Install the package

Until the package is published on Packagist, install it from Git. Add the repository to your application's `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ors-omes/laravel-request-log" }
  ]
}
```

Then require the beta and run the migration:

```bash
composer require ors-omes/laravel-request-log:^0.1@beta
php artisan migrate
```

The service provider is auto-discovered. It prepends the recorder to the global HTTP middleware stack, so no route changes are needed. To place the middleware yourself instead, set `middleware.auto_register` to `false` and add `OrsOmes\RequestLog\Http\Middleware\RecordRequest` to your global middleware.

## Use a dedicated connection

Request logs grow quickly. Keep them out of your application database and its backups:

```dotenv
REQUEST_LOG_DB_CONNECTION=pgsql_log
```

Define the connection in `config/database.php`. The migration then creates the table on that connection.

::: tip PostgreSQL
`created_at` is a `timestamptz` column. Times are written and compared with an explicit UTC offset, so the stored instant does not depend on the database session time zone.
:::

## Publish the configuration (optional)

```bash
php artisan vendor:publish --tag=request-log-config
```

Every option is documented in the [configuration reference](/reference/configuration).

## Grant access to the viewer

Outside the `local` environment the viewer is closed until you define the gate:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewRequestLogs', fn (User $user) => $user->can('request-logs.view'));
```

See [Viewer](./viewer) for authentication options.

## Replacing an in-house logger

If your application already has its own "HTTP mutation log" middleware:

1. Remove the old middleware from your routes or middleware groups. This package replaces it; it does not extend it.
2. Point your exception renderer's `reference_code` at [`RequestLog::referenceFor($status)`](./reference-codes#in-error-responses).
3. Keep the old table read-only for as long as you need it. The package does not migrate old rows.

## Verify

Send a few requests: a POST, a GET that returns 404, and a login. Then:

```bash
php artisan request-log:audit --since=1h
```

Open `/request-logs` in the `local` environment, or as a user who passes the gate.
