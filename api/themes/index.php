<?php
/**
 * PeachtreesCMS API - 主题列表（后台）
 * GET /api/themes/index.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

requireAdmin();

try {
    $pdo = getDB();
    scanThemePackages($pdo);

    $stmt = $pdo->query("SELECT id, slug, name, description, version, author, entry_css, thumbnail, is_active, user_layout_config, updated_at, last_scanned_at FROM pt_themes ORDER BY id ASC");
    $themes = $stmt ? $stmt->fetchAll() : [];

    foreach ($themes as &$theme) {
        $theme['is_active'] = intval($theme['is_active']);
        $theme['user_layout_config'] = $theme['user_layout_config'] ? json_decode($theme['user_layout_config'], true) : null;
        $theme['css_url'] = themePublicCssUrl($theme['slug'], $theme['entry_css']);
        // 添加主题的默认布局（从 layout.json 读取）
        $theme['default_layout'] = loadThemeLayout($theme['slug']);
        // 确保 default_layout 包含所有必需的字段
        if (!isset($theme['default_layout']['home']['left_sidebar_blocks'])) {
            $theme['default_layout']['home']['left_sidebar_blocks'] = $theme['default_layout']['home']['sidebar_blocks'] ?? [];
        }
        if (!isset($theme['default_layout']['home']['right_sidebar_blocks'])) {
            $theme['default_layout']['home']['right_sidebar_blocks'] = [];
        }
        if (!isset($theme['default_layout']['post']['left_sidebar_blocks'])) {
            $theme['default_layout']['post']['left_sidebar_blocks'] = $theme['default_layout']['post']['sidebar_blocks'] ?? [];
        }
        if (!isset($theme['default_layout']['post']['right_sidebar_blocks'])) {
            $theme['default_layout']['post']['right_sidebar_blocks'] = [];
        }
    }

    success([
        'themes' => $themes
    ], '获取主题列表成功');
} catch (PDOException $e) {
    serverError('获取主题列表失败: ' . $e->getMessage());
}
