<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class OrderController
{
    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => ['id' => 'ord_1', 'note' => $request->input('note')]], 201);
    }

    public function update(Order $order): JsonResponse
    {
        return new JsonResponse(['data' => ['id' => $order->getKey()]]);
    }

    public function fail(): JsonResponse
    {
        throw new RuntimeException('Order service exploded');
    }
}
