<?php
/**
 * PeachtreesCMS API - 创建用户
 * POST /api/users/create.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../password.php';

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证管理员权限
requireAdmin();

// 获取请求参数
$input = getJsonInput();
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// 验证输入
if (empty($username)) {
    error('用户名不能为空');
}

if (empty($email)) {
    error('邮箱不能为空');
}

if (empty($password)) {
    error('密码不能为空');
}

if (strlen($password) < 6) {
    error('密码长度至少6位');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error('邮箱格式不正确');
}

try {
    $pdo = getDB();
    
    // 检查用户名是否已存在
    $checkUserStmt = $pdo->prepare("SELECT id FROM pt_users WHERE username = ?");
    $checkUserStmt->execute([$username]);
    if ($checkUserStmt->fetch()) {
        error('用户名已存在');
    }
    
    // 检查邮箱是否已存在
    $checkEmailStmt = $pdo->prepare("SELECT id FROM pt_users WHERE email = ?");
    $checkEmailStmt->execute([$email]);
    if ($checkEmailStmt->fetch()) {
        error('邮箱已被使用');
    }
    
    // 加密密码
    $hashedPassword = hashPassword($password);
    
    // 插入用户
    $sql = "INSERT INTO pt_users (username, email, password_hash, created_at, last_login_at) VALUES (?, ?, ?, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $hashedPassword]);
    
    $userId = $pdo->lastInsertId();
    
    success([
        'id' => $userId,
        'username' => $username,
        'email' => $email
    ], '用户创建成功');
    
} catch (PDOException $e) {
    serverError('创建用户失败: ' . $e->getMessage());
}
