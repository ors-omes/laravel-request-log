<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Demo only: authenticates the user id sent in X-Demo-User.
 */
class DemoLogin
{
    public function handle(Request $request, Closure $next)
    {
        if ($id = $request->header('X-Demo-User')) {
            Auth::onceUsingId($id);
        }

        return $next($request);
    }
}
