<?php
/**
 * PeachtreesCMS API - 删除用户
 * DELETE /api/users/delete.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 接受 DELETE 和 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证管理员权限
requireAdmin();

// 获取请求参数
$input = getJsonInput();
$id = intval($input['id'] ?? 0);

// 也支持从 GET 参数获取
if ($id <= 0) {
    $id = intval($_GET['id'] ?? 0);
}

if ($id <= 0) {
    error('用户ID无效');
}

// 不能删除管理员 (uid = 1)
if ($id === 1) {
    error('无法删除管理员账户');
}

try {
    $pdo = getDB();
    
    // 检查用户是否存在
    $checkStmt = $pdo->prepare("SELECT id, username FROM pt_users WHERE id = ?");
    $checkStmt->execute([$id]);
    $user = $checkStmt->fetch();
    
    if (!$user) {
        notFound('用户不存在');
    }
    
    // 删除用户
    $deleteStmt = $pdo->prepare("DELETE FROM pt_users WHERE id = ?");
    $deleteStmt->execute([$id]);
    
    success(null, '用户删除成功');
    
} catch (PDOException $e) {
    serverError('删除用户失败: ' . $e->getMessage());
}
