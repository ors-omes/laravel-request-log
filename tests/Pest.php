<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Tests\ApiTestCase;
use OrsOmes\RequestLog\Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(ApiTestCase::class)->use(RefreshDatabase::class)->in('Api');
pest()->extend(TestCase::class)->in('Unit');

function latestRequestLog(): LoggedRequest
{
    $log = LoggedRequest::query()->newestFirst()->first();
    expect($log)->not->toBeNull();

    return $log;
}
