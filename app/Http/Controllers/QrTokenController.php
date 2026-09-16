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
        $expiresAt = now('UTC')->addSeconds($ttl);
        $plain = Str::random(64);

        QrAccessToken::create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
            'max_uses' => 1,
            'used_count' => 0,
            'created_by' => $request->user()?->id,
        ]);

        // Use LAN host, not APP_URL/127.0.0.1, so phone can reach it even if coordinator
        // opened dashboard via localhost. Falls back to configured QR_HOST or detected LAN IP.
        $host = $request->getSchemeAndHttpHost();
        if (config('qr.host')) {
            $host = rtrim(config('qr.host'), '/');
        } elseif (in_array($request->getHost(), ['127.0.0.1', 'localhost', '::1'])) {
            // Try configured APP_URL host if it's a LAN IP, else detect via gethostbyname.
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            if ($appHost && ! in_array($appHost, ['127.0.0.1', 'localhost', '::1'])) {
                $host = $request->getScheme() . '://' . $appHost . ($request->getPort() && ! in_array($request->getPort(), [80, 443]) ? ':' . $request->getPort() : '');
            } else {
                $lanIp = gethostbyname(gethostname());
                if ($lanIp && $lanIp !== gethostname() && filter_var($lanIp, FILTER_VALIDATE_IP)) {
                    $host = $request->getScheme() . '://' . $lanIp . ($request->getPort() && ! in_array($request->getPort(), [80, 443]) ? ':' . $request->getPort() : '');
                }
            }
        }
        $signedPath = URL::temporarySignedRoute('qr.enter', $expiresAt, ['token' => $plain], false);
        $signedUrl = rtrim($host, '/') . $signedPath;

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
