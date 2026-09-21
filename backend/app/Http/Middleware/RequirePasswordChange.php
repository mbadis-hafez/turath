<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users flagged must_change_password may only read their own profile, change
 * their password, or log out until the password is changed.
 */
class RequirePasswordChange
{
    private const ALLOWED = [
        ['GET', 'api/v1/auth/user'],
        ['POST', 'api/v1/auth/password'],
        ['POST', 'api/v1/auth/logout'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password) {
            $allowed = collect(self::ALLOWED)->contains(
                fn (array $route) => $request->is($route[1]) && $request->method() === $route[0],
            );

            abort_unless($allowed, 423, 'Password change required.');
        }

        return $next($request);
    }
}
