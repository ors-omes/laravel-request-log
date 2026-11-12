<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OrsOmes\RequestLog\Capture\QueryCollector;
use OrsOmes\RequestLog\Console\AuditCommand;
use OrsOmes\RequestLog\Console\PruneCommand;
use OrsOmes\RequestLog\Http\Middleware\Authorize;
use OrsOmes\RequestLog\Http\Middleware\RecordRequest;
use OrsOmes\RequestLog\Http\Middleware\RouteOptions;
use OrsOmes\RequestLog\Http\Middleware\ViewerHeaders;
use OrsOmes\RequestLog\Integrations\TelescopeBridge;
use OrsOmes\RequestLog\Recording\Recorder;
use Throwable;

final class RequestLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/request-log.php', 'request-log');

        $this->app->scoped(QueryCollector::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'request-log');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'request-log');

        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerGate();
        $this->registerListeners();
        $this->registerSchedule();

        if (Config::boolean('request-log.telescope.enabled')) {
            TelescopeBridge::register();
        }
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/request-log.php' => config_path('request-log.php')], 'request-log-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'request-log-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/request-log')], 'request-log-views');
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class, AuditCommand::class]);
        }
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('request-log', RouteOptions::class);

        if (! Config::boolean('request-log.middleware.auto_register')) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);
        if ($kernel instanceof Kernel) {
            $kernel->prependMiddleware(RecordRequest::class);
        }
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (Config::boolean('request-log.viewer.enabled')) {
            Route::group([
                'prefix' => trim(Config::string('request-log.viewer.path'), '/'),
                'domain' => Config::get('request-log.viewer.domain'),
                'middleware' => [...Config::array('request-log.viewer.middleware'), Authorize::class.':viewer', ViewerHeaders::class],
                'as' => 'request-log.viewer.',
            ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/viewer.php'));
        }

        if (Config::boolean('request-log.api.enabled')) {
            Route::group([
                'prefix' => trim(Config::string('request-log.api.prefix'), '/'),
                'middleware' => [...Config::array('request-log.api.middleware'), Authorize::class.':api'],
                'as' => 'request-log.api.',
            ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/api.php'));
        }
    }

    /**
     * Deny by default; the application overrides the gate with its own rule.
     */
    private function registerGate(): void
    {
        if (! Gate::has(RequestLog::GATE)) {
            Gate::define(RequestLog::GATE, static fn (mixed $user = null): bool => false);
        }
    }

    private function registerListeners(): void
    {
        if (Config::boolean('request-log.queries.enabled')) {
            Event::listen(QueryExecuted::class, function (QueryExecuted $event): void {
                if (! Recorder::isRecording()) {
                    $this->app->make(QueryCollector::class)->record($event);
                }
            });
        }

        Event::listen(MessageLogged::class, static function (MessageLogged $event): void {
            $exception = $event->context['exception'] ?? null;

            if ($exception instanceof Throwable && ! Recorder::isRecording()) {
                RequestLog::captureException($exception);
            }
        });
    }

    private function registerSchedule(): void
    {
        if (! Config::boolean('request-log.retention.schedule')) {
            return;
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('request-log:prune')->dailyAt('03:15')->name('request-log:prune')->withoutOverlapping();
        });
    }
}
