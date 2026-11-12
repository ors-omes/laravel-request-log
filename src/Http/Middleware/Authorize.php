<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use OrsOmes\RequestLog\RequestLog;
use Symfony\Component\HttpFoundation\Response;

final class Authorize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $area = 'viewer'): Response
    {
        $allowLocal = Config::boolean($area === 'api' ? 'request-log.api.allow_local' : 'request-log.viewer.allow_local');

        abort_unless(RequestLog::check($request, $allowLocal), 403);

        return $next($request);
    }
}
