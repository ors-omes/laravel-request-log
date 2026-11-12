<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Recording;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Per-request recording state, stored on the request attributes so it is
 * safe under Octane and shared by the middleware's handle() and terminate().
 */
final class RequestState
{
    private const ATTRIBUTE = 'request_log.state';

    public ?RouteMode $routeMode = null;

    public bool $decided = false;

    public ?Reason $reason = null;

    public ?Throwable $exception = null;

    public ?int $durationMs = null;

    public function __construct(
        public readonly string $reference,
        public readonly CarbonImmutable $startedAt,
        public readonly float $startedAtMicrotime,
    ) {}

    public static function attach(Request $request, self $state): void
    {
        $request->attributes->set(self::ATTRIBUTE, $state);
    }

    public static function of(Request $request): ?self
    {
        $state = $request->attributes->get(self::ATTRIBUTE);

        return $state instanceof self ? $state : null;
    }
}
