<?php
/**
 * PeachtreesCMS API - Active Theme (Frontend)
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

    $stmt = $pdo->query("SELECT id, slug, description, version, author, entry_css FROM pt_themes WHERE is_active = 1 LIMIT 1");
    $theme = $stmt ? $stmt->fetch() : null;

    if (!$theme) {
        success(null, 'No active theme');
    }

    $theme['css_url'] = themePublicCssUrl($theme['slug'], $theme['entry_css']);
    success($theme, 'Active theme retrieved successfully');
} catch (PDOException $e) {
    serverError('Failed to get active theme: ' . $e->getMessage());
}
