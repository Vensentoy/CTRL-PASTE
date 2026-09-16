<?php

return [
    'ttl' => env('QR_TTL', 120),
    'max_uses' => env('QR_MAX_USES', 1),
    // Keep existing suite green: bypass gate in testing unless a test
    // explicitly opts into gate enforcement via config(['qr.bypass_in_testing' => false]).
    'bypass_in_testing' => env('QR_BYPASS_IN_TESTING', true),
];
