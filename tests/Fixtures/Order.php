<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function resolveRouteBinding($value, $field = null): self
    {
        $order = new self();
        $order->setAttribute('id', $value);

        return $order;
    }
}
