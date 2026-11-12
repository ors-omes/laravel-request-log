<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OrsOmes\RequestLog\RequestLog;
use OrsOmes\RequestLog\Tests\Fixtures\User;

beforeEach(function (): void {
    Gate::define(RequestLog::GATE, static fn (?User $user = null): bool => $user?->getKey() === 'admin');
});

it('requires the gate', function (): void {
    $this->getJson('/api/request-logs')->assertForbidden();
    $this->actingAs(new User(['id' => 'someone']))->getJson('/api/request-logs')->assertForbidden();
});

it('lists with cursor pagination and exposes users as id and label only', function (): void {
    RequestLog::resolveUserLabelsUsing(static fn (array $ids): array => array_fill_keys($ids, 'Admin User'));
    $this->actingAs(new User(['id' => 'admin']));
    foreach (range(1, 3) as $index) {
        $this->postJson('/api/orders', ['n' => $index])->assertCreated();
    }

    $page = $this->getJson('/api/request-logs?per_page=2')->assertOk();

    expect($page->json('data'))->toHaveCount(2)
        ->and($page->json('data.0.user'))->toBe(['id' => 'admin', 'label' => 'Admin User'])
        ->and($page->json('data.0'))->not->toHaveKey('request_body')
        ->and($page->json('next_cursor'))->toBeString();

    $next = $this->getJson('/api/request-logs?per_page=2&cursor='.$page->json('next_cursor'))->assertOk();
    expect($next->json('data'))->toHaveCount(1);

    $this->getJson('/api/request-logs?per_page=1000')->assertStatus(422);

    $id = $page->json('data.0.id');
    $detail = $this->getJson('/api/request-logs/'.$id)->assertOk();
    expect(json_decode((string) $detail->json('request_body'), true))->toBe(['n' => 3]);

    $this->get('/api/request-logs/'.$id.'/markdown')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
});

it('suggests actions from the route list and users from the application callback', function (): void {
    RequestLog::searchUsersUsing(static fn (string $query, int $limit): array => ['u-1' => 'Lan '.$query]);
    $this->actingAs(new User(['id' => 'admin']));

    $actions = $this->getJson('/api/request-logs/suggest?kind=action&q=ordercontroller@st')->assertOk();
    expect($actions->json('data'))->toBe([[
        'value' => 'OrsOmes\RequestLog\Tests\Fixtures\OrderController@store',
        'label' => 'OrderController@store',
    ]]);

    $users = $this->getJson('/api/request-logs/suggest?kind=user&q=ng')->assertOk();
    expect($users->json('data'))->toBe([['value' => 'u-1', 'label' => 'Lan ng']]);
});
