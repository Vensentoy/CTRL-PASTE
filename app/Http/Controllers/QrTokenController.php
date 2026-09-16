<?php

namespace App\Http\Controllers;

use App\Models\QrAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrTokenController extends Controller
{
    /**
     * Coordinator-only: generate a short-lived single-use QR token.
     * Decided: 2-min TTL, single-use (configurable via QR_TTL env).
     * Returns JSON with data URL and expiry for coordinator dashboard.
     */
    public function generate(Request $request): JsonResponse
    {
        $ttl = (int) config('qr.ttl', env('QR_TTL', 120));
        $expiresAt = now()->addSeconds($ttl);
        $plain = Str::random(64);

        QrAccessToken::create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
            'max_uses' => 1,
            'used_count' => 0,
            'created_by' => $request->user()?->id,
        ]);

        // Use coordinator's current host (LAN IP) not APP_URL localhost, so phone can reach it.
        $signedPath = URL::temporarySignedRoute('qr.enter', $expiresAt, ['token' => $plain], false);
        $signedUrl = rtrim($request->getSchemeAndHttpHost(), '/') . $signedPath;

        $qrDataUrl = 'data:image/svg+xml;base64,' . base64_encode(
            QrCode::size(300)->generate($signedUrl)
        );

        return response()->json([
            'qr_data_url' => $qrDataUrl,
            'signed_url' => $signedUrl,
            'expires_at' => $expiresAt->toISOString(),
            'ttl_seconds' => $ttl,
        ]);
    }
}
