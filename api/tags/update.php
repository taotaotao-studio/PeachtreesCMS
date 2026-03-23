<?php
/**
 * PeachtreesCMS API - 更新标签
 * PUT /api/tags/update.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 PUT 和 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证登录
requireAuth();

// 获取请求参数
$input = getJsonInput();
$id = intval($input['id'] ?? 0);
$tag = trim($input['tag'] ?? '');
$tagLocal = trim($input['display_name'] ?? '');

// 验证输入
if ($id <= 0) {
    error('标签ID无效');
}

if (empty($tag)) {
    error('标签英文名不能为空');
}

if (empty($tagLocal)) {
    error('标签名称不能为空');
}

// 验证英文名格式
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $tag)) {
    error('标签英文名只能包含字母、数字、下划线和连字符');
}

try {
    $pdo = getDB();
    
    // 检查标签是否存在
    $checkStmt = $pdo->prepare("SELECT tag FROM tags WHERE id = ?");
    $checkStmt->execute([$id]);
    $oldTag = $checkStmt->fetch();
    
    if (!$oldTag) {
        notFound('标签不存在');
    }
    
    // 检查新英文名是否已被其他标签使用
    $dupStmt = $pdo->prepare("SELECT id FROM tags WHERE tag = ? AND id != ?");
    $dupStmt->execute([$tag, $id]);
    if ($dupStmt->fetch()) {
        error('标签英文名已被使用');
    }
    
    $oldTagName = $oldTag['tag'];
    
    // 开启事务
    $pdo->beginTransaction();
    
    try {
        // 更新标签
        $updateStmt = $pdo->prepare("UPDATE tags SET tag = ?, display_name = ? WHERE id = ?");
        $updateStmt->execute([$tag, $tagLocal, $id]);
        
        // 如果英文名变更，更新文章表中的标签
        if ($oldTagName !== $tag) {
            $updatePostsStmt = $pdo->prepare("UPDATE posts SET tag = ? WHERE tag = ?");
            $updatePostsStmt->execute([$tag, $oldTagName]);
        }
        
        $pdo->commit();
        
        success([
            'id' => $id,
            'tag' => $tag,
            'display_name' => $tagLocal
        ], '标签更新成功');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    serverError('更新标签失败: ' . $e->getMessage());
}
