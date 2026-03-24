<?php
/**
 * PeachtreesCMS API - 更新文章
 * PUT /api/posts/update.php
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
$title = trim($input['title'] ?? '');
$tag = trim($input['tag'] ?? '');
$postType = trim($input['post_type'] ?? 'normal');
$summary = trim($input['summary'] ?? '');
$coverMedia = $input['cover_media'] ?? [];
$content = $input['content'] ?? '';
$allowComments = isset($input['allow_comments']) ? intval($input['allow_comments']) : null;

// 处理slug - 如果没有提供或者是空字符串，设为null（使用ID作为URL）
$slug = null;
if (array_key_exists('slug', $input)) {
    $slug = trim($input['slug']);
    if ($slug === '') {
        $slug = null;
    }
}

// 验证输入（不需要数据库连接）
if ($id <= 0) {
    error('文章ID无效');
}

if (empty($title)) {
    error('文章标题不能为空');
}

if (empty($tag)) {
    error('请选择分类');
}

if (!in_array($postType, ['normal', 'big-picture'], true)) {
    error('文章类型无效');
}

if ($allowComments !== null && !in_array($allowComments, [0, 1])) {
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

    // 验证slug（如果提供了，需要数据库连接检查重复）
    if ($slug !== null && $slug !== '') {
        // 检查slug格式：只允许字母、数字、连字符和下划线
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            error('URL标识只能包含字母、数字、连字符和下划线');
        }
        // 检查slug是否已被其他文章使用
        $slugCheckStmt = $pdo->prepare("SELECT id FROM pt_posts WHERE slug = ? AND id != ?");
        $slugCheckStmt->execute([$slug, $id]);
        if ($slugCheckStmt->fetch()) {
            error('URL标识已存在');
        }
    }

    // 检查文章是否存在
    $checkStmt = $pdo->prepare("SELECT id, tag, post_type, slug, summary, cover_media, allow_comments FROM pt_posts WHERE id = ?");
    $checkStmt->execute([$id]);
    $oldPost = $checkStmt->fetch();

    if (!$oldPost) {
        notFound('文章不存在');
    }

    $oldTag = $oldPost['tag'];

    // 处理默认值
    if (!array_key_exists('post_type', $input)) {
        $postType = $oldPost['post_type'];
    }

    if ($allowComments === null) {
        $allowComments = $oldPost['allow_comments'];
    }

    if (!array_key_exists('summary', $input)) {
        $summary = $oldPost['summary'] ?? '';
    }

    if (!array_key_exists('cover_media', $input)) {
        $coverMedia = json_decode($oldPost['cover_media'] ?? '[]', true);
        if (!is_array($coverMedia)) {
            $coverMedia = [];
        }
    }

    // 处理slug的最终值
    if (!array_key_exists('slug', $input)) {
        // 没有提供slug字段，保持原值
        $slug = $oldPost['slug'];
    } elseif ($slug === '' || $slug === null) {
        // 提供了空值，用户想清空slug
        $slug = null;
    } elseif ($slug === $oldPost['slug']) {
        // slug值没变，保持原值
        $slug = $oldPost['slug'];
    }
    // 否则，用户设置了新的slug值，使用新值

    // 更新文章
    $sql = "UPDATE pt_posts SET tag = ?, post_type = ?, title = ?, slug = ?, summary = ?, cover_media = ?, content = ?, allow_comments = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $tag,
        $postType,
        $title,
        $slug,
        $summary,
        json_encode(array_values($coverMedia), JSON_UNESCAPED_UNICODE),
        $content,
        $allowComments,
        $id
    ]);

    // 更新旧标签和新标签的计数
    if ($oldTag != $tag) {
        $updateOldTag = $pdo->prepare("UPDATE pt_tags SET post_count = (SELECT COUNT(*) FROM pt_posts WHERE tag = ?) WHERE tag = ?");
        $updateOldTag->execute([$oldTag, $oldTag]);
    }
    $updateNewTag = $pdo->prepare("UPDATE pt_tags SET post_count = (SELECT COUNT(*) FROM pt_posts WHERE tag = ?) WHERE tag = ?");
    $updateNewTag->execute([$tag, $tag]);

    success([
        'id' => $id
    ], '文章更新成功');

} catch (PDOException $e) {
    serverError('更新文章失败: ' . $e->getMessage());
}