<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Bound by key ({order}) or by another column ({order:token}), in which
     * case the key is a fixed id.
     */
    public function resolveRouteBinding($value, $field = null): self
    {
        $order = new self();
        $order->setAttribute('id', $field === null ? $value : 'ord_1');

        if (is_string($field)) {
            $order->setAttribute($field, $value);
        }

        return $order;
    }
}
