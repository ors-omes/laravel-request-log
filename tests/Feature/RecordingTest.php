<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Recording\Reason;
use OrsOmes\RequestLog\RequestLog;
use Symfony\Component\HttpFoundation\Response;

it('records a mutation with request and response bodies', function (): void {
    $response = $this->postJson('/api/orders?expand=lines', ['note' => 'hello', 'qty' => 2]);

    $response->assertCreated();
    $log = latestRequestLog();

    expect($log->reason)->toBe(Reason::Mutation)
        ->and($log->method)->toBe('POST')
        ->and($log->path)->toBe('/api/orders')
        ->and($log->url)->toBe('/api/orders?expand=lines')
        ->and($log->status)->toBe(201)
        ->and($log->route_uri)->toBe('api/orders')
        ->and($log->action)->toBe('OrsOmes\RequestLog\Tests\Fixtures\OrderController@store')
        ->and(json_decode((string) $log->request_body, true))->toBe(['note' => 'hello', 'qty' => 2])
        ->and(json_decode((string) $log->response_body, true))->toBe(['data' => ['id' => 'ord_1', 'note' => 'hello']])
        ->and($log->response_kind)->toBe('json')
        ->and($log->revision)->toBe('abc123')
        ->and($log->reference)->toHaveLength(10);

    $response->assertHeader('X-Request-Reference', $log->reference);
});

it('does not record a successful GET by default', function (): void {
    $this->getJson('/api/orders')->assertOk()->assertHeaderMissing('X-Request-Reference');

    expect(LoggedRequest::query()->count())->toBe(0);
});

it('records GET requests that fail, with any status >= 400', function (): void {
    $this->getJson('/api/missing/42')->assertNotFound();
    $this->getJson('/api/does-not-exist')->assertNotFound();

    $logs = LoggedRequest::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->reason)->toBe(Reason::Error)
        ->and($logs[0]->subject)->toBe('id:42')
        ->and($logs[0]->exception_class)->toBe('Symfony\Component\HttpKernel\Exception\NotFoundHttpException')
        ->and($logs[0]->exception_snippet)->toBeNull()
        ->and($logs[1]->route_uri)->toBeNull();
});

it('records slow GET requests when a threshold is set', function (): void {
    Config::set('request-log.record.slow_ms', 10);

    $this->getJson('/api/slow')->assertOk();

    expect(latestRequestLog()->reason)->toBe(Reason::Slow)
        ->and(latestRequestLog()->duration_ms)->toBeGreaterThanOrEqual(10);
});

it('samples other requests', function (): void {
    Config::set('request-log.record.sample_rate', 1.0);

    $this->getJson('/api/orders')->assertOk();

    expect(latestRequestLog()->reason)->toBe(Reason::Sampled);
});

it('honours route modes', function (): void {
    $this->getJson('/api/always')->assertOk();
    $this->postJson('/api/off')->assertOk();
    $this->postJson('/api/metadata', ['password' => 'x', 'note' => 'kept out'])->assertOk();

    $logs = LoggedRequest::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->path)->toBe('/api/always')
        ->and($logs[0]->reason)->toBe(Reason::Forced)
        ->and($logs[1]->path)->toBe('/api/metadata')
        ->and($logs[1]->request_body)->toStartWith('[omitted:')
        ->and($logs[1]->response_body)->toStartWith('[omitted:');
});

it('applies metadata-only path patterns', function (): void {
    Config::set('request-log.record.metadata_only_paths', ['api/log*']);

    $this->postJson('/api/login', ['username' => 'an', 'password' => 'secret'])->assertOk();

    expect(latestRequestLog()->request_body)->toStartWith('[omitted:');
});

it('skips ignored paths, statuses and actions', function (): void {
    Config::set('request-log.record.ignore_paths', ['api/orders*']);
    $this->postJson('/api/orders')->assertCreated();

    Config::set('request-log.record.ignore_paths', []);
    Config::set('request-log.record.ignore_statuses', [404]);
    $this->getJson('/api/missing/1')->assertNotFound();

    Config::set('request-log.record.ignore_actions', ['LoginAction']);
    $this->postJson('/api/login')->assertOk();

    expect(LoggedRequest::query()->count())->toBe(0);
});

it('lets the application override the decision', function (): void {
    RequestLog::filterUsing(static fn (Request $request, Response $response, bool $default): bool => $request->is('api/orders') && $request->isMethod('GET'));

    $this->getJson('/api/orders')->assertOk();
    $this->postJson('/api/login')->assertOk();

    expect(LoggedRequest::query()->count())->toBe(1)
        ->and(latestRequestLog()->reason)->toBe(Reason::Forced);
});

it('does nothing when disabled', function (): void {
    Config::set('request-log.enabled', false);

    $this->postJson('/api/orders')->assertCreated()->assertHeaderMissing('X-Request-Reference');

    expect(LoggedRequest::query()->count())->toBe(0)
        ->and(RequestLog::reference())->toBeNull();
});

it('stores the route model binding as subject and resolves the user only when already authenticated', function (): void {
    $this->patchJson('/api/orders/01JCDV7Z9R2Q4P3M5N6B7C8D9E', ['note' => 'x'])->assertOk();

    expect(latestRequestLog()->subject)->toBe('Order:01JCDV7Z9R2Q4P3M5N6B7C8D9E')
        ->and(latestRequestLog()->user_id)->toBeNull();
});

it('stores tenant, subject and context from application callbacks', function (): void {
    RequestLog::resolveTenantUsing(static fn (): string => 'tenant-7');
    RequestLog::resolveSubjectUsing(static fn (): string => 'Custom:1');
    RequestLog::contextUsing(static fn (): array => ['feature' => 'checkout', 'api_key' => 'k-123']);

    $this->postJson('/api/orders')->assertCreated();

    expect(latestRequestLog()->tenant_id)->toBe('tenant-7')
        ->and(latestRequestLog()->subject)->toBe('Custom:1')
        ->and(json_decode((string) latestRequestLog()->context, true))->toBe(['feature' => 'checkout', 'api_key' => '[REDACTED]']);
});

it('counts queries of the request', function (): void {
    $this->postJson('/api/queries')->assertOk();

    expect(latestRequestLog()->query_count)->toBe(2)
        ->and(latestRequestLog()->queries)->toBeNull();

    Config::set('request-log.queries.slow_ms', 0);
    $this->postJson('/api/queries')->assertOk();

    expect(latestRequestLog()->queries)->toHaveCount(2)
        ->and(latestRequestLog()->queries[0]['sql'])->toStartWith('select');
});

it('never breaks the response when storing fails', function (): void {
    Config::set('request-log.storage.table', 'missing_request_logs');

    $this->postJson('/api/orders')->assertCreated();
});

it('stores the start time in the application time zone', function (): void {
    $previous = date_default_timezone_get();
    date_default_timezone_set('Asia/Ho_Chi_Minh');

    try {
        $before = Carbon\CarbonImmutable::now()->subSecond();
        $this->postJson('/api/orders')->assertCreated();

        expect(latestRequestLog()->created_at->greaterThanOrEqualTo($before))->toBeTrue()
            ->and(latestRequestLog()->created_at->lessThanOrEqualTo(Carbon\CarbonImmutable::now()->addSecond()))->toBeTrue()
            ->and(LoggedRequest::query()->filter(['range' => '15m'])->count())->toBe(1)
            ->and(LoggedRequest::query()->createdBefore($before)->count())->toBe(0);
    } finally {
        date_default_timezone_set($previous);
    }
});
