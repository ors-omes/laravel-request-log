<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class WorkOrderService
{
    public function complete(string $code, int $quantity): void
    {
        $available = 120;

        if ($quantity > $available) {
            throw new RuntimeException("Not enough raw material for work order {$code}: needs {$quantity}, {$available} in stock");
        }
    }
}
