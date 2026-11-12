<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Tests\Fixtures;

use Illuminate\Http\JsonResponse;

final class LoginAction
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'token_type' => 'Bearer',
            'access_token' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U',
            'refresh_token' => 'def50200a1b2c3',
            'expires_in' => 3600,
        ]);
    }
}
