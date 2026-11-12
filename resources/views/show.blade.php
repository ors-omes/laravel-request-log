@extends('request-log::layout')

@section('title', $entry->reference.' · '.__('request-log::viewer.title'))

@section('toolbar')
    <div class="toolbar">
        <a href="{{ route('request-log.viewer.index') }}">« {{ __('request-log::viewer.back') }}</a>
        <a class="button secondary" href="{{ route('request-log.viewer.markdown', $entry->id) }}">{{ __('request-log::viewer.markdown') }}</a>
        @if ($telescopeUrl !== null)
            <a class="button secondary" href="{{ $telescopeUrl }}" rel="noopener noreferrer">{{ __('request-log::viewer.telescope') }}</a>
        @endif
    </div>
@endsection

@section('content')
    <section class="panel">
        <h2 class="mono"><strong>{{ $entry->method }}</strong> {{ $entry->path }}
            <span class="badge s{{ intdiv($entry->status, 100) }}">{{ $entry->status }}</span></h2>
        <dl>
            <dt>{{ __('request-log::viewer.reference') }}</dt><dd class="mono">{{ $entry->reference }}</dd>
            <dt>{{ __('request-log::viewer.time') }}</dt><dd class="mono">{{ $entry->created_at->toIso8601String() }}</dd>
            <dt>{{ __('request-log::viewer.reason') }}</dt><dd>{{ $entry->reason->value }}</dd>
            <dt>{{ __('request-log::viewer.url') }}</dt><dd class="mono">{{ $entry->url }}</dd>
            <dt>{{ __('request-log::viewer.route') }}</dt><dd class="mono">{{ $entry->route_uri }} @if ($entry->route_name !== null)<span class="muted">({{ $entry->route_name }})</span>@endif</dd>
            <dt>{{ __('request-log::viewer.action') }}</dt><dd class="mono">@if ($entry->action !== null)<a href="{{ route('request-log.viewer.index', ['action' => $entry->action]) }}">{{ $entry->action }}</a>@endif</dd>
            <dt>{{ __('request-log::viewer.subject') }}</dt><dd class="mono">@if ($entry->subject !== null)<a href="{{ route('request-log.viewer.index', ['subject' => $entry->subject]) }}">{{ $entry->subject }}</a>@endif</dd>
            <dt>{{ __('request-log::viewer.user') }}</dt><dd>@if ($entry->user_id !== null)<a href="{{ route('request-log.viewer.index', ['user' => $entry->user_id]) }}">{{ $labels[$entry->user_id] ?? $entry->user_id }}</a> <span class="muted mono">{{ $entry->user_id }}</span>@endif</dd>
            @if ($entry->tenant_id !== null)<dt>{{ __('request-log::viewer.tenant') }}</dt><dd class="mono">{{ $entry->tenant_id }}</dd>@endif
            <dt>{{ __('request-log::viewer.duration') }}</dt><dd>{{ $entry->duration_ms }} ms · {{ __('request-log::viewer.queries') }} {{ $entry->query_count }} ({{ $entry->query_time_ms }} ms) · {{ __('request-log::viewer.memory') }} {{ \OrsOmes\RequestLog\Support\Display::bytes($entry->memory_peak_kb * 1024) }}</dd>
            <dt>{{ __('request-log::viewer.response_kind') }}</dt><dd>{{ $entry->response_kind }}</dd>
            <dt>{{ __('request-log::viewer.ip') }}</dt><dd class="mono">{{ $entry->ip }}</dd>
            <dt>{{ __('request-log::viewer.user_agent') }}</dt><dd class="mono">{{ $entry->user_agent }}</dd>
            <dt>{{ __('request-log::viewer.host') }}</dt><dd class="mono">{{ $entry->hostname }}</dd>
            <dt>{{ __('request-log::viewer.revision') }}</dt><dd class="mono">{{ $entry->revision }}</dd>
        </dl>
    </section>

    @if ($entry->exception_class !== null)
        <section class="panel">
            <h2 class="s5">{{ __('request-log::viewer.exception') }}</h2>
            <dl>
                <dt>Class</dt><dd class="mono">{{ $entry->exception_class }}</dd>
                <dt>{{ __('request-log::viewer.location') }}</dt><dd class="mono">{{ $entry->exception_location }}</dd>
                <dt>{{ __('request-log::viewer.exception_hash') }}</dt><dd class="mono"><a href="{{ route('request-log.viewer.index', ['exception' => $entry->exception_hash]) }}">{{ $entry->exception_hash }}</a></dd>
            </dl>
            <pre>{{ $entry->exception_message }}</pre>
            @if ($entry->exception_snippet !== null)
                <details open><summary>{{ __('request-log::viewer.snippet') }}</summary><pre>{{ $entry->exception_snippet }}</pre></details>
            @endif
            @if ($entry->exception_trace !== null && $entry->exception_trace !== [])
                <details><summary>{{ __('request-log::viewer.trace') }}</summary><pre>@foreach ($entry->exception_trace as $index => $frame){{ $index }}  {{ \OrsOmes\RequestLog\Support\Markdown::frame($frame) }}
@endforeach</pre></details>
            @endif
        </section>
    @endif

    <section class="panel">
        @include('request-log::partials.block', ['title' => __('request-log::viewer.request_body'), 'content' => $entry->request_body, 'bytes' => $entry->request_bytes, 'truncated' => $entry->request_truncated, 'open' => true])
        @include('request-log::partials.block', ['title' => __('request-log::viewer.response_body'), 'content' => $entry->response_body, 'bytes' => $entry->response_bytes, 'truncated' => $entry->response_truncated, 'open' => true])
        @include('request-log::partials.block', ['title' => __('request-log::viewer.request_headers'), 'content' => $entry->request_headers])
        @include('request-log::partials.block', ['title' => __('request-log::viewer.response_headers'), 'content' => $entry->response_headers])
        @if ($entry->context !== null)
            @include('request-log::partials.block', ['title' => __('request-log::viewer.context'), 'content' => $entry->context])
        @endif
    </section>

    @if ($entry->queries !== null && $entry->queries !== [])
        <section class="panel">
            <h2>{{ __('request-log::viewer.slow_queries') }}</h2>
            @foreach ($entry->queries as $query)
                <p class="muted mono">{{ $query['time_ms'] }} ms · {{ $query['connection'] }} @if ($query['caller'] !== null)· {{ $query['caller'] }}@endif</p>
                <pre>{{ $query['sql'] }}</pre>
            @endforeach
        </section>
    @endif

    @if ($sameException->isNotEmpty())
        <section class="panel">
            <h2>{{ __('request-log::viewer.same_exception') }}</h2>
            @include('request-log::partials.rows', ['rows' => $sameException])
        </section>
    @endif

    @if ($sameReference->isNotEmpty())
        <section class="panel">
            <h2>{{ __('request-log::viewer.same_reference') }}</h2>
            @include('request-log::partials.rows', ['rows' => $sameReference])
        </section>
    @endif
@endsection
