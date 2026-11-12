@php($pretty = \OrsOmes\RequestLog\Support\Display::pretty($content))
<details @if ($open ?? false) open @endif>
    <summary>{{ $title }}
        @isset($bytes)
            <span class="muted">· {{ __('request-log::viewer.original_size') }} {{ \OrsOmes\RequestLog\Support\Display::bytes($bytes) }}@if ($truncated ?? false) · <strong class="s4">{{ __('request-log::viewer.shortened') }}</strong>@endif</span>
        @endisset
    </summary>
    @if ($pretty === null || $pretty === '')
        <p class="muted">{{ __('request-log::viewer.empty') }}</p>
    @else
        <pre>{{ $pretty }}</pre>
    @endif
</details>
