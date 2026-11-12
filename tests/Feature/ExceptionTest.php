<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use OrsOmes\RequestLog\Models\LoggedRequest;
use OrsOmes\RequestLog\Recording\Reason;

it('stores the rendered body and the exception with snippet and trimmed trace for 5xx', function (): void {
    Config::set('request-log.exceptions.project_paths', ['tests']);

    $this->getJson('/api/orders/fail')->assertStatus(500);

    $log = latestRequestLog();
    $trace = $log->exception_trace;

    expect($log->reason)->toBe(Reason::Error)
        ->and($log->exception_class)->toBe(RuntimeException::class)
        ->and($log->exception_message)->toBe('Order service exploded')
        ->and($log->exception_hash)->toBe(md5(RuntimeException::class.'|'.$log->exception_location))
        ->and($log->exception_snippet)->toContain("throw new RuntimeException('Order service exploded');")
        ->and($log->exception_snippet)->toMatch('/^>\s+\d+\| .*throw new RuntimeException/m')
        ->and($log->response_kind)->toBe('json')
        ->and($trace)->toBeArray()
        ->and(end($trace)['function'])->toMatch('/framework frames omitted/');

    foreach ($trace as $frame) {
        expect(array_keys($frame))->toBe(['file', 'line', 'class', 'function', 'type']);
    }
});

it('keeps validation errors but no debug data for 4xx', function (): void {
    $this->postJson('/api/validate', ['password' => 'short'])->assertStatus(422);

    $log = latestRequestLog();
    $response = json_decode((string) $log->response_body, true);

    expect($log->exception_class)->toBe('Illuminate\Validation\ValidationException')
        ->and($log->exception_snippet)->toBeNull()
        ->and($log->exception_trace)->toBeNull()
        ->and($response['errors'])->toHaveKeys(['name', 'password'])
        ->and(json_decode((string) $log->request_body, true))->toBe(['password' => '[REDACTED]']);
});

it('stores query exceptions without bound values', function (): void {
    $this->postJson('/api/query-error')->assertStatus(500);

    $log = latestRequestLog();

    expect($log->exception_class)->toBe('Illuminate\Database\QueryException')
        ->and($log->exception_message)->toContain('select * from missing_table where email = ?')
        ->and($log->exception_message)->not->toContain('person@example.com');
});

it('keeps database error messages without the values they quote', function (string $driverMessage, string $expected): void {
    Route::post('api/driver-error', static function () use ($driverMessage): never {
        throw new QueryException('pgsql', 'select * from orders where id = ?', ['private-value'], new PDOException($driverMessage));
    });

    $this->postJson('/api/driver-error')->assertStatus(500);

    expect(latestRequestLog()->exception_message)->toBe($expected.' (Connection: pgsql, SQL: select * from orders where id = ?)');
})->with([
    'postgres cast' => [
        "SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type integer: \"private-value\"\nCONTEXT:  unnamed portal parameter \$1 = 'private-value'",
        'SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type integer: "[REDACTED]"',
    ],
    'postgres range' => [
        'SQLSTATE[22003]: Numeric value out of range: 7 ERROR:  value "99999999999" is out of range for type integer',
        'SQLSTATE[22003]: Numeric value out of range: 7 ERROR:  value "[REDACTED]" is out of range for type integer',
    ],
    'postgres unique' => [
        "SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint \"users_email_unique\"\nDETAIL:  Key (email)=(a@b.test) already exists.",
        'SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "users_email_unique"',
    ],
    'postgres identifier' => [
        'SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation "orders" does not exist',
        'SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation "orders" does not exist',
    ],
    'mysql value with a quote' => [
        "SQLSTATE[HY000]: General error: 1366 Incorrect integer value: 'pa'ss' for column 'qty' at row 1",
        "SQLSTATE[HY000]: General error: 1366 Incorrect integer value: '[REDACTED]' for column 'qty' at row 1",
    ],
    'mysql duplicate' => [
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'a@b.test' for key 'users.users_email_unique'",
        "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '[REDACTED]' for key 'users.users_email_unique'",
    ],
    'sql server duplicate' => [
        "SQLSTATE[23000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Violation of UNIQUE KEY constraint 'users_email_unique'. Cannot insert duplicate key in object 'dbo.users'. The duplicate key value is (a@b.test).",
        "SQLSTATE[23000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Violation of UNIQUE KEY constraint 'users_email_unique'. Cannot insert duplicate key in object 'dbo.users'. The duplicate key value is ([REDACTED]).",
    ],
    'sqlite' => [
        'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.email',
        'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.email',
    ],
]);

it('scrubs PDO exceptions thrown outside the query builder', function (): void {
    Route::post('api/pdo-error', static function (): never {
        throw new PDOException('SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type uuid: "private-value"');
    });

    $this->postJson('/api/pdo-error')->assertStatus(500);

    expect(latestRequestLog()->exception_message)->toBe('SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type uuid: "[REDACTED]"');
});

it('stores the full query message with bound values when enabled', function (): void {
    Config::set('request-log.exceptions.query_bindings', true);

    $this->postJson('/api/query-error')->assertStatus(500);

    expect(latestRequestLog()->exception_message)->toContain('person@example.com');
});

it('keeps values out of real PostgreSQL error messages', function (): void {
    Route::post('api/pgsql-error', static fn (): array => DB::select('select ?::integer as n', ['private789']));

    $this->postJson('/api/pgsql-error')->assertStatus(500);

    expect(latestRequestLog()->exception_message)
        ->toStartWith('SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type integer: "[REDACTED]"')
        ->toEndWith('SQL: select ?::integer as n)')
        ->not->toContain('private789');
})->skip(getenv('REQUEST_LOG_TEST_DB') !== 'pgsql', 'PostgreSQL only');

it('groups the same failure under one hash', function (): void {
    $this->getJson('/api/orders/fail')->assertStatus(500);
    $this->getJson('/api/orders/fail')->assertStatus(500);

    expect(LoggedRequest::query()->distinct()->pluck('exception_hash'))->toHaveCount(1);
});
