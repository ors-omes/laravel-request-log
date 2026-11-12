<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('stores content that fits the byte limit whole, whatever the item and string limits', function (): void {
    Config::set('request-log.capture.max_items', 3);
    Config::set('request-log.capture.max_string', 20);

    $payload = ['lines' => range(1, 10), 'description' => str_repeat('a', 50)];
    $this->postJson('/api/orders', $payload)->assertCreated();

    expect(json_decode((string) latestRequestLog()->request_body, true))->toBe($payload)
        ->and(latestRequestLog()->request_truncated)->toBeFalse();
});

it('shortens long arrays and strings over the byte limit but keeps valid json with markers', function (): void {
    Config::set('request-log.capture.max_items', 3);
    Config::set('request-log.capture.max_string', 20);
    Config::set('request-log.capture.request_body_bytes', 200);
    Config::set('request-log.capture.response_body_bytes', 200);

    $this->postJson('/api/echo', [
        'lines' => range(1, 100),
        'description' => str_repeat('a', 300),
        'meta' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
    ])->assertOk();

    $log = latestRequestLog();
    $body = json_decode((string) $log->request_body, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and(strlen((string) $log->request_body))->toBeLessThanOrEqual(200)
        ->and(end($body['lines']))->toMatch('/^…\[\+\d+ items\]$/')
        ->and(array_slice($body['lines'], 0, 3))->toBe([1, 2, 3])
        ->and($body['description'])->toMatch('/^a{20,}…\[\+\d+ chars\]$/')
        ->and($log->request_truncated)->toBeTrue()
        ->and($log->response_truncated)->toBeTrue();
});

it('keeps as many items as fit the byte limit', function (): void {
    Config::set('request-log.capture.request_body_bytes', 100000);

    $payload = ['rows' => array_map(static fn (int $i): array => ['id' => $i, 'name' => str_repeat('x', 40)], range(1, 4000))];
    $this->postJson('/api/orders', $payload)->assertCreated();

    $body = (string) latestRequestLog()->request_body;
    $rows = json_decode($body, true)['rows'];

    expect(strlen($body))->toBeLessThanOrEqual(100000)
        ->and(strlen($body))->toBeGreaterThan(60000)
        ->and(count($rows))->toBeGreaterThan(1000)
        ->and(end($rows))->toMatch('/^…\[\+\d+ items\]$/');
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

it('falls back to a utf-8 safe cut with a size marker when the structure cannot shrink', function (): void {
    Config::set('request-log.capture.request_body_bytes', 300);

    $this->postJson('/api/orders', [str_repeat('Grüße aus Köln, naïve café — €50 ', 200) => 1])->assertCreated();

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
