<?php
/**
 * PeachtreesCMS API - 认证中间件
 * 使用 Session 进行认证
 */

require_once __DIR__ . '/config.php';

/**
 * 检查用户是否已登录
 * @return array|null 返回用户信息或 null
 */
function getCurrentUser(): ?array {
    if (!isset($_SESSION['uid']) || !isset($_SESSION['user'])) {
        return null;
    }
    return [
        'id' => $_SESSION['uid'],
        'username' => $_SESSION['user']
    ];
}

/**
 * 要求用户登录
 * 如果未登录则返回 401 错误
 * @return array 用户信息
 */
function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        require_once __DIR__ . '/response.php';
        unauthorized('请先登录');
    }
    return $user;
}

/**
 * 检查是否为管理员 (uid = 1)
 * @return bool
 */
function isAdmin(): bool {
    return isset($_SESSION['uid']) && $_SESSION['uid'] == 1;
}

/**
 * 要求管理员权限
 * 如果不是管理员则返回 403 错误
 */
function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        require_once __DIR__ . '/response.php';
        forbidden('需要管理员权限');
    }
}
