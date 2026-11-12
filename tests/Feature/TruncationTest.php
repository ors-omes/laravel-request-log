<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('shortens long arrays and strings but keeps valid json with markers', function (): void {
    Config::set('request-log.capture.max_items', 3);
    Config::set('request-log.capture.max_string', 20);

    $this->postJson('/api/echo', [
        'lines' => range(1, 10),
        'description' => str_repeat('a', 50),
        'meta' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
    ])->assertOk();

    $log = latestRequestLog();
    $body = json_decode((string) $log->request_body, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($body['lines'])->toBe([1, 2, 3, '…[+7 items]'])
        ->and($body['description'])->toBe(str_repeat('a', 20).'…[+30 chars]')
        ->and($body['meta'])->toBe(['a' => 1, 'b' => 2, 'c' => 3, '…' => '…[+1 keys]'])
        ->and($log->request_truncated)->toBeTrue()
        ->and($log->response_truncated)->toBeTrue();
});

it('shrinks until the body fits the byte limit and records the original size', function (): void {
    Config::set('request-log.capture.request_body_bytes', 2048);

    $payload = ['rows' => array_map(static fn (int $i): array => ['id' => $i, 'name' => str_repeat('x', 40)], range(1, 400))];
    $this->postJson('/api/orders', $payload)->assertCreated();

    $log = latestRequestLog();

    expect(strlen((string) $log->request_body))->toBeLessThanOrEqual(2048)
        ->and(json_decode((string) $log->request_body, true))->toBeArray()
        ->and($log->request_body)->toContain('…[+')
        ->and($log->request_bytes)->toBe(strlen((string) json_encode($payload)))
        ->and($log->request_truncated)->toBeTrue();
});

it('falls back to a utf-8 safe cut with a size marker', function (): void {
    Config::set('request-log.capture.request_body_bytes', 300);
    Config::set('request-log.capture.max_string', 100000);

    $this->postJson('/api/orders', ['text' => str_repeat('Tiếng Việt có dấu ', 200)])->assertCreated();

    $body = (string) latestRequestLog()->request_body;

    expect(strlen($body))->toBeLessThanOrEqual(300)
        ->and(mb_check_encoding($body, 'UTF-8'))->toBeTrue()
        ->and($body)->toMatch('/…\[truncated: kept \d+ of \d+ bytes\]$/');
});

it('does not parse bodies above the parse limit', function (): void {
    Config::set('request-log.capture.max_parse_bytes', 100);

    $payload = ['text' => str_repeat('a', 200)];
    $this->postJson('/api/orders', $payload)->assertCreated();

    expect(latestRequestLog()->request_body)->toBe('[body too large to parse: '.strlen((string) json_encode($payload)).' bytes]')
        ->and(latestRequestLog()->request_truncated)->toBeTrue();
});

it('marks invalid json bodies', function (): void {
    $this->call('POST', '/api/orders', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"broken": ')->assertCreated();

    expect(latestRequestLog()->request_body)->toBe('[invalid json: 11 bytes]');
});
