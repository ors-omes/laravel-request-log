<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests;

use Illuminate\Foundation\Application;

abstract class ApiTestCase extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('request-log.api.enabled', true);
    }
}
