@extends('request-log::layout')

@section('content')
    <form class="panel" method="get" action="{{ route('request-log.viewer.index') }}">
        <div class="filters">
            <div><label for="f-reference">{{ __('request-log::viewer.reference') }}</label><input id="f-reference" name="reference" value="{{ $filters['reference'] ?? '' }}" autocomplete="off"></div>
            <div><label for="f-method">{{ __('request-log::viewer.method') }}</label>
                <select id="f-method" name="method"><option value="">{{ __('request-log::viewer.any') }}</option>
                    @foreach ($methods as $method)<option value="{{ $method }}" @selected(strtoupper($filters['method'] ?? '') === $method)>{{ $method }}</option>@endforeach
                </select></div>
            <div><label for="f-status">{{ __('request-log::viewer.status') }}</label><input id="f-status" name="status" list="request-log-statuses" value="{{ $filters['status'] ?? '' }}" placeholder="4xx, 500"></div>
            <div><label for="f-reason">{{ __('request-log::viewer.reason') }}</label>
                <select id="f-reason" name="reason"><option value="">{{ __('request-log::viewer.any') }}</option>
                    @foreach ($reasons as $reason)<option value="{{ $reason->value }}" @selected(($filters['reason'] ?? '') === $reason->value)>{{ $reason->value }}</option>@endforeach
                </select></div>
            <div><label for="f-action">{{ __('request-log::viewer.action') }}</label><input id="f-action" name="action" list="request-log-actions" value="{{ $filters['action'] ?? '' }}"></div>
            <div><label for="f-path">{{ __('request-log::viewer.path_prefix') }}</label><input id="f-path" name="path" value="{{ $filters['path'] ?? '' }}" placeholder="/api/orders"></div>
            <div><label for="f-route">{{ __('request-log::viewer.route') }}</label><input id="f-route" name="route" value="{{ $filters['route'] ?? '' }}" placeholder="api/orders/{order}"></div>
            <div><label for="f-user">{{ __('request-log::viewer.user') }}</label><input id="f-user" name="user" value="{{ $filters['user'] ?? '' }}"></div>
            <div><label for="f-tenant">{{ __('request-log::viewer.tenant') }}</label><input id="f-tenant" name="tenant" value="{{ $filters['tenant'] ?? '' }}"></div>
            <div><label for="f-subject">{{ __('request-log::viewer.subject') }}</label><input id="f-subject" name="subject" value="{{ $filters['subject'] ?? '' }}" placeholder="Order:01J…"></div>
            <div><label for="f-exception">{{ __('request-log::viewer.exception_hash') }}</label><input id="f-exception" name="exception" value="{{ $filters['exception'] ?? '' }}"></div>
            <div><label for="f-range">{{ __('request-log::viewer.range') }}</label>
                <select id="f-range" name="range"><option value="">{{ __('request-log::viewer.any') }}</option>
                    @foreach ($ranges as $range)<option value="{{ $range }}" @selected(($filters['range'] ?? '') === $range)>{{ $range }}</option>@endforeach
                </select></div>
            <div><label for="f-from">{{ __('request-log::viewer.from') }}</label><input id="f-from" type="datetime-local" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div><label for="f-to">{{ __('request-log::viewer.to') }}</label><input id="f-to" type="datetime-local" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div><label for="f-per-page">{{ __('request-log::viewer.per_page') }}</label><input id="f-per-page" type="number" min="1" max="200" name="per_page" value="{{ $filters['per_page'] ?? $perPage }}"></div>
            <div class="toolbar"><button type="submit">{{ __('request-log::viewer.apply') }}</button><a class="button secondary" href="{{ route('request-log.viewer.index') }}">{{ __('request-log::viewer.reset') }}</a></div>
        </div>
        <datalist id="request-log-actions">@foreach ($actions as $action)<option value="{{ $action }}">{{ \OrsOmes\RequestLog\Support\RouteInfo::shortAction($action) }}</option>@endforeach</datalist>
        <datalist id="request-log-statuses">@foreach ($statuses as $status)<option value="{{ $status }}"></option>@endforeach</datalist>
        @if ($errors->isNotEmpty())
            <p class="errors">{{ __('request-log::viewer.invalid_filters') }} {{ implode(' ', $errors->all()) }}</p>
        @endif
    </form>

    @if ($recentErrors->isNotEmpty() && ($filters === [] || array_filter($filters) === []))
        <section class="panel">
            <h2 class="muted">{{ __('request-log::viewer.recent_errors') }}</h2>
            @include('request-log::partials.rows', ['rows' => $recentErrors])
        </section>
    @endif

    <section class="panel">
        @if ($entries->isEmpty())
            <p class="muted">{{ __('request-log::viewer.no_entries') }}</p>
        @else
            @include('request-log::partials.rows', ['rows' => $entries])
        @endif
        <p class="toolbar">
            @if (isset($filters['before']) && $filters['before'] !== '')
                <a href="{{ route('request-log.viewer.index', \Illuminate\Support\Arr::except($filters, ['before'])) }}">« {{ __('request-log::viewer.newest') }}</a>
            @endif
            @if ($nextCursor !== null)
                <a href="{{ route('request-log.viewer.index', [...$filters, 'before' => $nextCursor]) }}">{{ __('request-log::viewer.older') }} »</a>
            @endif
        </p>
    </section>
@endsection
