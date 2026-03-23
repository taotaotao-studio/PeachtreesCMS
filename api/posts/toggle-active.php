<?php
/**
 * PeachtreesCMS API - 切换文章发布状态
 * PUT /api/posts/toggle-active.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 PUT 请求
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证登录
requireAuth();

// 获取请求参数
$input = getJsonInput();
$id = intval($input['id'] ?? 0);

// 验证输入
if ($id <= 0) {
    error('文章ID无效');
}

try {
    $pdo = getDB();
    
    // 检查文章是否存在
    $checkStmt = $pdo->prepare("SELECT id, active FROM posts WHERE id = ?");
    $checkStmt->execute([$id]);
    $post = $checkStmt->fetch();
    
    if (!$post) {
        notFound('文章不存在');
    }
    
    // 切换active状态
    $newActive = $post['active'] == 1 ? 0 : 1;
    
    // 更新文章状态
    $sql = "UPDATE posts SET active = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newActive, $id]);
    
    success([
        'id' => $id,
        'active' => $newActive
    ], $newActive == 1 ? '文章已发布' : '文章已下架');
    
} catch (PDOException $e) {
    serverError('切换文章状态失败: ' . $e->getMessage());
}