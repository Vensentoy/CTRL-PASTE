<?php

namespace App\Http\Controllers;

use App\Models\QrAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class QrEnterController extends Controller
{
    public function enter(Request $request): Response
    {
        // Local/testing bypass still validates signature but we allow generation
        // to be coordinator-only; entry itself is guest-accessible.

        if (! URL::hasValidSignature($request)) {
            return response()->view('auth.qr-expired', ['reason' => 'QR signature invalid or expired (2-min window). Ask coordinator to Generate again.'], 410);
        }

        $plain = $request->query('token');
        if (! $plain) {
            return response()->view('auth.qr-expired', ['reason' => 'Missing QR token. Ask coordinator to Generate again.'], 410);
        }

        $hash = hash('sha256', $plain);
        $token = QrAccessToken::where('token_hash', $hash)->first();

        if (! $token) {
            return response()->view('auth.qr-expired', ['reason' => 'QR token not found. Ask your coordinator to Generate again.'], 410);
        }

        if ($token->isExpired() || $token->isExhausted()) {
            return response()->view('auth.qr-expired', ['reason' => 'QR expired or already used. Ask your coordinator to Generate again.'], 410);
        }

        $token->markUsed();

        $request->session()->put([
            'qr_verified_at' => now()->toISOString(),
            'qr_verified_expires_at' => $token->expires_at,
            'qr_token_id' => $token->id,
        ]);

        return redirect()->route('login');
    }
}
