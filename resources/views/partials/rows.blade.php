<table>
    <thead>
    <tr>
        <th>{{ __('request-log::viewer.time') }}</th>
        <th>{{ __('request-log::viewer.reference') }}</th>
        <th>{{ __('request-log::viewer.request') }}</th>
        <th>{{ __('request-log::viewer.status') }}</th>
        <th>{{ __('request-log::viewer.action') }}</th>
        <th>{{ __('request-log::viewer.user') }}</th>
        <th class="num">{{ __('request-log::viewer.duration') }}</th>
        <th class="num">{{ __('request-log::viewer.queries') }}</th>
        <th>{{ __('request-log::viewer.exception') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td class="nowrap mono">{{ $row->created_at->format('Y-m-d H:i:s') }}</td>
            <td class="mono"><a href="{{ route('request-log.viewer.show', $row->id) }}">{{ $row->reference }}</a></td>
            <td class="trunc mono" title="{{ $row->path }}"><strong>{{ $row->method }}</strong> {{ $row->path }}</td>
            <td><span class="badge s{{ intdiv($row->status, 100) }}">{{ $row->status }}</span> <span class="muted">{{ $row->reason->value }}</span></td>
            <td class="trunc mono" title="{{ $row->action }}">{{ $row->action === null ? '' : \OrsOmes\RequestLog\Support\RouteInfo::shortAction($row->action) }}</td>
            <td class="trunc">@if ($row->user_id !== null)<a href="{{ route('request-log.viewer.index', ['user' => $row->user_id]) }}">{{ $labels[$row->user_id] ?? $row->user_id }}</a>@endif</td>
            <td class="num">{{ $row->duration_ms }} ms</td>
            <td class="num">{{ $row->query_count }}</td>
            <td class="trunc mono" title="{{ $row->exception_class }}">{{ $row->exception_class === null ? '' : class_basename($row->exception_class) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
