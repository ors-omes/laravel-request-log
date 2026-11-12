<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TestRoutes
{
    public static function register(Router $router): void
    {
        $router->get('api/orders', [OrderController::class, 'index']);
        $router->post('api/orders', [OrderController::class, 'store']);
        $router->patch('api/orders/{order}', [OrderController::class, 'update'])->middleware(SubstituteBindings::class);
        $router->get('api/orders/fail', [OrderController::class, 'fail']);
        $router->post('api/login', LoginAction::class);
        $router->post('api/reset-password/{token}', static fn (): JsonResponse => new JsonResponse(['ok' => true]));

        $router->get('api/missing/{id}', static fn (): never => abort(404, 'Nothing here'));
        $router->post('api/validate', static function (Request $request): JsonResponse {
            $request->validate(['name' => ['required', 'string'], 'password' => ['required', 'min:50']]);

            return new JsonResponse(['ok' => true]);
        });
        $router->get('api/slow', static function (): JsonResponse {
            usleep(30_000);

            return new JsonResponse(['ok' => true]);
        });
        $router->get('api/always', static fn (): JsonResponse => new JsonResponse(['ok' => true]))->middleware('request-log:always');
        $router->post('api/off', static fn (): JsonResponse => new JsonResponse(['ok' => true]))->middleware('request-log:off');
        $router->post('api/metadata', static fn (): JsonResponse => new JsonResponse(['secret_value' => 'abc']))->middleware('request-log:metadata');
        $router->post('api/echo', static fn (Request $request): JsonResponse => new JsonResponse($request->input()));
        $router->post('api/html', static fn (): Response => new Response('<form><input name="_token" value="x"></form>', 422, ['Content-Type' => 'text/html']));
        $router->post('api/stream', static fn (): StreamedResponse => new StreamedResponse(static function (): void {
            echo 'chunk';
        }, 200, ['Content-Type' => 'text/csv']));
        $router->post('api/redirect', static fn () => redirect('/done?token=secret-token-value&page=2'));
        $router->post('api/query-error', static function (): JsonResponse {
            DB::select('select * from missing_table where email = ?', ['person@example.com']);

            return new JsonResponse(['ok' => true]);
        });
        $router->post('api/queries', static function (): JsonResponse {
            DB::select('select 1');
            DB::select('select 2');

            return new JsonResponse(['ok' => true]);
        });
    }
}
