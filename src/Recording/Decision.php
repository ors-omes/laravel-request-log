<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Recording;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use OrsOmes\RequestLog\RequestLog;
use OrsOmes\RequestLog\Support\RouteInfo;
use OrsOmes\RequestLog\Support\Settings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cheap checks only: nothing here reads bodies or touches the database.
 */
final class Decision
{
    /**
     * Whether a request is considered at all: gets a reference, a Context
     * entry and query statistics.
     */
    public function considers(Request $request): bool
    {
        if (! Config::boolean('request-log.enabled')) {
            return false;
        }

        $path = $this->path($request);

        $only = $this->patterns('request-log.record.only_paths');
        if ($only !== [] && ! Str::is($only, $path)) {
            return false;
        }

        return ! Str::is([...$this->patterns('request-log.record.ignore_paths'), ...$this->ownPaths()], $path);
    }

    public function initialRouteMode(Request $request): ?RouteMode
    {
        $metadataOnly = $this->patterns('request-log.record.metadata_only_paths');

        return $metadataOnly !== [] && Str::is($metadataOnly, $this->path($request)) ? RouteMode::Metadata : null;
    }

    public function decide(Request $request, Response $response, RequestState $state): ?Reason
    {
        if ($state->routeMode === RouteMode::Off) {
            return null;
        }

        $default = $this->defaultReason($request, $response->getStatusCode(), $state);

        $filter = RequestLog::filterCallback();
        if ($filter === null) {
            return $default;
        }

        if (! $filter($request, $response, $default !== null)) {
            return null;
        }

        return $default ?? Reason::Forced;
    }

    public function wouldRecordStatus(Request $request, RequestState $state, int $status): bool
    {
        if ($state->routeMode === RouteMode::Off || $this->isIgnoredStatus($status) || $this->isIgnoredAction($request)) {
            return false;
        }

        return $state->routeMode === RouteMode::Always
            || $status >= $this->minErrorStatus()
            || $this->isRecordedMethod($request);
    }

    private function defaultReason(Request $request, int $status, RequestState $state): ?Reason
    {
        if ($this->isIgnoredStatus($status) || $this->isIgnoredAction($request)) {
            return null;
        }

        if ($status >= $this->minErrorStatus()) {
            return Reason::Error;
        }

        if ($this->isRecordedMethod($request)) {
            return Reason::Mutation;
        }

        if ($state->routeMode === RouteMode::Always) {
            return Reason::Forced;
        }

        $slowMs = Config::get('request-log.record.slow_ms');
        if (is_numeric($slowMs) && $state->durationMs !== null && $state->durationMs >= (int) $slowMs) {
            return Reason::Slow;
        }

        $sampleRate = Settings::number('request-log.record.sample_rate');
        if ($sampleRate > 0 && mt_rand() / mt_getrandmax() < $sampleRate) {
            return Reason::Sampled;
        }

        return null;
    }

    private function isRecordedMethod(Request $request): bool
    {
        $methods = array_map(
            static fn (mixed $method): string => is_string($method) ? strtoupper($method) : '',
            Config::array('request-log.record.methods'),
        );

        return in_array(strtoupper($request->getMethod()), $methods, true);
    }

    private function isIgnoredStatus(int $status): bool
    {
        foreach (Config::array('request-log.record.ignore_statuses') as $ignored) {
            if (is_numeric($ignored) && (int) $ignored === $status) {
                return true;
            }
        }

        return false;
    }

    private function isIgnoredAction(Request $request): bool
    {
        $patterns = $this->patterns('request-log.record.ignore_actions');
        if ($patterns === []) {
            return false;
        }

        $action = RouteInfo::action($request->route());
        if ($action === null) {
            return false;
        }

        return Str::is($patterns, $action) || Str::is($patterns, RouteInfo::shortAction($action));
    }

    private function minErrorStatus(): int
    {
        return Config::integer('request-log.record.min_error_status');
    }

    private function path(Request $request): string
    {
        return ltrim($request->path(), '/');
    }

    /**
     * @return list<string>
     */
    private function ownPaths(): array
    {
        $paths = [];

        if (Config::boolean('request-log.viewer.enabled')) {
            $paths[] = trim(Config::string('request-log.viewer.path'), '/').'*';
        }

        if (Config::boolean('request-log.api.enabled')) {
            $paths[] = trim(Config::string('request-log.api.prefix'), '/').'*';
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function patterns(string $key): array
    {
        return array_values(array_filter(
            Config::array($key),
            static fn (mixed $pattern): bool => is_string($pattern) && $pattern !== '',
        ));
    }
}
