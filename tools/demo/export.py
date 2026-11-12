"""Crawl the viewer of a running demo app and write a static, fully linked copy.

usage: python3 -I export.py <base-url> <out-dir> <hostname> [--no-banner]

Every link the viewer renders (records, Markdown exports, filter links) is
fetched and rewritten to a relative file, so the copy can be browsed on
GitHub Pages. Machine-specific values are replaced with neutral ones.
"""
import hashlib
import html
import os
import re
import sys
import urllib.parse
import urllib.request

BASE, OUT, HOSTNAME = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
WITH_BANNER = '--no-banner' not in sys.argv
ROOT = BASE + '/request-logs'
LINK = re.compile(r'(href|action)="(' + re.escape(ROOT) + r'[^"]*)"')
HOST = urllib.parse.urlsplit(BASE).netloc

BANNER = (
    '<div style="background:#fff7d6;color:#5c4400;border-bottom:1px solid #e9d68a;padding:8px 20px;'
    'font:13px/1.4 system-ui,sans-serif;display:flex;gap:16px;flex-wrap:wrap;align-items:center">'
    '<strong>Demo</strong><span>Static copy with sample data. Records, Markdown exports and pre-built '
    'filter links work; the filter form needs a running app.</span>'
    '<a href="../index.html" style="color:#2457c5">Back to the documentation</a></div>'
)


def local_name(url: str) -> str:
    parsed = urllib.parse.urlsplit(html.unescape(url))
    path = parsed.path[len('/request-logs'):].strip('/')
    if path == '':
        if not parsed.query:
            return 'index.html'
        query = urllib.parse.urlencode(sorted(urllib.parse.parse_qsl(parsed.query)))
        return 'filter-' + hashlib.sha1(query.encode()).hexdigest()[:10] + '.html'
    if path.endswith('/markdown'):
        return 'entry-' + path.split('/')[0] + '.md.txt'
    return 'entry-' + path + '.html'


def clean(text: str) -> str:
    for old, new in ((HOST, 'app.example.com'), ('127.0.0.1', '10.20.0.15'), (HOSTNAME, 'demo-app-01')):
        text = text.replace(old, new)
    return text


os.makedirs(OUT, exist_ok=True)
queue, seen = [ROOT], set()
while queue:
    url = html.unescape(queue.pop())
    name = local_name(url)
    if name in seen:
        continue
    seen.add(name)
    with urllib.request.urlopen(url) as response:
        body = response.read().decode('utf-8')

    if name.endswith('.html'):
        queue.extend(target for _, target in LINK.findall(body))
        body = LINK.sub(lambda m: f'{m.group(1)}="{local_name(m.group(2))}"', body)
        if WITH_BANNER:
            body = body.replace('<body>', '<body>\n' + BANNER, 1)

    with open(os.path.join(OUT, name), 'w', encoding='utf-8') as handle:
        handle.write(clean(body))

print(f'exported {len(seen)} pages to {OUT}')
