<?php
/**
 * PeachtreesCMS API - CORS Configuration
 */

// Allowed origins (should be restricted to specific domains in production)
$allowedOrigins = [
    'http://localhost:5173',  // Vite dev server
    'http://localhost',       // Local production
];

// Allow custom origins from environment variable (comma-separated)
$envOrigins = getenv('CORS_ALLOWED_ORIGINS');
if ($envOrigins) {
    $envOriginsArray = array_map('trim', explode(',', $envOrigins));
    $allowedOrigins = array_merge($allowedOrigins, $envOriginsArray);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Caching proxies must not serve one origin's CORS headers to another.
header('Vary: Origin');

if (in_array($origin, $allowedOrigins, strict: true)) {
    header("Access-Control-Allow-Origin: $origin");
} elseif ($origin !== '' && preg_match('#^https?://localhost(:\d+)?$#', $origin)) {
    // Development: reflect the actual localhost origin so credentials work
    header("Access-Control-Allow-Origin: $origin");
}
// Unknown origins: no ACAO header at all → browser blocks the request

// Allowed HTTP methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allowed request headers
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

// Allow credentials (cookies)
header("Access-Control-Allow-Credentials: true");

// Preflight request cache time (seconds)
header("Access-Control-Max-Age: 86400");

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
