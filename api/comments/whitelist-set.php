<?php
/**
 * PeachtreesCMS API - 设置留言白名单状态
 * PUT /api/comments/whitelist-set.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

$admin = requireAdmin();

$input = getJsonInput();
$email = strtolower(trim($input['email'] ?? ''));
$status = trim($input['status'] ?? '');
$reason = trim($input['reason'] ?? '');
$expiresAt = trim($input['expires_at'] ?? '');

if ($email === '') {
    error('邮箱不能为空');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error('邮箱格式不正确');
}
match ($status) {
    'trusted', 'blocked', 'none' => true,
    default => error('状态值无效')
};

$expiresAtValue = null;
if ($expiresAt !== '') {
    $ts = strtotime($expiresAt);
    if ($ts === false) {
        error('过期时间格式无效');
    }
    $expiresAtValue = date('Y-m-d H:i:s', $ts);
}

try {
    $pdo = getDB();

    $userStmt = $pdo->prepare("SELECT id FROM pt_comment_users WHERE email = ?");
    $userStmt->execute([$email]);
    $commentUser = $userStmt->fetch();
    if (!$commentUser) {
        notFound('该邮箱尚未留言，无法设置白名单');
    }
    $commentUserId = intval($commentUser['id']);

    if ($status === 'none') {
        $deleteStmt = $pdo->prepare("DELETE FROM pt_commenter_whitelist WHERE comment_user_id = ?");
        $deleteStmt->execute([$commentUserId]);
        success([
            'email' => $email,
            'status' => 'none',
        ], '已移出白名单');
    }

    $upsertSql = "
        INSERT INTO pt_commenter_whitelist
            (comment_user_id, status, reason, expires_at, created_by, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            reason = VALUES(reason),
            expires_at = VALUES(expires_at),
            created_by = VALUES(created_by),
            updated_at = NOW()
    ";
    $upsertStmt = $pdo->prepare($upsertSql);
    $upsertStmt->execute([
        $commentUserId,
        $status,
        $reason === '' ? null : $reason,
        $expiresAtValue,
        intval($admin['id'] ?? 0) ?: null,
    ]);

    success([
        'email' => $email,
        'status' => $status,
        'expires_at' => $expiresAtValue,
    ], '白名单状态已更新');
} catch (PDOException $e) {
    serverError('设置白名单失败: ' . $e->getMessage());
}

