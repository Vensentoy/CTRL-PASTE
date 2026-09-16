<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-14 — backend-enforced isolation, first layer.
 *
 * This only checks that the logged-in user's role matches what the route
 * requires (e.g. a student can never hit a coordinator-only route, even
 * by typing the URL directly). This is necessary but NOT sufficient on
 * its own — it does not yet check OWNERSHIP (e.g. that a coordinator
 * hitting /students/{id} actually owns that student). Ownership checks
 * belong in Policies (see app/Policies), applied per-controller action,
 * once the Student/Coordinator controllers exist.
 *
 * Usage in routes/web.php:
 *   Route::middleware(['auth', 'role:coordinator'])->group(...);
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(
            $request->user() && $request->user()->role === $role,
            403,
            'You do not have access to this section.'
        );

        return $next($request);
    }
}
