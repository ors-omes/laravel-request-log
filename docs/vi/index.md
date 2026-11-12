---
title: Hướng dẫn nhanh
description: Hướng dẫn nhanh bằng tiếng Việt cho Laravel Request Log.
---

# Hướng dẫn nhanh (Tiếng Việt)

Laravel Request Log là package ghi nhật ký request cho Laravel 12/13. Mặc định nó ghi:

- **mọi POST, PUT, PATCH, DELETE**;
- **mọi request trả 4xx/5xx, kể cả GET**.

Mỗi request được ghi thành một dòng trong bảng `request_logs`, các cột quan trọng đều có index.

## Mỗi dòng có gì

- **Payload và response:** đã che dữ liệu nhạy cảm. Nằm trong giới hạn (512 KB request / 256 KB response) thì lưu đủ; vượt thì rút gọn có đánh dấu `…[+N items]`, `…[+N chars]` và vẫn là JSON hợp lệ, kèm kích thước gốc.
- **Exception:** class, message (lỗi SQL không kèm giá trị bind), vị trí trong code, đoạn mã nguồn quanh dòng lỗi, trace và mã nhóm lỗi để xem mọi lần lỗi giống nhau.
- **Hiệu năng:** thời gian xử lý, bộ nhớ, số truy vấn, các truy vấn chậm kèm `file:dòng` gọi tới.
- **Ai/cái gì:** người dùng, tenant, đối tượng bị tác động (vd. `Order:01J…`), route, action, IP, revision.

## Cài đặt

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ors-omes/laravel-request-log" }
  ]
}
```

```bash
composer require ors-omes/laravel-request-log:^0.1@beta
php artisan migrate
```

Nên dùng connection riêng cho log:

```dotenv
REQUEST_LOG_DB_CONNECTION=pgsql_log
```

## Mã tham chiếu

Mỗi request có mã 10 ký tự, vd. `7KQ2M9XHTA`. Mã này xuất hiện ở bốn chỗ:

- Laravel Context, nên mọi dòng log và queued job đều mang theo;
- header `X-Request-Reference`;
- response lỗi, qua `RequestLog::referenceFor($status)`;
- tag `ref:<mã>` trong Telescope.

```php
$problem['reference_code'] = RequestLog::referenceFor($status);
```

## Xem log

Viewer ở `/request-logs`, mở cho môi trường `local` hoặc cho người qua gate:

```php
Gate::define('viewRequestLogs', fn (User $user) => $user->can('request-logs.view'));
```

Xem thử [bản demo tiếng Việt](https://ors-omes.github.io/laravel-request-log/demo/vi/index.html). API JSON tắt sẵn, bật bằng `REQUEST_LOG_API_ENABLED=true`.

## Theo route

```php
Route::get('reports/{report}', ShowReport::class)->middleware('request-log:always');   // ghi cả GET 200
Route::post('heartbeat', Heartbeat::class)->middleware('request-log:off');             // không ghi
Route::post('login', Login::class)->middleware('request-log:metadata');                // ghi nhưng bỏ body
```

## Bảo mật

- Che theo khoá ở mọi độ sâu, không phân biệt hoa thường, kể cả giá trị rỗng. Mặc định gồm `*password*`, `*token*`, `*secret*`, `*_pin`, `otp`, `cvv`, `*card_number*`, `signature`…
- Che JWT, `Bearer …`, `Basic …` dù nằm dưới khoá vô hại.
- Áp dụng cho header, query string, tham số route trong path và response.
- Không lưu nội dung XML/CSV/text/HTML hay nội dung file upload.
- Lưu giữ 30 ngày (lỗi ≥ 500 giữ 90 ngày). Trong giai đoạn beta, chạy `php artisan request-log:audit --since=24h` để kiểm tra dữ liệu thật còn lọt gì không.

Tài liệu đầy đủ bằng tiếng Anh: [Introduction](/guide/introduction).
