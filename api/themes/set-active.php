<?php
/**
 * PeachtreesCMS API - 切换激活主题
 * PUT /api/themes/set-active.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

requireAdmin();

$input = getJsonInput();
$slug = trim($input['slug'] ?? '');

if ($slug === '' || !isValidThemeSlug($slug)) {
    error('主题标识无效');
}

try {
    $pdo = getDB();
    scanThemePackages($pdo);

    $checkStmt = $pdo->prepare("SELECT id FROM themes WHERE slug = ? LIMIT 1");
    $checkStmt->execute([$slug]);
    $theme = $checkStmt->fetch();
    if (!$theme) {
        notFound('主题不存在，请先将主题包放入 public/theme 目录');
    }

    $targetId = intval($theme['id']);
    $updateStmt = $pdo->prepare("UPDATE themes SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END, updated_at = NOW()");
    $updateStmt->execute([$targetId]);

    success([
        'slug' => $slug
    ], '主题切换成功');
} catch (PDOException $e) {
    serverError('主题切换失败: ' . $e->getMessage());
}
