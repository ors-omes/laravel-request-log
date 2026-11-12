<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use OrsOmes\RequestLog\Capture\QueryCollector;
use OrsOmes\RequestLog\Recording\Decision;
use OrsOmes\RequestLog\Recording\Recorder;
use OrsOmes\RequestLog\Recording\RequestState;
use OrsOmes\RequestLog\Support\ReferenceCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Global, terminable middleware. handle() assigns the reference and makes the
 * cheap recording decision; terminate() builds and stores the record after
 * the response has been sent.
 */
final class RecordRequest
{
    public function __construct(
        private readonly Container $container,
        private readonly Decision $decision,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (RequestState::of($request) !== null || ! $this->decision->considers($request)) {
            return $next($request);
        }

        $state = $this->start($request);

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            $state->exception = $throwable;

            throw $throwable;
        }

        $state->durationMs = Recorder::elapsedMs($state);
        $this->decide($request, $response, $state);

        $header = Config::get('request-log.reference.header');
        if ($state->reason !== null && is_string($header) && $header !== '') {
            $response->headers->set($header, $state->reference);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $state = RequestState::of($request);
        if ($state === null) {
            return;
        }

        if (! $state->decided) {
            $state->durationMs ??= Recorder::elapsedMs($state);
            $this->decide($request, $response, $state);
        }

        if ($state->reason === null) {
            $this->stopQueries();

            return;
        }

        $this->container->make(Recorder::class)->record($request, $response, $state);
    }

    private function start(Request $request): RequestState
    {
        $startedAt = $this->startTime($request);
        $state = new RequestState(ReferenceCode::generate(), CarbonImmutable::createFromTimestampMs((int) round($startedAt * 1000)), $startedAt);
        $state->routeMode = $this->decision->initialRouteMode($request);
        RequestState::attach($request, $state);

        $contextKey = Config::get('request-log.reference.context_key');
        if (is_string($contextKey) && $contextKey !== '') {
            Context::add($contextKey, $state->reference);
        }

        if (Config::boolean('request-log.queries.enabled')) {
            $this->container->make(QueryCollector::class)->start();
        }

        return $state;
    }

    private function decide(Request $request, Response $response, RequestState $state): void
    {
        $state->decided = true;

        try {
            $state->reason = $this->decision->decide($request, $response, $state);
        } catch (Throwable $throwable) {
            $state->reason = null;
            report($throwable);
        }
    }

    private function stopQueries(): void
    {
        if (Config::boolean('request-log.queries.enabled')) {
            $this->container->make(QueryCollector::class)->stop();
        }
    }

    /**
     * REQUEST_TIME_FLOAT is per request under FPM and Octane alike (unlike
     * LARAVEL_START, which is the worker start under Octane). Values that are
     * not plausible fall back to now.
     */
    private function startTime(Request $request): float
    {
        $now = microtime(true);
        $requestTime = $request->server('REQUEST_TIME_FLOAT');

        if (is_numeric($requestTime) && $now - (float) $requestTime >= 0 && $now - (float) $requestTime < 300) {
            return (float) $requestTime;
        }

        return $now;
    }
}
