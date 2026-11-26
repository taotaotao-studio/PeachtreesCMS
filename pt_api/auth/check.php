<?php
/**
 * PeachtreesCMS API - Check Login Status
 * GET /api/auth/check.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

$user = getCurrentUser();

// Installer presence — surfaced by the admin UI as a security banner
// (install.php left on a production server is a re-install / overwrite risk)
$installPhpExists = is_file(__DIR__ . '/../install.php');

if ($user) {
    success([
        'loggedIn' => true,
        'user' => $user,
        'installPhpExists' => $installPhpExists
    ]);
} else {
    success([
        'loggedIn' => false,
        'user' => null,
        'installPhpExists' => $installPhpExists
    ]);
}
