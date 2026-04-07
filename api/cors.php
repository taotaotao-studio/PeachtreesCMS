<?php
/**
 * PeachtreesCMS API - CORS Configuration
 */

// Allowed origins (should be restricted to specific domains in production)
$allowedOrigins = [
    'http://localhost:5173',  // Vite dev server
    'http://localhost:3000',  // Alternate port
    'http://localhost',       // Local production
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, strict: true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Allow all origins in development, should comment out in production
    header("Access-Control-Allow-Origin: *");
}

// Allowed HTTP methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allowed request headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Allow credentials (cookies)
header("Access-Control-Allow-Credentials: true");

// Preflight request cache time (seconds)
header("Access-Control-Max-Age: 86400");

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
