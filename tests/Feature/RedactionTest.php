<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;

it('redacts sensitive keys at any depth, case-insensitively, including empty values', function (): void {
    $this->postJson('/api/echo', [
        'name' => 'An',
        'Password' => 'hunter2',
        'current_password' => '',
        'profile' => ['New-Password' => 'x', 'client_secret' => 's', 'nested' => ['access_token' => null, 'otp' => '123456']],
        'items' => [['card_number' => '4111111111111111', 'qty' => 1]],
    ])->assertOk();

    $body = json_decode((string) latestRequestLog()->request_body, true);

    expect($body)->toBe([
        'name' => 'An',
        'Password' => '[REDACTED]',
        'current_password' => '[REDACTED]',
        'profile' => ['New-Password' => '[REDACTED]', 'client_secret' => '[REDACTED]', 'nested' => ['access_token' => '[REDACTED]', 'otp' => '[REDACTED]']],
        'items' => [['card_number' => '[REDACTED]', 'qty' => 1]],
    ]);

    $response = json_decode((string) latestRequestLog()->response_body, true);
    expect($response['Password'])->toBe('[REDACTED]');
});

it('redacts tokens in login responses and token-looking values under harmless keys', function (): void {
    $this->postJson('/api/login', [
        'username' => 'an',
        'password' => 'secret',
        'note' => 'Bearer abcdefghijklmnop and eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJlLXZhbHVl',
    ])->assertOk();

    $request = json_decode((string) latestRequestLog()->request_body, true);
    $response = json_decode((string) latestRequestLog()->response_body, true);

    expect($request['password'])->toBe('[REDACTED]')
        ->and($request['note'])->toBe('[REDACTED] and [REDACTED]')
        ->and($response['access_token'])->toBe('[REDACTED]')
        ->and($response['refresh_token'])->toBe('[REDACTED]')
        ->and($response['expires_in'])->toBe(3600)
        ->and(latestRequestLog()->response_body)->not->toContain('eyJ');
});

it('redacts query parameters in the stored url', function (): void {
    $this->postJson('/api/orders?api_key=abc&page=2&Access-Token=zzz')->assertCreated();

    expect(latestRequestLog()->url)->toBe('/api/orders?api_key=[REDACTED]&page=2&Access-Token=[REDACTED]');
});

it('redacts request and response headers with separate lists', function (): void {
    $this->withHeaders([
        'Authorization' => 'Bearer abcdefghijklmnopqrstuvwxyz',
        'Cookie' => 'laravel_session=abc',
        'X-Api-Key' => 'k',
        'X-Custom-Token' => 't',
        'Accept-Language' => 'vi',
    ])->postJson('/api/orders')->assertCreated();

    $request = json_decode((string) latestRequestLog()->request_headers, true);

    expect($request['authorization'])->toBe('[REDACTED]')
        ->and($request['cookie'])->toBe('[REDACTED]')
        ->and($request['x-api-key'])->toBe('[REDACTED]')
        ->and($request['x-custom-token'])->toBe('[REDACTED]')
        ->and($request['accept-language'])->toBe('vi');

    $this->post('/api/redirect')->assertRedirect();
    $response = json_decode((string) latestRequestLog()->response_headers, true);

    expect($response)->toHaveKey('location')
        ->and(latestRequestLog()->response_body)->toEndWith('/done?token=[REDACTED]&page=2')
        ->and(latestRequestLog()->response_kind)->toBe('redirect');
});

it('stores uploaded files as metadata only', function (): void {
    $file = UploadedFile::fake()->createWithContent('report.csv', "a,b\n1,2\n");

    $this->post('/api/echo', ['title' => 'Q3', 'attachment' => $file, 'password' => 'p'])->assertOk();

    $body = json_decode((string) latestRequestLog()->request_body, true);

    expect($body['title'])->toBe('Q3')
        ->and($body['password'])->toBe('[REDACTED]')
        ->and($body['attachment'])->toBe(['file' => 'report.csv', 'size' => 8, 'mime' => 'text/csv'])
        ->and(latestRequestLog()->request_body)->not->toContain('1,2');
});

it('does not store non-JSON text bodies unless enabled', function (): void {
    $this->call('POST', '/api/orders', [], [], [], ['CONTENT_TYPE' => 'text/xml'], '<order><password>x</password></order>')->assertCreated();

    expect(latestRequestLog()->request_body)->toBe('[unparsed text/xml: 37 bytes]');

    Config::set('request-log.capture.raw_text_bodies', true);
    $this->call('POST', '/api/orders', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'note with Bearer abcdefghijklmnop')->assertCreated();

    expect(latestRequestLog()->request_body)->toBe('note with [REDACTED]');
});

it('keeps html responses out of the log', function (): void {
    $this->post('/api/html')->assertStatus(422);

    expect(latestRequestLog()->response_kind)->toBe('html')
        ->and(latestRequestLog()->response_body)->toStartWith('[html text/html:')
        ->and(latestRequestLog()->response_body)->not->toContain('_token');
});

it('describes streamed responses without reading them', function (): void {
    $this->post('/api/stream')->assertOk();

    expect(latestRequestLog()->response_kind)->toBe('stream')
        ->and(latestRequestLog()->response_body)->toBe('[streamed text/csv]');
});

it('honours extra keys and can mask the ip address', function (): void {
    Config::set('request-log.redact.extra_keys', ['national_id']);
    Config::set('request-log.capture.ip', 'masked');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->postJson('/api/echo', ['national_id' => '0790', 'city' => 'HN'])->assertOk();

    expect(json_decode((string) latestRequestLog()->request_body, true))->toBe(['national_id' => '[REDACTED]', 'city' => 'HN'])
        ->and(latestRequestLog()->ip)->toBe('203.0.113.0');
});

it('redacts sensitive route parameters in the path and signed url signatures', function (): void {
    $this->postJson('/api/reset-password/123456?email=a@b.c&signature=abc123')->assertOk();

    expect(latestRequestLog()->path)->toBe('/api/reset-password/[REDACTED]')
        ->and(latestRequestLog()->url)->toBe('/api/reset-password/[REDACTED]?email=a@b.c&signature=[REDACTED]')
        ->and(latestRequestLog()->route_uri)->toBe('api/reset-password/{token}')
        ->and(latestRequestLog()->subject)->toBeNull();
});
