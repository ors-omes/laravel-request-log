---
description: Application callbacks for tenant, subject, context, the recording decision, user labels, user search and viewer scope.
---

# Application hooks

Register hooks in a service provider's `boot()` method. They are closures, so `config:cache` keeps working.

```php
use OrsOmes\RequestLog\RequestLog;
```

## Tenant

```php
RequestLog::resolveTenantUsing(fn (Request $request) => $request->user()?->organization_id);
```

The value is stored in the indexed `tenant_id` column (up to 64 characters).

## Subject

```php
RequestLog::resolveSubjectUsing(fn (Request $request) => 'Order:'.$request->route('order')?->getKey());
```

By default the subject is the last route-model-bound parameter, or the last id-like route parameter.

## Context

```php
RequestLog::contextUsing(fn (Request $request, Response $response) => [
    'feature' => 'checkout',
    'client_version' => $request->header('X-Client-Version'),
]);
```

The array is redacted like everything else and stored in `context`.

## Recording decision

```php
RequestLog::filterUsing(fn (Request $request, Response $response, bool $default): bool => $default);
```

See [Overriding the decision](./recording#overriding-the-decision).

## User labels and search

The viewer and API show user labels next to ids. By default the package reads the auth provider's model and uses `name`, then `username`, then the id. E-mail addresses are never used by default.

```php
RequestLog::resolveUserLabelsUsing(fn (array $ids) =>
    User::query()->whereKey($ids)->pluck('name', 'id')->all());

RequestLog::searchUsersUsing(fn (string $query, int $limit) =>
    User::query()->where('name', 'like', $query.'%')->limit($limit)->pluck('name', 'id')->all());
```

## Viewer scope

Restrict what the viewer and API can read, for example to the current tenant:

```php
RequestLog::scopeViewerUsing(function (LoggedRequestQuery $query, Request $request): void {
    $query->where('tenant_id', $request->user()->organization_id);
});
```

The scope applies to lists, detail pages, Markdown exports and the API. A record outside the scope returns 404.

## Exceptions the framework does not attach

```php
RequestLog::captureException($throwable);
```

Laravel attaches exceptions to rendered responses automatically. Call this only from custom rendering paths that bypass that.
