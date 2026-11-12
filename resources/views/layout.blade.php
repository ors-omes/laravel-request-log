<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', __('request-log::viewer.title'))</title>
<style nonce="{{ $nonce }}">
:root{--bg:#f7f7f8;--panel:#fff;--text:#1d1f23;--muted:#6b7079;--line:#e3e5e8;--accent:#2457c5;--ok:#1f7a3a;--warn:#9a6200;--err:#b42318;--code:#f2f3f5}
@media (prefers-color-scheme:dark){:root{--bg:#141518;--panel:#1c1e22;--text:#e6e7ea;--muted:#9a9fa8;--line:#2d3036;--accent:#7aa2ff;--ok:#5bc27c;--warn:#e0a640;--err:#f07167;--code:#23262b}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
header{display:flex;align-items:center;gap:16px;padding:12px 20px;border-bottom:1px solid var(--line);background:var(--panel)}
header h1{font-size:16px;margin:0}main{padding:16px 20px;max-width:1500px;margin:0 auto}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:12px 14px;margin-bottom:14px}
.filters{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:8px 12px;align-items:end}
label{display:block;font-size:12px;color:var(--muted);margin-bottom:2px}
input,select{width:100%;padding:5px 7px;border:1px solid var(--line);border-radius:5px;background:var(--panel);color:var(--text);font:inherit}
button,.button{display:inline-block;padding:6px 12px;border:1px solid var(--accent);border-radius:5px;background:var(--accent);color:#fff;font:inherit;cursor:pointer}
.button.secondary{background:transparent;color:var(--accent)}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:6px 8px;border-bottom:1px solid var(--line);vertical-align:top}
th{font-size:12px;color:var(--muted);font-weight:600}td.num{text-align:right;white-space:nowrap}
.mono,code,pre{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px}
pre{background:var(--code);padding:10px;border-radius:6px;overflow:auto;max-height:640px;white-space:pre-wrap;word-break:break-word;margin:6px 0 0}
.badge{display:inline-block;padding:1px 6px;border-radius:10px;font-size:12px;border:1px solid var(--line)}
.s2,.s3{color:var(--ok)}.s4{color:var(--warn)}.s5{color:var(--err)}.muted{color:var(--muted)}
.errors{color:var(--err)}.nowrap{white-space:nowrap}.trunc{max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
dl{display:grid;grid-template-columns:max-content 1fr;gap:4px 16px;margin:0}dt{color:var(--muted)}dd{margin:0;word-break:break-all}
details{margin-bottom:10px}summary{cursor:pointer;font-weight:600}.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
</style>
</head>
<body>
<header>
    <h1><a href="{{ route('request-log.viewer.index') }}">{{ __('request-log::viewer.title') }}</a></h1>
    @yield('toolbar')
</header>
<main>
@yield('content')
</main>
</body>
</html>
