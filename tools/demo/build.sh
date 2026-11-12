#!/usr/bin/env bash
# Rebuild the static viewer demo (docs/public/demo) and the screenshots
# (docs/public/screenshots) from a throwaway Laravel app with sample data.
#
# usage: tools/demo/build.sh [work-dir]
# needs: php, composer, python3, google-chrome (or chromium) on PATH
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="${1:-$(mktemp -d)}"
APP="$WORK/app"
PORT="${DEMO_PORT:-8931}"
URL="http://127.0.0.1:$PORT"
CHROME="$(command -v google-chrome || command -v chromium || command -v chromium-browser)"

if [ ! -d "$APP" ]; then
  composer create-project laravel/laravel "$APP" --no-interaction --prefer-dist --quiet
fi

cd "$APP"
composer config repositories.request-log "{\"type\":\"path\",\"url\":\"$REPO\",\"options\":{\"symlink\":true}}"
composer require ors-omes/laravel-request-log:@dev --no-interaction --quiet
cp -r "$REPO/tools/demo/fixtures/." "$APP/"
grep -q "api: __DIR__" bootstrap/app.php || sed -i "s|        web: __DIR__.'/../routes/web.php',|        web: __DIR__.'/../routes/web.php',\n        api: __DIR__.'/../routes/api.php',|" bootstrap/app.php

set_env() { grep -q "^$1=" .env && sed -i "s|^$1=.*|$1=$2|" .env || echo "$1=$2" >> .env; }
set_env APP_ENV local
set_env APP_DEBUG false
set_env APP_LOCALE en
set_env REQUEST_LOG_SLOW_MS 250
set_env REQUEST_LOG_REVISION 3f9c2a7e1b4d8c6f0a5e2d9b7c1f4a8e6d3b0c92

php artisan config:clear --quiet
php artisan migrate:fresh --force --quiet
php artisan tinker --execute '
    \App\Models\User::query()->forceCreate(["name" => "Alice Carter", "email" => "alice.carter@example.com", "password" => bcrypt(\Illuminate\Support\Str::random(32))]);
    \App\Models\User::query()->forceCreate(["name" => "Ben Turner", "email" => "ben.turner@example.com", "password" => bcrypt(\Illuminate\Support\Str::random(32))]);
'

php artisan serve --port="$PORT" --no-reload >/dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 30); do curl -s -o /dev/null "$URL/up" && break; sleep 0.5; done

python3 -I -c "
import json
rows = [{'line': i, 'component': f'RM-{i:04d}', 'description': 'Cold-rolled SPCC steel sheet, 1.2 mm thick, 1219 x 2438 mm, oiled, ' * 3, 'qty': round(i * 1.25, 2), 'unit': 'kg'} for i in range(1, 2601)]
json.dump({'product_code': 'ARM-BRACKET-01', 'revision': 'B', 'rows': rows}, open('$WORK/bom.json', 'w'))
"
printf 'Code,Quantity\nRM-0001,10\n' > "$WORK/plan.csv"

J=(-H 'Content-Type: application/json' -H 'Accept: application/json')
send() { curl -s -o /dev/null "$@"; sleep 0.3; }
API="$URL/api"
send "${J[@]}" -X POST "$API/auth/login" -d '{"username":"alice.carter","password":"S3cret!pass","remember":true}'
send "${J[@]}" -H 'X-Demo-User: 1' -H 'Authorization: Bearer eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl' -X POST "$API/work-orders" -d '{"product_code":"ARM-BRACKET-01","quantity":500,"line":"L2","due_date":"2026-11-20","note":"Deliver before shift C"}'
send "${J[@]}" -H 'X-Demo-User: 1' -X PATCH "$API/work-orders/12" -d '{"status":"released"}'
send "${J[@]}" -H 'X-Demo-User: 2' -X POST "$API/products" -d '{"code":"ARM-BRACKET-LONG-01","unit":"box"}'
send "${J[@]}" -H 'X-Demo-User: 2' -X POST "$API/work-orders/12/complete" -d '{"quantity":500,"operator_pin":"4821"}'
send "${J[@]}" -H 'X-Demo-User: 1' -X POST "$API/imports/bom" --data-binary @"$WORK/bom.json"
send -H 'Accept: application/json' -H 'X-Demo-User: 2' -X POST "$API/attachments" -F "file=@$WORK/plan.csv;type=text/csv" -F 'work_order_id=12'
send "${J[@]}" -H 'X-Demo-User: 1' "$API/reports/output?shift=A&date=2026-11-12"
send "${J[@]}" -H 'X-Demo-User: 2' "$API/work-orders/999"
send "${J[@]}" -H 'X-Demo-User: 1' -X DELETE "$API/work-orders/12"
send "${J[@]}" -H 'X-Demo-User: 2' -X POST "$API/work-orders/13/complete" -d '{"quantity":800}'
send "${J[@]}" -X POST "$API/password/reset/7f3a9c2e5b1d4f60?email=alice.carter@example.com&signature=9b2f1e7c" -d '{"password":"N3w-S3cret!","password_confirmation":"N3w-S3cret!"}'

rm -rf "$REPO/docs/public/demo" "$WORK/clean"
python3 -I "$REPO/tools/demo/export.py" "$URL" "$REPO/docs/public/demo" "$(hostname)"
python3 -I "$REPO/tools/demo/export.py" "$URL" "$WORK/clean" "$(hostname)" --no-banner

id_of() { php artisan tinker --execute "echo \OrsOmes\RequestLog\Models\LoggedRequest::query()->where('path', 'like', '$1')->where('status', $2)->value('id');"; }
shot() {
  "$CHROME" --headless=new --disable-gpu --hide-scrollbars --no-first-run --user-data-dir="$WORK/chrome" ${4:-} \
    --window-size="$2" --screenshot="$REPO/docs/public/screenshots/$1.png" "file://$WORK/clean/$3" >/dev/null 2>&1
}
mkdir -p "$REPO/docs/public/screenshots"
shot list 1440,980 index.html
shot list-dark 1440,980 index.html --force-dark-mode
shot error-500 1440,1500 "entry-$(id_of '/api/work-orders/12/complete' 500).html"
shot validation-422 1440,1240 "entry-$(id_of '/api/products' 422).html"
shot payload-shortened 1440,900 "entry-$(id_of '/api/imports/bom' 201).html"
shot login-redacted 1440,900 "entry-$(id_of '/api/auth/login' 200).html"
shot reset-password 1440,860 "entry-$(id_of '/api/password/reset/%' 200).html"

echo "demo and screenshots rebuilt (work dir: $WORK)"
