<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Recording;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use OrsOmes\RequestLog\Capture\ExceptionCapture;
use OrsOmes\RequestLog\Capture\QueryCollector;
use OrsOmes\RequestLog\Capture\RequestCapture;
use OrsOmes\RequestLog\Capture\ResponseCapture;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\RequestLog;
use OrsOmes\RequestLog\Support\Redactor;
use OrsOmes\RequestLog\Support\Revision;
use OrsOmes\RequestLog\Support\RouteInfo;
use OrsOmes\RequestLog\Support\Truncator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Builds and stores one record. Runs after the response was sent; failures
 * are logged and swallowed so logging can never break a request.
 */
final class Recorder
{
    private const CONTEXT_BYTES = 16384;

    private static bool $recording = false;

    public function __construct(
        private readonly Container $container,
    ) {}

    public static function isRecording(): bool
    {
        return self::$recording;
    }

    public static function elapsedMs(RequestState $state): int
    {
        return max(0, (int) round((microtime(true) - $state->startedAtMicrotime) * 1000));
    }

    public function record(Request $request, Response $response, RequestState $state): void
    {
        if (self::$recording) {
            return;
        }

        self::$recording = true;

        try {
            LoggedRequest::query()->create($this->attributes($request, $response, $state));
        } catch (Throwable $throwable) {
            Log::warning('request-log: could not store request '.$state->reference, [
                'error' => $throwable instanceof QueryException
                    ? 'SQLSTATE['.$throwable->getCode().']'
                    : $throwable::class.': '.mb_strcut($throwable->getMessage(), 0, 300, 'UTF-8'),
            ]);
        } finally {
            self::$recording = false;
        }
    }

    private static function hostname(): ?string
    {
        $hostname = gethostname();

        return $hostname === false ? null : $hostname;
    }

    private static function limit(?string $value, int $bytes): ?string
    {
        return $value === null || $value === '' ? null : mb_strcut($value, 0, $bytes, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, Response $response, RequestState $state): array
    {
        $redactor = new Redactor();
        $truncator = new Truncator();
        $requestCapture = new RequestCapture($redactor, $truncator);
        $responseCapture = new ResponseCapture($redactor, $truncator);
        $metadataOnly = $state->routeMode === RouteMode::Metadata;

        $route = $request->route();
        $route = $route instanceof Route ? $route : null;
        $status = $response->getStatusCode();
        $requestBody = $requestCapture->body($request, $metadataOnly);
        $responseBody = $responseCapture->body($response, $metadataOnly);
        $queries = $this->queries();
        $exception = $this->exception($response, $state);

        return [
            'reference' => $state->reference,
            'reason' => $state->reason,
            'method' => strtoupper($request->getMethod()),
            'url' => $requestCapture->url($request),
            'path' => mb_strcut($requestCapture->path($request), 0, 255, 'UTF-8'),
            'route_uri' => $route?->uri(),
            'route_name' => $route?->getName(),
            'action' => self::limit(RouteInfo::action($route), 255),
            'subject' => self::limit($this->subject($request, $route, $redactor), 255),
            'status' => $status,
            'user_id' => self::limit($this->userId(), 64),
            'tenant_id' => self::limit($this->tenantId($request), 64),
            'ip' => $requestCapture->ip($request),
            'user_agent' => $requestCapture->userAgent($request),
            'hostname' => self::limit(self::hostname(), 255),
            'revision' => Revision::current(),
            'duration_ms' => $state->durationMs ?? self::elapsedMs($state),
            'memory_peak_kb' => (int) round(memory_get_peak_usage(true) / 1024),
            'query_count' => $queries['count'],
            'query_time_ms' => $queries['time_ms'],
            'queries' => $queries['slow'] === [] ? null : $queries['slow'],
            'request_headers' => $requestCapture->headers($request),
            'request_body' => $requestBody['body'],
            'request_bytes' => $requestBody['bytes'],
            'request_truncated' => $requestBody['truncated'],
            'response_kind' => $responseBody['kind'],
            'response_headers' => $responseCapture->headers($response),
            'response_body' => $responseBody['body'],
            'response_bytes' => $responseBody['bytes'],
            'response_truncated' => $responseBody['truncated'],
            ...($exception === null ? [] : (new ExceptionCapture($redactor, $truncator))->capture($exception, $status)),
            'context' => $this->context($request, $response, $redactor, $truncator),
            'created_at' => $state->startedAt,
        ];
    }

    /**
     * @return array{count: int, time_ms: int, slow: list<array{sql: string, time_ms: float, connection: string, caller: string|null}>}
     */
    private function queries(): array
    {
        if (! Config::boolean('request-log.queries.enabled')) {
            return ['count' => 0, 'time_ms' => 0, 'slow' => []];
        }

        $collector = $this->container->make(QueryCollector::class);
        $collector->stop();

        return $collector->summary();
    }

    private function exception(Response $response, RequestState $state): ?Throwable
    {
        $attached = property_exists($response, 'exception') ? $response->exception : null;

        return $attached instanceof Throwable ? $attached : $state->exception;
    }

    private function subject(Request $request, ?Route $route, Redactor $redactor): ?string
    {
        $callback = RequestLog::subjectCallback();

        return $callback !== null ? $callback($request) : RouteInfo::subject($route, $redactor->isSensitiveKey(...));
    }

    private function tenantId(Request $request): ?string
    {
        $callback = RequestLog::tenantCallback();

        return $callback === null ? null : $callback($request);
    }

    /**
     * Only reads a user that authentication already resolved, so recording
     * never triggers a guard or an extra query.
     */
    private function userId(): ?string
    {
        if (! $this->container->bound('auth')) {
            return null;
        }

        $auth = $this->container->make('auth');
        if (! $auth instanceof AuthManager || ! $auth->hasResolvedGuards()) {
            return null;
        }

        $guard = $auth->guard();
        if (! method_exists($guard, 'hasUser') || ! $guard->hasUser()) {
            return null;
        }

        $id = $guard->id();

        return is_scalar($id) ? (string) $id : null;
    }

    private function context(Request $request, Response $response, Redactor $redactor, Truncator $truncator): ?string
    {
        $callback = RequestLog::contextCallback();
        if ($callback === null) {
            return null;
        }

        $context = $callback($request, $response);
        if ($context === []) {
            return null;
        }

        [$encoded] = $truncator->json($redactor->data($context), self::CONTEXT_BYTES);

        return $encoded;
    }
}
