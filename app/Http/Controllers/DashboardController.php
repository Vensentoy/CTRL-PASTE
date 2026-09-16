<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Fixes a real bug found this session: AuthenticatedSessionController::store()
 * redirects to route('dashboard') after every successful login (this is
 * Breeze's default and was never changed), but no route named 'dashboard'
 * existed anywhere in routes/web.php — only commented-out placeholders
 * named 'student.dashboard' / 'coordinator.dashboard'. A student or
 * coordinator could authenticate correctly and still hit a hard
 * RouteNotFoundException on the very next request.
 *
 * This controller is registered at the top level (outside both role-scoped
 * route groups) as the literal 'dashboard' route, and just forwards to the
 * correct role-specific dashboard. Keeping the 'dashboard' name intact
 * means nothing else that references it (redirect()->intended() included)
 * needs to change.
 */
class DashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        $user = request()->user();

        if ($user->isCoordinator()) {
            return redirect()->route('coordinator.dashboard');
        }

        // Default to the student dashboard — isStudent() is the expected
        // case, and there is no third role in this system (roles-and-
        // permissions.md), so this is a safe fallback rather than a
        // silent misroute.
        return redirect()->route('student.dashboard');
    }
}
