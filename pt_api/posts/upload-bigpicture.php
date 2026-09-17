<?php
/**
 * PeachtreesCMS API - Upload Big-Picture Post Cover Media
 * POST /api/posts/upload-bigpicture.php
 * Requires authentication
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../media/_helpers.php';
require_once __DIR__ . '/../uploads/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

$user = requireAuth();
$mediaUserId = (int)$user['id'];

try {
    // Big-picture cover endpoint: whitelist profile "post_cover" = image + mp4,
    // matching the client's <input accept="image/*,video/mp4">. Audio is deliberately
    // NOT accepted here — a cover slot cannot render it.
    $saved = processMediaUpload('post_cover', $mediaUserId);

    success([
        'paths' => array_column($saved, 'path')
    ], 'Upload successful');
} catch (Exception $e) {
    error_log('[peachtrees] Upload failed: ' . $e->getMessage());
    serverError('Upload failed');
}
