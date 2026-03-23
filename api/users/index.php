<?php
/**
 * PeachtreesCMS API - 获取用户列表
 * GET /api/users/index.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// 验证管理员权限
requireAdmin();

try {
    $pdo = getDB();
    
    $sql = "SELECT id, username, email, created_at, last_login_at FROM users ORDER BY id ASC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
    
    success($users);
    
} catch (PDOException $e) {
    serverError('获取用户列表失败: ' . $e->getMessage());
}
