<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\RequestLog;
use OrsOmes\RequestLog\Support\ReferenceCode;

it('shares the reference through the Context and the response header', function (): void {
    Route::post('api/context', static fn (): JsonResponse => new JsonResponse([
        'context' => Context::get('request_reference'),
        'reference' => RequestLog::reference(),
    ]));

    $response = $this->postJson('/api/context')->assertOk();
    $log = LoggedRequest::query()->firstOrFail();

    expect($response->json('context'))->toBe($log->reference)
        ->and($response->json('reference'))->toBe($log->reference)
        ->and($response->headers->get('X-Request-Reference'))->toBe($log->reference);
});

it('only hands out a reference for statuses that will be recorded', function (): void {
    Config::set('request-log.record.ignore_statuses', [401]);

    Route::get('api/reference-for', static fn (Request $request): JsonResponse => new JsonResponse([
        '404' => RequestLog::referenceFor(404, $request),
        '401' => RequestLog::referenceFor(401, $request),
        '200' => RequestLog::referenceFor(200, $request),
    ]));
    Route::get('api/reference-off', static fn (Request $request): JsonResponse => new JsonResponse([
        '500' => RequestLog::referenceFor(500, $request),
    ]))->middleware('request-log:off');

    $response = $this->getJson('/api/reference-for')->assertOk();

    expect($response->json('404'))->toBeString()
        ->and($response->json('401'))->toBeNull()
        ->and($response->json('200'))->toBeNull()
        ->and($this->getJson('/api/reference-off')->json('500'))->toBeNull();
});

it('generates and normalizes crockford references', function (): void {
    $code = ReferenceCode::generate();

    expect($code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{10}$/')
        ->and(ReferenceCode::normalize('[ab-cd ef0o1il]'))->toBe('ABCDEF00111');
});

it('reads the revision from git refs when not configured', function (): void {
    Config::set('request-log.revision', null);
    $base = sys_get_temp_dir().'/request-log-git-'.bin2hex(random_bytes(4));
    mkdir($base.'/.git', 0777, true);
    file_put_contents($base.'/.git/HEAD', "ref: refs/heads/main\n");
    file_put_contents($base.'/.git/packed-refs', "# pack-refs\n".str_repeat('a', 40)." refs/heads/main\n");
    $this->app->setBasePath($base);

    expect(OrsOmes\RequestLog\Support\Revision::current())->toBe(str_repeat('a', 40));
});
