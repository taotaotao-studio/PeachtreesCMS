<?php
/**
 * PeachtreesCMS API - Batch Toggle Post Publication Status
 * PUT /api/posts/batch-toggle.php
 * Requires authentication
 * Body: { ids: [1,2,3], active: 0 }
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

requireAuth();

$input = getJsonInput();
$ids = $input['ids'] ?? [];
$active = isset($input['active']) ? intval($input['active']) : 0;

if (!is_array($ids) || empty($ids)) {
    error('No post IDs provided');
}

$ids = array_map('intval', $ids);
$ids = array_filter($ids, fn($id) => $id > 0);
$ids = array_unique($ids);

if (empty($ids)) {
    error('No valid post IDs');
}

try {
    $pdo = getDB();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE pt_posts SET active = ?, updated_at = NOW() WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$active], $ids));

    success([
        'affected' => $stmt->rowCount(),
        'ids' => $ids,
        'active' => $active
    ], $active == 1 ? 'Posts published' : 'Posts unpublished');

} catch (PDOException $e) {
    serverError('Failed to batch toggle posts: ' . $e->getMessage());
}
