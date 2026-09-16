<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Confirmed clean during the LoginRequest email->username bug hunt —
 * this file never referenced 'email' at all, it just delegates to
 * LoginRequest::authenticate(). Included here so the next session
 * doesn't have to re-derive that it's already fine.
 *
 * AuditLog wiring (this session): store() now logs a 'Login' row after
 * a successful authenticate() call — data-model.md lists Login as one
 * of the six action_type values, and this is the one place in the app
 * a login actually happens.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $auditLogger->log($request->user(), 'Login');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
