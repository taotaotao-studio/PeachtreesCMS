<?php
/**
 * PeachtreesCMS API - 创建文章
 * POST /api/posts/create.php
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
$title = trim($input['title'] ?? '');
$slug = trim($input['slug'] ?? '');
$tag = trim($input['tag'] ?? '');
$postType = trim($input['post_type'] ?? 'normal');
$summary = trim($input['summary'] ?? '');
$coverMedia = $input['cover_media'] ?? [];
$content = $input['content'] ?? '';
$allowComments = isset($input['allow_comments']) ? intval($input['allow_comments']) : 1;

// 验证输入
if (empty($title)) {
    error('文章标题不能为空');
}

// 验证slug
if (!empty($slug)) {
    // 检查slug格式：只允许字母、数字、连字符和下划线
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
        error('URL标识只能包含字母、数字、连字符和下划线');
    }
    // 检查slug是否已存在
    $slugCheckStmt = $pdo->prepare("SELECT id FROM pt_posts WHERE slug = ?");
    $slugCheckStmt->execute([$slug]);
    if ($slugCheckStmt->fetch()) {
        error('URL标识已存在');
    }
}

if (empty($tag)) {
    error('请选择分类');
}

if (!in_array($postType, ['normal', 'big-picture'], true)) {
    error('文章类型无效');
}

if (!in_array($allowComments, [0, 1])) {
    error('评论开关值无效');
}

if (!is_array($coverMedia)) {
    error('封面媒体格式无效');
}

if ($postType === 'big-picture' && count($coverMedia) === 0) {
    error('大片文章至少需要上传一个封面媒体文件');
}

try {
    $pdo = getDB();
    
    // 检查标签是否存在
    $tagStmt = $pdo->prepare("SELECT tag FROM pt_tags WHERE tag = ?");
    $tagStmt->execute([$tag]);
    if (!$tagStmt->fetch()) {
        error('所选分类不存在');
    }
    
    // 插入文章
    $sql = "INSERT INTO pt_posts (tag, post_type, title, slug, summary, cover_media, content, allow_comments, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tag,
        $postType,
        $title,
        !empty($slug) ? $slug : null,
        $summary,
        json_encode(array_values($coverMedia), JSON_UNESCAPED_UNICODE),
        $content,
        $allowComments
    ]);
    
    $postId = $pdo->lastInsertId();
    
    // 更新标签计数
    $updateCountStmt = $pdo->prepare("UPDATE pt_tags SET post_count = (SELECT COUNT(*) FROM pt_posts WHERE tag = ?) WHERE tag = ?");
    $updateCountStmt->execute([$tag, $tag]);
    
    success([
        'id' => $postId
    ], '文章创建成功');
    
} catch (PDOException $e) {
    serverError('创建文章失败: ' . $e->getMessage());
}
