<?php
/**
 * PeachtreesCMS API - Upload Media Files
 * POST /api/media/upload.php
 * Requires admin privileges
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../uploads/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

requireAdmin();
$user = getCurrentUser();
$mediaUserId = $user ? (int)$user['id'] : null;

try {
    // Media-library endpoint: whitelist profile "media" = image + video + audio,
    // matching the client's <input accept="image/*,video/*,audio/*">.
    $saved = processMediaUpload('media', $mediaUserId);

    success([
        'files' => $saved
    ], 'Upload successful');
} catch (Exception $e) {
    error_log('[peachtrees] Upload failed: ' . $e->getMessage());
    serverError('Upload failed');
}
