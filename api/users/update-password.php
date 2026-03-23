<?php
/**
 * PeachtreesCMS API - 修改密码
 * PUT /api/users/update-password.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../password.php';

// 只接受 PUT 和 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证登录
$user = requireAuth();

// 获取请求参数
$input = getJsonInput();
$oldPassword = $input['oldPassword'] ?? '';
$newPassword = $input['newPassword'] ?? '';
$confirmPassword = $input['confirmPassword'] ?? '';

// 验证输入
if (empty($oldPassword)) {
    error('请输入原密码');
}

if (empty($newPassword)) {
    error('请输入新密码');
}

if (strlen($newPassword) < 6) {
    error('新密码长度至少6位');
}

if ($newPassword !== $confirmPassword) {
    error('两次输入的密码不一致');
}

try {
    $pdo = getDB();
    
    // 获取当前用户的密码
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        error('用户不存在');
    }
    
    // 验证原密码
    if (!verifyPassword($oldPassword, $currentUser['password_hash'])) {
        error('原密码错误');
    }
    
    // 加密新密码
    $hashedPassword = hashPassword($newPassword);
    
    // 更新密码
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $updateStmt->execute([$hashedPassword, $user['id']]);
    
    success(null, '密码修改成功');
    
} catch (PDOException $e) {
    serverError('修改密码失败: ' . $e->getMessage());
}
