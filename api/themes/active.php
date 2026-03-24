<?php
/**
 * PeachtreesCMS API - 当前激活主题（前台）
 * GET /api/themes/active.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

try {
    $pdo = getDB();
    scanThemePackages($pdo);

    $stmt = $pdo->query("SELECT id, slug, name, description, version, author, entry_css, user_layout_config, updated_at FROM pt_themes WHERE is_active = 1 LIMIT 1");
    $theme = $stmt ? $stmt->fetch() : null;

    if (!$theme) {
        success(null, '当前没有激活主题');
    }

    $versionToken = rawurlencode($theme['updated_at'] ?? date('Y-m-d H:i:s'));
    $theme['css_url'] = themePublicCssUrl($theme['slug'], $theme['entry_css']) . '?v=' . $versionToken;
    $userLayoutConfig = json_decode($theme['user_layout_config'] ?? 'null', true);
    $theme['layout'] = loadThemeLayout($theme['slug'], $userLayoutConfig);

    success($theme, '获取激活主题成功');
} catch (PDOException $e) {
    serverError('获取激活主题失败: ' . $e->getMessage());
}
