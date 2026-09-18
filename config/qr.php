<?php

return [
    'ttl' => env('QR_TTL', 120),
    'max_uses' => env('QR_MAX_USES', 1),
    'host' => env('QR_HOST', null),
    // Keep existing suite green: bypass gate in testing unless a test
    // explicitly opts into gate enforcement via config(['qr.bypass_in_testing' => false]).
    'bypass_in_testing' => env('QR_BYPASS_IN_TESTING', true),
    // Explicit opt-out for local/dev only — defaults to false so the QR
    // gate is enforced unless someone deliberately bypasses it.
    'bypass_local' => env('QR_GATE_BYPASS_LOCAL', false),
];
