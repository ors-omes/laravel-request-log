<?php

declare(strict_types=1);

namespace App\Http\Controllers;

class LoginController
{
    public function __invoke()
    {
        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => 'eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxIiwic2NvcGUiOiIqIn0.Zm9vYmFyYmF6cXV4',
            'refresh_token' => 'def5020045a1b2c3d4e5f6',
            'expires_in' => 3600,
        ]);
    }
}
