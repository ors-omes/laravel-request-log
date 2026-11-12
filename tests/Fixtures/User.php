<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    protected $guarded = [];
}
