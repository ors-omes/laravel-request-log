<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Recording\Reason;

it('stores the rendered body and the exception with snippet and trimmed trace for 5xx', function (): void {
    Config::set('request-log.exceptions.project_paths', ['tests']);

    $this->getJson('/api/orders/fail')->assertStatus(500);

    $log = latestRequestLog();
    $trace = $log->exception_trace;

    expect($log->reason)->toBe(Reason::Error)
        ->and($log->exception_class)->toBe(RuntimeException::class)
        ->and($log->exception_message)->toBe('Order service exploded')
        ->and($log->exception_hash)->toBe(md5(RuntimeException::class.'|'.$log->exception_location))
        ->and($log->exception_snippet)->toContain("throw new RuntimeException('Order service exploded');")
        ->and($log->exception_snippet)->toMatch('/^>\s+\d+\| .*throw new RuntimeException/m')
        ->and($log->response_kind)->toBe('json')
        ->and($trace)->toBeArray()
        ->and(end($trace)['function'])->toMatch('/framework frames omitted/');

    foreach ($trace as $frame) {
        expect(array_keys($frame))->toBe(['file', 'line', 'class', 'function', 'type']);
    }
});

it('keeps validation errors but no debug data for 4xx', function (): void {
    $this->postJson('/api/validate', ['password' => 'short'])->assertStatus(422);

    $log = latestRequestLog();
    $response = json_decode((string) $log->response_body, true);

    expect($log->exception_class)->toBe('Illuminate\Validation\ValidationException')
        ->and($log->exception_snippet)->toBeNull()
        ->and($log->exception_trace)->toBeNull()
        ->and($response['errors'])->toHaveKeys(['name', 'password'])
        ->and(json_decode((string) $log->request_body, true))->toBe(['password' => '[REDACTED]']);
});

it('stores query exceptions without bound values', function (): void {
    $this->postJson('/api/query-error')->assertStatus(500);

    $log = latestRequestLog();

    expect($log->exception_class)->toBe('Illuminate\Database\QueryException')
        ->and($log->exception_message)->toContain('select * from missing_table where email = ?')
        ->and($log->exception_message)->not->toContain('person@example.com');
});

it('groups the same failure under one hash', function (): void {
    $this->getJson('/api/orders/fail')->assertStatus(500);
    $this->getJson('/api/orders/fail')->assertStatus(500);

    expect(LoggedRequest::query()->distinct()->pluck('exception_hash'))->toHaveCount(1);
});
