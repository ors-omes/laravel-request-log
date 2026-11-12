<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OrsOmes\RequestLog\Http\Filters;
use OrsOmes\RequestLog\Http\Presenter;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\RequestLog;
use OrsOmes\RequestLog\Support\Markdown;
use OrsOmes\RequestLog\Support\RouteInfo;
use OrsOmes\RequestLog\Support\UserLabels;

final class ApiController
{
    private const SUGGEST_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $maxPerPage = Config::integer('request-log.api.max_per_page');
        $filters = Validator::make($request->query(), [...Filters::rules($maxPerPage), 'cursor' => ['nullable', 'string', 'max:512']])->validate();
        $perPage = min($maxPerPage, Filters::perPage($filters['per_page'] ?? null, 'api'));

        $page = Filters::scoped($request)
            ->filter($filters)
            ->newestFirst()
            ->select(Filters::listColumns())
            ->cursorPaginate($perPage)
            ->withQueryString();

        $labels = UserLabels::for(array_map(
            static fn (LoggedRequest $entry): ?string => $entry->user_id,
            $page->items(),
        ));

        return new JsonResponse([
            'data' => array_map(static fn (LoggedRequest $entry): array => Presenter::summary($entry, $labels), $page->items()),
            'per_page' => $page->perPage(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'prev_cursor' => $page->previousCursor()?->encode(),
        ]);
    }

    public function show(Request $request, string $entry): JsonResponse
    {
        $log = $this->find($request, $entry);

        return new JsonResponse(Presenter::detail($log, UserLabels::for([$log->user_id])));
    }

    public function markdown(Request $request, string $entry): Response
    {
        $log = $this->find($request, $entry);
        $label = $log->user_id === null ? null : (UserLabels::for([$log->user_id])[$log->user_id] ?? null);

        return new Response(Markdown::render($log, $label), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function suggest(Request $request, Router $router): JsonResponse
    {
        $input = Validator::make($request->query(), [
            'kind' => ['required', Rule::in(['action', 'user'])],
            'q' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $query = is_string($input['q'] ?? null) ? trim($input['q']) : '';

        if ($input['kind'] === 'user') {
            $search = RequestLog::userSearchCallback();
            $users = $search === null || $query === '' ? [] : $search($query, self::SUGGEST_LIMIT);

            return new JsonResponse(['data' => array_map(
                static fn (string $label, int|string $id): array => ['value' => (string) $id, 'label' => $label],
                array_values($users),
                array_keys($users),
            )]);
        }

        $actions = array_filter(
            RouteInfo::allActions($router),
            static fn (string $action): bool => $query === '' || Str::contains($action, $query, true),
        );

        return new JsonResponse(['data' => array_map(
            static fn (string $action): array => ['value' => $action, 'label' => RouteInfo::shortAction($action)],
            array_slice(array_values($actions), 0, self::SUGGEST_LIMIT),
        )]);
    }

    private function find(Request $request, string $id): LoggedRequest
    {
        abort_unless(Str::isUlid($id), 404);

        return Filters::scoped($request)->whereKey(strtolower($id))->firstOrFail();
    }
}
