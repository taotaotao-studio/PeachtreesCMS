<?php
/**
 * PeachtreesCMS API - 删除标签
 * DELETE /api/tags/delete.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 接受 DELETE 和 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证登录
requireAuth();

// 获取请求参数
$input = getJsonInput();
$id = intval($input['id'] ?? 0);

// 也支持从 GET 参数获取
if ($id <= 0) {
    $id = intval($_GET['id'] ?? 0);
}

if ($id <= 0) {
    error('标签ID无效');
}

try {
    $pdo = getDB();
    
    // 检查标签是否存在
    $checkStmt = $pdo->prepare("SELECT id, tag FROM pt_tags WHERE id = ?");
    $checkStmt->execute([$id]);
    $tag = $checkStmt->fetch();
    
    if (!$tag) {
        notFound('标签不存在');
    }
    
    // 检查是否有文章使用此标签
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pt_posts WHERE tag = ?");
    $countStmt->execute([$tag['tag']]);
    $postCount = $countStmt->fetchColumn();
    
    if ($postCount > 0) {
        error("无法删除：有 {$postCount} 篇文章使用此标签");
    }
    
    // 删除标签
    $deleteStmt = $pdo->prepare("DELETE FROM pt_tags WHERE id = ?");
    $deleteStmt->execute([$id]);
    
    success(null, '标签删除成功');
    
} catch (PDOException $e) {
    serverError('删除标签失败: ' . $e->getMessage());
}
