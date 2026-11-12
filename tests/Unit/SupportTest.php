<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use OrsOmes\RequestLog\Capture\RequestCapture;
use OrsOmes\RequestLog\Integrations\TelescopeBridge;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Models\LoggedRequestQuery;
use OrsOmes\RequestLog\Recording\Reason;
use OrsOmes\RequestLog\RequestLogServiceProvider;
use OrsOmes\RequestLog\Support\Markdown;
use OrsOmes\RequestLog\Support\Redactor;
use OrsOmes\RequestLog\Support\Truncator;

it('grows markdown fences beyond any backtick run in the content', function (): void {
    $entry = new LoggedRequest([
        'reference' => 'ABCDEFGHJK',
        'reason' => Reason::Error,
        'method' => 'POST',
        'url' => '/api/x',
        'path' => '/api/x',
        'status' => 500,
        'duration_ms' => 5,
        'memory_peak_kb' => 1,
        'query_count' => 0,
        'query_time_ms' => 0,
        'request_body' => '{"note":"````` break out"}',
        'request_bytes' => 25,
        'request_truncated' => false,
        'response_kind' => 'json',
        'response_bytes' => 0,
        'response_truncated' => false,
        'created_at' => CarbonImmutable::parse('2026-11-12T10:00:00+07:00'),
    ]);

    $markdown = Markdown::render($entry, null);

    expect($markdown)->toContain("``````json\n")
        ->and($markdown)->toContain('- User: _null_')
        ->and($markdown)->toContain('# Request log ABCDEFGHJK');
});

it('cuts text on a utf-8 boundary and reports sizes', function (): void {
    $cut = (new Truncator())->text(str_repeat('€', 100), 60);

    expect(strlen($cut))->toBeLessThanOrEqual(60)
        ->and(mb_check_encoding($cut, 'UTF-8'))->toBeTrue()
        ->and($cut)->toContain('of 300 bytes]');
});

it('masks ipv4 and ipv6 addresses', function (): void {
    expect(RequestCapture::maskIp('192.168.10.77'))->toBe('192.168.10.0')
        ->and(RequestCapture::maskIp('2001:db8:85a3:8d3:1319:8a2e:370:7348'))->toBe('2001:db8:85a3::')
        ->and(RequestCapture::maskIp('not-an-ip'))->toBe('not-an-ip');
});

it('escapes like wildcards portably', function (): void {
    expect(LoggedRequestQuery::escapeLike('50%_off!'))->toBe('50!%!_off!!');
});

it('registers the telescope bridge only when telescope is installed', function (): void {
    TelescopeBridge::register();

    expect(class_exists('Laravel\Telescope\Telescope'))->toBeFalse();
});

it('redacts urls whose parameters it cannot know', function (string $url, string $expected): void {
    expect((new Redactor())->url($url))->toBe($expected);
})->with([
    'query and fragment values' => [
        'https://client.test/cb?code=abc&state=s1&flag#access_token=xyz&type=bearer',
        'https://client.test/cb?code=[REDACTED]&state=[REDACTED]&flag#access_token=[REDACTED]&type=[REDACTED]',
    ],
    'token-like path segments' => [
        'https://app.test/reset-password/0f3a9c1d2e4b5a69788776655443322?email=a@b.test',
        'https://app.test/reset-password/[REDACTED]?email=[REDACTED]',
    ],
    'ids, slugs and numbers stay' => [
        '/orders/01JCDV7Z9R2Q4P3M5N6B7C8D9E/lines/3f2504e0-4f89-11d3-9a0c-0305e82c3301/summer-sale-2026/12345678901234567890',
        '/orders/01JCDV7Z9R2Q4P3M5N6B7C8D9E/lines/3f2504e0-4f89-11d3-9a0c-0305e82c3301/summer-sale-2026/12345678901234567890',
    ],
    'refresh header' => ['0; url=/next?token=abc', '0; url=/next?token=[REDACTED]'],
    'anchor' => ['/docs#install', '/docs#install'],
]);

it('fills config keys a published file lacks without touching its lists', function (): void {
    $defaults = ['redact' => ['keys' => ['*password*', '*token*'], 'url_headers' => ['location', 'referer'], 'replacement' => '[REDACTED]'], 'enabled' => true];
    $published = ['redact' => ['keys' => ['*password*'], 'replacement' => '***'], 'enabled' => false];

    expect(RequestLogServiceProvider::fillMissingConfig($defaults, $published))->toBe([
        'redact' => ['keys' => ['*password*'], 'replacement' => '***', 'url_headers' => ['location', 'referer']],
        'enabled' => false,
    ])->and(RequestLogServiceProvider::fillMissingConfig($defaults, ['redact' => ['url_headers' => []]])['redact']['url_headers'])->toBe([]);
});
