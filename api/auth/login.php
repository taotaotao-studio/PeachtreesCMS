<?php
/**
 * PeachtreesCMS API - 用户登录
 * POST /api/auth/login.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../password.php';

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 获取请求参数
$input = getJsonInput();
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// 验证输入
if (empty($username) || empty($password)) {
    error('用户名和密码不能为空');
}

try {
    $pdo = getDB();
    
    // 查询用户
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        error('用户不存在');
    }
    
    // 验证密码
    if (!verifyPassword($password, $user['password_hash'])) {
        error('密码错误');
    }
    
    // 设置 Session
    $_SESSION['uid'] = $user['id'];
    $_SESSION['user'] = $user['username'];
    
    // 更新登录时间
    $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // 返回成功
    success([
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email']
    ], '登录成功');
    
} catch (PDOException $e) {
    serverError('登录失败: ' . $e->getMessage());
}
