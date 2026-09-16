<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureQrAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass for dev/testing so suite doesn't lock out, but allow real
        // gate test on LAN by setting APP_ENV=production or QR_GATE_ENFORCE=true.
        // Local still bypasses unless QR_GATE_ENFORCE is set to force gate even in local.
        if (env('QR_GATE_ENFORCE', false)) {
            // Force gate even in local/testing when user wants to demo expiry.
        } elseif (app()->environment('local') || env('QR_GATE_BYPASS', false)) {
            return $next($request);
        }
        if (app()->environment('testing') && config('qr.bypass_in_testing', true)) {
            return $next($request);
        }

        // Coordinators can log in directly to Generate the QR — otherwise no one
        // could bootstrap the gate. Check username before auth (role not known yet).
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
