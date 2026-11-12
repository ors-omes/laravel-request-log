---
description: The 10-character reference code — where it appears (Context, header, error responses, Telescope) and how to look it up.
---

# Reference codes

Every considered request gets a 10-character Crockford base32 code, for example `7KQ2M9XHTA` (about 50 bits). It shows up in four places.

## In the Laravel Context

The code is added to the [Laravel Context](https://laravel.com/docs/context) under `request_reference`. As a result:

- every log record written during the request carries it;
- queued jobs dispatched by the request carry it too, so their logs can be traced back to the request.

Set `reference.context_key` to `null` to disable this.

## In the response header

Recorded requests get an `X-Request-Reference` header (`reference.header`; empty disables it). Requests that are not recorded do not get the header, so a header value always resolves to a row.

## In error responses

Put the code in your problem responses so users can quote it to support:

```php
use OrsOmes\RequestLog\RequestLog;

// RFC 9457 problem details renderer
$problem['reference_code'] = RequestLog::referenceFor($status);
```

`referenceFor()` returns `null` when the request will **not** be recorded with that status, for example an ignored status or a `request-log:off` route. A code shown to a user therefore always exists in the log. `RequestLog::reference()` returns the code regardless of that check.

## Looking a code up

The viewer's reference filter accepts pasted values: `[7kq2-m9xh-ta]` finds `7KQ2M9XHTA`. Punctuation is ignored, case is ignored, `I`/`L` read as `1` and `O` reads as `0`.

## In Telescope

When Telescope is installed, every entry of the request is tagged `ref:<code>`. See [Telescope](./telescope).
