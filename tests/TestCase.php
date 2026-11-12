<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;
use OrsOmes\RequestLog\RequestLog;
use OrsOmes\RequestLog\RequestLogServiceProvider;
use OrsOmes\RequestLog\Support\Revision;
use OrsOmes\RequestLog\Tests\Fixtures\TestRoutes;

abstract class TestCase extends Orchestra
{
    /**
     * On PostgreSQL the log is written through its own connection, as in
     * production, so a failed application query cannot abort the log insert.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        $this->connectionsToTransact = self::usesPostgres() ? ['pgsql_test', 'pgsql_log_test'] : [null];

        parent::setUp();
    }

    protected function tearDown(): void
    {
        RequestLog::flushCallbacks();
        Revision::flush();

        parent::tearDown();
    }

    /**
     * CI runs the suite a second time against PostgreSQL with
     * REQUEST_LOG_TEST_DB=pgsql and the DB_* variables of a disposable database.
     */
    protected static function usesPostgres(): bool
    {
        return getenv('REQUEST_LOG_TEST_DB') === 'pgsql';
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RequestLogServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        tap($app->make(Repository::class), static function (Repository $config): void {
            $config->set('database.default', self::usesPostgres() ? 'pgsql_test' : 'testing');
            $pgsql = [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'request_log_test',
                'username' => getenv('DB_USERNAME') ?: 'postgres',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ];
            $config->set('database.connections.pgsql_test', $pgsql);
            $config->set('database.connections.pgsql_log_test', $pgsql);

            if (self::usesPostgres()) {
                $config->set('request-log.storage.connection', 'pgsql_log_test');
            }
            $config->set('request-log.revision', 'abc123');
            $config->set('request-log.retention.schedule', false);
        });
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        TestRoutes::register($router);
    }
}
