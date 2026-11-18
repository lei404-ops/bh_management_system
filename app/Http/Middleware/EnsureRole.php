<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access control.
 *   ->middleware('role')                  any logged-in user
 *   ->middleware('role:admin,landlord')   only these roles
 * Pages redirect to the login form; /api/* endpoints answer with JSON 401/403.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $isApi = $request->is('api/*');

        if (!$user || !$user->is_active) {
            return $isApi
                ? ApiResponse::error('Please log in again.', 401)
                : redirect()->route('login');
        }

        if ($roles && !in_array($user->role, $roles, true)) {
            return $isApi
                ? ApiResponse::error('You do not have permission to do that.', 403)
                : response('403 - You do not have permission to view this page.', 403);
        }

        return $next($request);
    }
}
