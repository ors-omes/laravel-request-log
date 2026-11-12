<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OrsOmes\RequestLog\Models\LoggedRequestQuery;
use OrsOmes\RequestLog\Recording\Decision;
use OrsOmes\RequestLog\Recording\RequestState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Application-facing API. Register callbacks from a service provider's
 * boot() method; they are not stored in config so `config:cache` keeps
 * working.
 */
final class RequestLog
{
    public const GATE = 'viewRequestLogs';

    /** @var (Closure(Request, Response, bool): bool)|null */
    private static ?Closure $filterCallback = null;

    /** @var (Closure(Request): (string|null))|null */
    private static ?Closure $tenantCallback = null;

    /** @var (Closure(Request): (string|null))|null */
    private static ?Closure $subjectCallback = null;

    /** @var (Closure(Request, Response): array<string, mixed>)|null */
    private static ?Closure $contextCallback = null;

    /** @var (Closure(list<string>): array<string, string>)|null */
    private static ?Closure $userLabelsCallback = null;

    /** @var (Closure(string, int): array<string, string>)|null */
    private static ?Closure $userSearchCallback = null;

    /** @var (Closure(LoggedRequestQuery, Request): void)|null */
    private static ?Closure $viewerScopeCallback = null;

    /**
     * Override the recording decision. The callback receives the default
     * decision and returns whether the request is recorded. Requests recorded
     * only because of the callback get the "forced" reason.
     *
     * @param  (Closure(Request, Response, bool): bool)|null  $callback
     */
    public static function filterUsing(?Closure $callback): void
    {
        self::$filterCallback = $callback;
    }

    /**
     * @param  (Closure(Request): (string|null))|null  $callback
     */
    public static function resolveTenantUsing(?Closure $callback): void
    {
        self::$tenantCallback = $callback;
    }

    /**
     * Resolve the record the request acted on, e.g. "Order:01J...". By default
     * the last route-model-bound parameter, or the last id-like parameter.
     *
     * @param  (Closure(Request): (string|null))|null  $callback
     */
    public static function resolveSubjectUsing(?Closure $callback): void
    {
        self::$subjectCallback = $callback;
    }

    /**
     * Extra application data stored in the "context" column (redacted).
     *
     * @param  (Closure(Request, Response): array<string, mixed>)|null  $callback
     */
    public static function contextUsing(?Closure $callback): void
    {
        self::$contextCallback = $callback;
    }

    /**
     * Map user ids to display labels for the viewer and API.
     *
     * @param  (Closure(list<string>): array<string, string>)|null  $callback
     */
    public static function resolveUserLabelsUsing(?Closure $callback): void
    {
        self::$userLabelsCallback = $callback;
    }

    /**
     * Search users for the API suggest endpoint; returns [id => label].
     *
     * @param  (Closure(string, int): array<string, string>)|null  $callback
     */
    public static function searchUsersUsing(?Closure $callback): void
    {
        self::$userSearchCallback = $callback;
    }

    /**
     * Restrict what the viewer and API can read, e.g. to the current tenant.
     *
     * @param  (Closure(LoggedRequestQuery, Request): void)|null  $callback
     */
    public static function scopeViewerUsing(?Closure $callback): void
    {
        self::$viewerScopeCallback = $callback;
    }

    /**
     * Reference of the current request, or null when the request is not
     * considered for recording (disabled, ignored path, "request-log:off").
     */
    public static function reference(?Request $request = null): ?string
    {
        $state = self::state($request);

        return $state?->reference;
    }

    /**
     * Reference to show to a client for a response with the given status:
     * null unless the request will be recorded with that status. Use it in
     * exception renderers so a returned reference always resolves to a row.
     */
    public static function referenceFor(int $status, ?Request $request = null): ?string
    {
        $request ??= self::currentRequest();
        if ($request === null) {
            return null;
        }

        $state = RequestState::of($request);
        if ($state === null) {
            return null;
        }

        return app(Decision::class)->wouldRecordStatus($request, $state, $status) ? $state->reference : null;
    }

    /**
     * Attach an exception to the current request when the framework does not
     * (the recorder already reads the exception from rendered responses).
     */
    public static function captureException(Throwable $exception, ?Request $request = null): void
    {
        $state = self::state($request);
        if ($state !== null && $state->exception === null) {
            $state->exception = $exception;
        }
    }

    public static function check(Request $request, bool $allowLocal): bool
    {
        if ($allowLocal && app()->environment('local')) {
            return true;
        }

        return Gate::forUser($request->user())->allows(self::GATE);
    }

    /**
     * @return (Closure(Request, Response, bool): bool)|null
     */
    public static function filterCallback(): ?Closure
    {
        return self::$filterCallback;
    }

    /**
     * @return (Closure(Request): (string|null))|null
     */
    public static function tenantCallback(): ?Closure
    {
        return self::$tenantCallback;
    }

    /**
     * @return (Closure(Request): (string|null))|null
     */
    public static function subjectCallback(): ?Closure
    {
        return self::$subjectCallback;
    }

    /**
     * @return (Closure(Request, Response): array<string, mixed>)|null
     */
    public static function contextCallback(): ?Closure
    {
        return self::$contextCallback;
    }

    /**
     * @return (Closure(list<string>): array<string, string>)|null
     */
    public static function userLabelsCallback(): ?Closure
    {
        return self::$userLabelsCallback;
    }

    /**
     * @return (Closure(string, int): array<string, string>)|null
     */
    public static function userSearchCallback(): ?Closure
    {
        return self::$userSearchCallback;
    }

    /**
     * @return (Closure(LoggedRequestQuery, Request): void)|null
     */
    public static function viewerScopeCallback(): ?Closure
    {
        return self::$viewerScopeCallback;
    }

    public static function flushCallbacks(): void
    {
        self::$filterCallback = null;
        self::$tenantCallback = null;
        self::$subjectCallback = null;
        self::$contextCallback = null;
        self::$userLabelsCallback = null;
        self::$userSearchCallback = null;
        self::$viewerScopeCallback = null;
    }

    private static function state(?Request $request): ?RequestState
    {
        $request ??= self::currentRequest();

        return $request === null ? null : RequestState::of($request);
    }

    private static function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request ? $request : null;
    }
}
