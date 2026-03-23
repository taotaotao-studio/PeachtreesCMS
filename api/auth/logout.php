<?php
/**
 * PeachtreesCMS API - 用户登出
 * POST /api/auth/logout.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 销毁 Session
session_unset();
session_destroy();

success(null, '已退出登录');
