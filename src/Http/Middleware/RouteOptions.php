<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OrsOmes\RequestLog\Recording\RequestState;
use OrsOmes\RequestLog\Recording\RouteMode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware alias "request-log":
 *   request-log:always    record even when the default profile would not (e.g. GET 200)
 *   request-log:off       never record
 *   request-log:metadata  record, but without request and response bodies
 */
final class RouteOptions
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        $routeMode = RouteMode::tryFrom($mode);
        if ($routeMode === null) {
            throw new InvalidArgumentException("Unknown request-log route mode [{$mode}]; use always, off or metadata.");
        }

        $state = RequestState::of($request);
        if ($state !== null) {
            $state->routeMode = $routeMode;
        }

        return $next($request);
    }
}
