<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http\Controllers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OrsOmes\RequestLog\Http\Filters;
use OrsOmes\RequestLog\Http\Middleware\ViewerHeaders;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Models\LoggedRequestQuery;
use OrsOmes\RequestLog\Recording\Reason;
use OrsOmes\RequestLog\Support\Markdown;
use OrsOmes\RequestLog\Support\RouteInfo;
use OrsOmes\RequestLog\Support\UserLabels;

final class ViewerController
{
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly ViewFactory $views,
    ) {}

    public static function telescopeUrl(string $reference): ?string
    {
        if (! Config::boolean('request-log.telescope.enabled') || ! class_exists('Laravel\Telescope\Telescope') || ! Route::has('telescope')) {
            return null;
        }

        $path = Config::get('telescope.path', 'telescope');

        return url((is_string($path) ? trim($path, '/') : 'telescope').'/requests?'.http_build_query(['tag' => 'ref:'.$reference]));
    }

    public function index(Request $request, Router $router): View
    {
        $validator = Validator::make($request->query(), Filters::rules(self::MAX_PER_PAGE));
        $filters = $validator->fails() ? [] : $validator->validated();
        $perPage = min(self::MAX_PER_PAGE, Filters::perPage($filters['per_page'] ?? null, 'viewer'));
        $before = $filters['before'] ?? null;

        $rows = Filters::scoped($request)
            ->filter($filters)
            ->olderThan(is_string($before) ? $before : null)
            ->newestFirst()
            ->limit($perPage + 1)
            ->get(Filters::listColumns());

        $entries = $rows->take($perPage);
        $next = $rows->count() > $perPage ? $entries->last()?->id : null;

        $recentErrors = Filters::scoped($request)
            ->whereStatus('5xx')
            ->newestFirst()
            ->limit(5)
            ->get(Filters::listColumns());

        return $this->views->make('request-log::index', [
            'entries' => $entries,
            'recentErrors' => $recentErrors,
            'labels' => UserLabels::for([...$entries->pluck('user_id')->all(), ...$recentErrors->pluck('user_id')->all()]),
            'filters' => array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $request->query()),
            'errors' => $validator->errors(),
            'nextCursor' => $next,
            'perPage' => $perPage,
            'actions' => RouteInfo::allActions($router),
            'methods' => Filters::METHODS,
            'reasons' => Reason::cases(),
            'ranges' => array_keys(LoggedRequestQuery::RANGES),
            'statuses' => LoggedRequestQuery::STATUS_CLASSES,
            'nonce' => self::nonce($request),
        ]);
    }

    public function show(Request $request, string $entry): View
    {
        $log = $this->find($request, $entry);

        $sameException = $log->exception_hash === null ? collect() : Filters::scoped($request)
            ->where('exception_hash', $log->exception_hash)
            ->whereKeyNot($log->id)
            ->newestFirst()
            ->limit(10)
            ->get(Filters::listColumns());

        $sameReference = Filters::scoped($request)
            ->where('reference', $log->reference)
            ->whereKeyNot($log->id)
            ->newestFirst()
            ->limit(10)
            ->get(Filters::listColumns());

        return $this->views->make('request-log::show', [
            'entry' => $log,
            'labels' => UserLabels::for([$log->user_id, ...$sameException->pluck('user_id')->all()]),
            'sameException' => $sameException,
            'sameReference' => $sameReference,
            'telescopeUrl' => self::telescopeUrl($log->reference),
            'nonce' => self::nonce($request),
        ]);
    }

    public function markdown(Request $request, string $entry): Response
    {
        $log = $this->find($request, $entry);
        $label = $log->user_id === null ? null : (UserLabels::for([$log->user_id])[$log->user_id] ?? null);

        return new Response(Markdown::render($log, $label), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private static function nonce(Request $request): string
    {
        $nonce = $request->attributes->get(ViewerHeaders::NONCE_ATTRIBUTE);

        return is_string($nonce) ? $nonce : '';
    }

    private function find(Request $request, string $id): LoggedRequest
    {
        abort_unless(Str::isUlid($id), 404);

        return Filters::scoped($request)->whereKey(strtolower($id))->firstOrFail();
    }
}
