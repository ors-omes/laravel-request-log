<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The viewer ships no JavaScript; the only inline style carries a per-request
 * nonce, so the policy can deny everything else.
 */
final class ViewerHeaders
{
    public const NONCE_ATTRIBUTE = 'request_log.nonce';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        $request->attributes->set(self::NONCE_ATTRIBUTE, $nonce);

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "style-src 'nonce-{$nonce}'",
            "img-src 'self' data:",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'none'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
