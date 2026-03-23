<?php
/**
 * PeachtreesCMS API - 获取文章详情
 * GET /api/posts/view.php?id=1
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

// 只接受 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// 获取文章标识（可以是slug或id）
$identifier = $_GET['id'] ?? $_GET['slug'] ?? '';

if (empty($identifier)) {
    error('文章标识无效');
}

try {
    $pdo = getDB();

    // 判断是数字ID还是slug
    $isNumericId = is_numeric($identifier);

    // 获取当前文章 - 使用OR条件同时匹配id和slug
    if ($isNumericId) {
        $sql = "SELECT p.id, p.tag, p.post_type, p.title, p.slug, p.summary, p.cover_media, p.content, p.allow_comments, p.active, p.created_at, p.updated_at,
                t.display_name
                FROM posts p
                LEFT JOIN tags t ON p.tag = t.tag
                WHERE p.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([intval($identifier)]);
    } else {
        $sql = "SELECT p.id, p.tag, p.post_type, p.title, p.slug, p.summary, p.cover_media, p.content, p.allow_comments, p.active, p.created_at, p.updated_at,
                t.display_name
                FROM posts p
                LEFT JOIN tags t ON p.tag = t.tag
                WHERE p.slug = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$identifier]);
    }
    $post = $stmt->fetch();

    if (!$post) {
        notFound('文章不存在');
    }

    // 检查文章是否已发布
    if ($post['active'] != 1) {
        notFound('文章不存在');
    }

    $coverMedia = json_decode($post['cover_media'] ?? '[]', true);
    $post['cover_media'] = is_array($coverMedia) ? $coverMedia : [];

    // 获取上一篇文章（ID更小的最近一篇，且已发布）
    $prevSql = "SELECT id, title, slug FROM posts WHERE id < ? AND active = 1 ORDER BY id DESC LIMIT 1";
    $prevStmt = $pdo->prepare($prevSql);
    $prevStmt->execute([$post['id']]);
    $post['prev_post'] = $prevStmt->fetch();

    // 获取下一篇文章（ID更大的最近一篇，且已发布）
    $nextSql = "SELECT id, title, slug FROM posts WHERE id > ? AND active = 1 ORDER BY id ASC LIMIT 1";
    $nextStmt = $pdo->prepare($nextSql);
    $nextStmt->execute([$post['id']]);
    $post['next_post'] = $nextStmt->fetch();

    success($post);

} catch (PDOException $e) {
    serverError('获取文章失败: ' . $e->getMessage());
}
