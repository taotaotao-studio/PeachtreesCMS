<?php
/**
 * PeachtreesCMS API - 删除评论
 * DELETE /api/comments/delete.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 DELETE 请求
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证登录
requireAuth();

// 获取请求参数
$input = getJsonInput();
$id = intval($input['id'] ?? 0);

// 验证输入
if ($id <= 0) {
    error('评论ID无效');
}

try {
    $pdo = getDB();
    
    // 检查评论是否存在
    $checkStmt = $pdo->prepare("SELECT id FROM comments WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        notFound('评论不存在');
    }
    
    // 删除评论（级联删除会自动删除子评论）
    $sql = "DELETE FROM comments WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    success([
        'id' => $id
    ], '评论删除成功');
    
} catch (PDOException $e) {
    serverError('删除评论失败: ' . $e->getMessage());
}