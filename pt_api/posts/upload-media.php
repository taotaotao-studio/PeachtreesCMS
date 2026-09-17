<?php
/**
 * PeachtreesCMS API - Upload Normal Post Media (image/video/audio)
 * POST /api/posts/upload-media.php
 * Supports session auth or mail token auth (X-Mail-Token header / token POST field)
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

// Support both session auth and mail token auth
$authenticated = false;
$user = getCurrentUser();
if ($user) {
    $authenticated = true;
} else {
    // Fallback: token-based auth for mail handler
    $token = $_POST['token'] ?? ($_SERVER['HTTP_X_MAIL_TOKEN'] ?? '');
    if ($token !== '') {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT option_value FROM pt_options WHERE option_key = ? LIMIT 1');
        $stmt->execute(['mail_publish_secret']);
        $row = $stmt->fetch();
        $secret = $row ? (string)$row['option_value'] : '';
        if ($secret !== '' && hash_equals($secret, $token)) {
            $authenticated = true;
        }
    }
}
if (!$authenticated) {
    unauthorized('Please login first');
}

try {
    // Single-file endpoint: accepts only the `file` field, one file per request.
    // Whitelist profile "media" = image + video + audio (same set as the media library),
    // so the editor's <input accept="video/*"> no longer offers types we then reject.
    $saved = processMediaUpload('media', $user ? (int)$user['id'] : null, ['file'], 1);

    success([
        'path' => $saved[0]['path'],
        'url' => $saved[0]['url']
    ], 'Upload successful');
} catch (Exception $e) {
    error_log('[peachtrees] Upload failed: ' . $e->getMessage());
    serverError('Upload failed');
}
