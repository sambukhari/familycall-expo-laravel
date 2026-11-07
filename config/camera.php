<?php

return [
    // Coturn REST authentication. The shared secret never leaves the server.
    'turn_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('CAMERA_TURN_URLS', ''))))),
    'turn_secret' => env('CAMERA_TURN_SECRET'),
    'session_seconds' => 900,
    'viewer_lease_seconds' => 30,
    'online_seconds' => 20,
    'unlock_seconds' => 1800,
];
