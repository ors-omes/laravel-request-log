<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\RequestLog;
use OrsOmes\RequestLog\Tests\Fixtures\User;

function allowViewer(): void
{
    Gate::define(RequestLog::GATE, static fn (?User $user = null): bool => $user?->getKey() === 'admin');
}

it('denies the viewer outside local without the gate', function (): void {
    $this->postJson('/api/orders')->assertCreated();
    $log = latestRequestLog();

    $this->get('/request-logs')->assertForbidden();
    $this->get('/request-logs/'.$log->id)->assertForbidden();
    $this->get('/request-logs/'.$log->id.'/markdown')->assertForbidden();
    $this->actingAs(new User(['id' => 'someone']))->get('/request-logs')->assertForbidden();
});

it('allows the local environment', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    $this->get('/request-logs')->assertOk();

    Config::set('request-log.viewer.allow_local', false);
    $this->get('/request-logs')->assertForbidden();
});

it('lists, filters and shows records through the gate with strict headers', function (): void {
    allowViewer();
    $this->postJson('/api/orders', ['note' => '<script>alert(1)</script>'])->assertCreated();
    $this->getJson('/api/missing/7')->assertNotFound();
    $created = LoggedRequest::query()->where('status', 201)->firstOrFail();

    $this->actingAs(new User(['id' => 'admin']));

    $index = $this->get('/request-logs')->assertOk();
    $index->assertSee($created->reference)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($index->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->toContain("style-src 'nonce-");

    $this->get('/request-logs?status=4xx')->assertOk()->assertSee('/api/missing/7')->assertDontSee($created->reference);
    $this->get('/request-logs?reference='.strtolower($created->reference))->assertOk()->assertSee($created->reference);
    $this->get('/request-logs?path=/api/ord')->assertOk()->assertSee($created->reference);
    $this->get('/request-logs?path=%25')->assertOk()->assertDontSee($created->reference);

    $show = $this->get('/request-logs/'.$created->id)->assertOk();
    $show->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

    $markdown = $this->get('/request-logs/'.$created->id.'/markdown')->assertOk();
    expect($markdown->headers->get('Content-Type'))->toBe('text/plain; charset=UTF-8')
        ->and($markdown->getContent())->toContain('# Request log '.$created->reference)
        ->and($markdown->getContent())->toContain('## Request body');
});

it('reports invalid filters instead of failing', function (): void {
    allowViewer();

    $this->actingAs(new User(['id' => 'admin']))->get('/request-logs?status=abc&exception=zz')->assertOk()->assertSee('status');
});

it('pages with a keyset cursor', function (): void {
    allowViewer();
    foreach (range(1, 3) as $index) {
        $this->postJson('/api/orders', ['n' => $index])->assertCreated();
    }
    $ids = LoggedRequest::query()->newestFirst()->pluck('id')->all();

    $this->actingAs(new User(['id' => 'admin']));
    $first = $this->get('/request-logs?per_page=2')->assertOk();
    $first->assertSee('before='.$ids[1], false);

    $this->get('/request-logs?per_page=2&before='.$ids[1])->assertOk()
        ->assertSee(LoggedRequest::query()->whereKey($ids[2])->value('reference'))
        ->assertDontSee(LoggedRequest::query()->whereKey($ids[0])->value('reference'));
});

it('applies the application viewer scope', function (): void {
    allowViewer();
    RequestLog::resolveTenantUsing(static fn (): string => 'tenant-a');
    $this->postJson('/api/orders')->assertCreated();
    $log = latestRequestLog();

    RequestLog::scopeViewerUsing(static function ($query): void {
        $query->where('tenant_id', 'tenant-b');
    });

    $this->actingAs(new User(['id' => 'admin']));
    $this->get('/request-logs')->assertOk()->assertDontSee($log->reference);
    $this->get('/request-logs/'.$log->id)->assertNotFound();
});

it('does not record its own pages', function (): void {
    allowViewer();

    $this->actingAs(new User(['id' => 'admin']))->get('/request-logs/not-a-ulid')->assertNotFound();

    expect(LoggedRequest::query()->count())->toBe(0);
});

it('captures the authenticated user id', function (): void {
    $this->actingAs(new User(['id' => 'user-42']))->postJson('/api/orders')->assertCreated();

    expect(latestRequestLog()->user_id)->toBe('user-42');
});

it('keeps the json api disabled by default', function (): void {
    allowViewer();

    $this->actingAs(new User(['id' => 'admin']))->getJson('/api/request-logs')->assertNotFound();
});
