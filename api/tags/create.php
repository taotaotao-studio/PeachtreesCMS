<?php
/**
 * PeachtreesCMS API - 创建标签
 * POST /api/tags/create.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证登录
requireAuth();

// 获取请求参数
$input = getJsonInput();
$tag = trim($input['tag'] ?? '');
$tagLocal = trim($input['display_name'] ?? '');

// 验证输入
if (empty($tag)) {
    error('标签英文名不能为空');
}

if (empty($tagLocal)) {
    error('标签名称不能为空');
}

// 验证英文名格式 (只允许字母、数字、下划线、连字符)
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $tag)) {
    error('标签英文名只能包含字母、数字、下划线和连字符');
}

try {
    $pdo = getDB();
    
    // 检查标签是否已存在
    $checkStmt = $pdo->prepare("SELECT id FROM tags WHERE tag = ?");
    $checkStmt->execute([$tag]);
    if ($checkStmt->fetch()) {
        error('标签英文名已存在');
    }
    
    // 插入标签
    $sql = "INSERT INTO tags (tag, display_name, post_count) VALUES (?, ?, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tag, $tagLocal]);
    
    $tagId = $pdo->lastInsertId();
    
    success([
        'id' => $tagId,
        'tag' => $tag,
        'display_name' => $tagLocal,
        'post_count' => 0
    ], '标签创建成功');
    
} catch (PDOException $e) {
    serverError('创建标签失败: ' . $e->getMessage());
}
