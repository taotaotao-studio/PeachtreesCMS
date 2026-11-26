<?php
/**
 * PeachtreesCMS API - Get Site Settings
 * GET /api/options/index.php
 *
 * Public (unauthenticated) requests receive only the whitelisted keys needed
 * by the frontend. Admin users receive the full option set.
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// Keys that are safe to expose to anonymous visitors
$publicKeys = [
    'site_title',
    'footer_text',
    'default_lang',
    'show_logo',
    'comments_enabled',
    'site_description',
];

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT option_key, option_value FROM pt_options");
    $rows = $stmt->fetchAll();
    
    $options = [];
    foreach ($rows as $row) {
        $options[$row['option_key']] = $row['option_value'];
    }

    // Non-admin callers only see the public whitelist
    $user = getCurrentUser();
    if (!$user || (int)($user['role'] ?? 2) !== 1) {
        $filtered = [];
        foreach ($publicKeys as $key) {
            if (array_key_exists($key, $options)) {
                $filtered[$key] = $options[$key];
            }
        }
        // Expose plugin-enabled flags (non-secret) so the frontend can adapt UI
        foreach ($options as $k => $v) {
            if (str_starts_with($k, 'plugin_enabled_')) {
                $filtered[$k] = $v;
            }
        }
        $options = $filtered;
    }
    
    success($options);
} catch (PDOException $e) {
    error_log('[peachtrees] Failed to get settings: ' . $e->getMessage());
    serverError('Failed to get settings');
}
