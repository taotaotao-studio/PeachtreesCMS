<?php
/**
 * PeachtreesCMS API - Batch Set Comment Status
 * PUT /api/comments/batch-approve.php
 * Requires authentication
 * Body: { ids: [1,2,3], status: 1 }
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
$status = intval($input['status'] ?? 1);

if (!is_array($ids) || empty($ids)) {
    error('No comment IDs provided');
}

if (!in_array($status, [0, 1, 2])) {
    error('Invalid status value');
}

$ids = array_map('intval', $ids);
$ids = array_filter($ids, fn($id) => $id > 0);
$ids = array_unique($ids);

if (empty($ids)) {
    error('No valid comment IDs');
}

$statusText = [0 => 'Pending', 1 => 'Approved', 2 => 'Rejected'];

try {
    $pdo = getDB();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE pt_comments SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$status], $ids));

    success([
        'affected' => $stmt->rowCount(),
        'ids' => $ids,
        'status' => $status,
        'status_text' => $statusText[$status]
    ], 'Comments updated');

} catch (PDOException $e) {
    serverError('Failed to batch update comments: ' . $e->getMessage());
}
