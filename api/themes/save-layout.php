<?php
/**
 * PeachtreesCMS API - 保存用户自定义主题布局
 * PUT /api/themes/save-layout.php
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
$themeSlug = trim($input['theme_slug'] ?? '');

if ($themeSlug === '' || !isValidThemeSlug($themeSlug)) {
    error('主题标识无效');
}

$layoutConfig = $input['layout'] ?? null;

if (!is_array($layoutConfig)) {
    error('布局配置格式无效');
}

try {
    $pdo = getDB();

    // 检查主题是否存在
    $checkStmt = $pdo->prepare("SELECT id FROM themes WHERE slug = ? LIMIT 1");
    $checkStmt->execute([$themeSlug]);
    $theme = $checkStmt->fetch();

    if (!$theme) {
        notFound('主题不存在');
    }

    // 验证并规范化布局配置 (使用该主题的文件默认布局作为 fallback，而不是系统全局默认)
    $themeDefaults = loadThemeLayout($themeSlug);
    $normalizedLayout = [
        'home' => normalizePageLayout($layoutConfig['home'] ?? null, $themeDefaults['home'], 'home'),
        'post' => normalizePageLayout($layoutConfig['post'] ?? null, $themeDefaults['post'], 'post'),
    ];

    // 保存用户自定义布局配置
    $updateStmt = $pdo->prepare("UPDATE themes SET user_layout_config = ?, updated_at = NOW() WHERE slug = ?");
    $updateStmt->execute([json_encode($normalizedLayout, JSON_UNESCAPED_UNICODE), $themeSlug]);

    success([
        'theme_slug' => $themeSlug,
        'layout' => $normalizedLayout
    ], '主题布局保存成功');

} catch (PDOException $e) {
    serverError('保存主题布局失败: ' . $e->getMessage());
}