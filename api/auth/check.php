<?php
/**
 * PeachtreesCMS API - 检查登录状态
 * GET /api/auth/check.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

$user = getCurrentUser();

if ($user) {
    success([
        'loggedIn' => true,
        'user' => $user
    ]);
} else {
    success([
        'loggedIn' => false,
        'user' => null
    ]);
}
