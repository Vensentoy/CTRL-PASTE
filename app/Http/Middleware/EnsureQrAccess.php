<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureQrAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // The gate is ENFORCED by default in every environment (local
        // included — "only by scanning the QR can they access the
        // website"). The ONLY automatic bypass is the test suite, so
        // feature tests can hit /login directly without a QR session.
        // Local/dev opt-out is explicit and off by default: set
        // QR_GATE_BYPASS_LOCAL=true to bypass outside testing.
        if (app()->environment('testing') && config('qr.bypass_in_testing', true)) {
            return $next($request);
        }
        if (config('qr.bypass_local', false)) {
            return $next($request);
        }

        // Coordinators can log in directly to Generate the QR — otherwise no one
        // could bootstrap the gate. Check username before auth (role not known yet).
        // The login FORM itself (GET) always renders — the form was never the
        // gate, and hiding it deadlocked coordinators out of the very login
        // they need to generate the first QR. Enforcement lives on POST
        // below: without a verified session, only coordinator usernames
        // pass; everyone else gets the QR-required block.
        if ($request->isMethod('get') && ($request->routeIs('login') || $request->is('login'))) {
            return $next($request);
        }
        if ($request->isMethod('post') && ($request->routeIs('login') || $request->is('login'))) {
            $username = $request->input('username');
            if ($username) {
                $isCoordinator = \App\Models\User::where('username', $username)->where('role', 'coordinator')->exists();
                if ($isCoordinator) {
                    return $next($request);
                }
            }
        }

        // Explicit gate bypass for QR entry itself is handled by not applying
        // this middleware to qr.enter route.
        if ($request->session()->has('qr_verified_at')) {
            $expiresAt = $request->session()->get('qr_verified_expires_at');
            if ($expiresAt === null || now()->lessThan($expiresAt)) {
                return $next($request);
            }
            $request->session()->forget(['qr_verified_at', 'qr_verified_expires_at', 'qr_token_id']);
        }

        // Block direct access — friendly view, not raw 403.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'QR access required. Please scan the coordinator QR.'], 403);
        }

        return response()->view('auth.qr-required', [], 403);
    }
}
