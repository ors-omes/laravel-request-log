<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model whose primary (and route) key is the secret itself.
 */
final class Invitation extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'token';

    protected $keyType = 'string';

    public function resolveRouteBinding($value, $field = null): self
    {
        $invitation = new self();
        $invitation->setAttribute('token', $value);

        return $invitation;
    }
}
