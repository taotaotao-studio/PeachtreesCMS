<?php
/**
 * PeachtreesCMS API - 删除文章
 * DELETE /api/posts/delete.php
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
    error('文章ID无效');
}

try {
    $pdo = getDB();
    
    // 检查文章是否存在并获取标签
    $checkStmt = $pdo->prepare("SELECT id, tag FROM posts WHERE id = ?");
    $checkStmt->execute([$id]);
    $post = $checkStmt->fetch();
    
    if (!$post) {
        notFound('文章不存在');
    }
    
    $tag = $post['tag'];
    
    // 删除文章
    $deleteStmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $deleteStmt->execute([$id]);
    
    // 更新标签计数
    if ($tag) {
        $updateCountStmt = $pdo->prepare("UPDATE tags SET post_count = (SELECT COUNT(*) FROM posts WHERE tag = ?) WHERE tag = ?");
        $updateCountStmt->execute([$tag, $tag]);
    }
    
    success(null, '文章删除成功');
    
} catch (PDOException $e) {
    serverError('删除文章失败: ' . $e->getMessage());
}
