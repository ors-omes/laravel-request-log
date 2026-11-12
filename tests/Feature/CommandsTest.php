<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Recording\Reason;

function storeLog(array $attributes): LoggedRequest
{
    return LoggedRequest::query()->create([
        'reference' => 'TEST000000',
        'reason' => Reason::Mutation,
        'method' => 'POST',
        'url' => '/api/x',
        'path' => '/api/x',
        'status' => 200,
        'duration_ms' => 1,
        'memory_peak_kb' => 1,
        'query_count' => 0,
        'query_time_ms' => 0,
        'request_bytes' => 0,
        'request_truncated' => false,
        'response_kind' => 'json',
        'response_bytes' => 0,
        'response_truncated' => false,
        'created_at' => CarbonImmutable::now(),
        ...$attributes,
    ]);
}

it('prunes old records and keeps server errors longer', function (): void {
    Config::set('request-log.retention.chunk', 1);
    $old = CarbonImmutable::now()->subDays(40);
    $ancient = CarbonImmutable::now()->subDays(100);

    storeLog(['created_at' => $old, 'status' => 201]);
    storeLog(['created_at' => $old, 'status' => 422]);
    $keptError = storeLog(['created_at' => $old, 'status' => 500]);
    storeLog(['created_at' => $ancient, 'status' => 500]);
    $recent = storeLog(['status' => 201]);

    $this->artisan('request-log:prune')->expectsOutputToContain('Deleted 3')->assertSuccessful();

    expect(LoggedRequest::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$keptError->id, $recent->id])->sort()->values()->all());

    $this->travel(2)->seconds();
    $this->artisan('request-log:prune', ['--days' => 0, '--error-days' => 0])->assertSuccessful();
    expect(LoggedRequest::query()->count())->toBe(0);

    $this->artisan('request-log:prune', ['--days' => -1])->assertFailed();
});

it('audits stored records for values that look like secrets', function (): void {
    storeLog(['request_body' => '{"password":"[REDACTED]","name":"An"}']);

    $this->artisan('request-log:audit')->expectsOutputToContain('No suspicious values in 1 record(s).')->assertSuccessful();

    storeLog([
        'url' => '/api/x?session_token=abc123',
        'request_body' => '{"profile":{"newPassword":"hunter2"},"card":"4111 1111 1111 1111"}',
        'response_body' => '{"note":"eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl"}',
    ]);

    $this->artisan('request-log:audit', ['--since' => '1h'])
        ->expectsOutputToContain('secret-key')
        ->expectsOutputToContain('secret-query')
        ->expectsOutputToContain('card-number')
        ->expectsOutputToContain('jwt')
        ->doesntExpectOutputToContain('hunter2')
        ->assertFailed();

    $this->artisan('request-log:audit', ['--since' => 'yesterday'])->assertFailed();
});

it('runs the migration and reports the table on the configured connection', function (): void {
    expect(Illuminate\Support\Facades\Schema::hasTable('request_logs'))->toBeTrue()
        ->and(Illuminate\Support\Facades\Schema::hasColumns('request_logs', ['reference', 'exception_hash', 'tenant_id', 'request_truncated']))->toBeTrue();
});
