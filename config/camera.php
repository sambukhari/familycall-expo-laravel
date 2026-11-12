<?php

return [
    // Password unlock is the default. Owners can opt into the existing authenticator flow.
    'require_authenticator' => env('CAMERA_REQUIRE_AUTHENTICATOR', false),
    // Matches the STUN discovery service used by FamilyCall calls.
    'stun_urls' => ['stun:stun.l.google.com:19302'],
    // Coturn REST authentication. The shared secret never leaves the server.
    'turn_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('CAMERA_TURN_URLS', ''))))),
    'turn_secret' => env('CAMERA_TURN_SECRET'),
    'session_seconds' => 900,
    'viewer_lease_seconds' => 30,
    'online_seconds' => 20,
    'unlock_seconds' => 1800,
];
