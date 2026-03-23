<?php
/**
 * PeachtreesCMS API - 审核评论
 * PUT /api/comments/approve.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 PUT 请求
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 验证登录
requireAuth();

// 获取请求参数
$input = getJsonInput();
$id = intval($input['id'] ?? 0);
$status = intval($input['status'] ?? 1);

// 验证输入
if ($id <= 0) {
    error('评论ID无效');
}

if (!in_array($status, [0, 1, 2])) {
    error('状态值无效');
}

// 状态说明
$statusText = [
    0 => '待审核',
    1 => '已通过',
    2 => '已拒绝'
];

try {
    $pdo = getDB();
    
    // 检查评论是否存在
    $checkStmt = $pdo->prepare("SELECT id, status, user_id FROM comments WHERE id = ?");
    $checkStmt->execute([$id]);
    $comment = $checkStmt->fetch();
    
    if (!$comment) {
        notFound('评论不存在');
    }
    
    // 更新评论状态
    $sql = "UPDATE comments SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $id]);

    // 自动白名单规则：
    // 1) 累计 >=3 条已通过评论 -> trusted
    // 2) 累计 >=2 条已拒绝评论 -> blocked（若当前已是 trusted 则不自动降级）
    try {
        $userId = intval($comment['user_id'] ?? 0);
        if ($userId > 0 && in_array($status, [1, 2], true)) {
            $wlStmt = $pdo->prepare("SELECT status FROM commenter_whitelist WHERE comment_user_id = ? LIMIT 1");
            $wlStmt->execute([$userId]);
            $wl = $wlStmt->fetch();
            $currentWlStatus = $wl['status'] ?? null;

            if ($status === 1 && $currentWlStatus !== 'blocked') {
                $approvedCountStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM comments WHERE user_id = ? AND status = 1");
                $approvedCountStmt->execute([$userId]);
                $approvedCount = intval($approvedCountStmt->fetch()['cnt'] ?? 0);

                if ($approvedCount >= 3) {
                    $upsertTrustedStmt = $pdo->prepare("
                        INSERT INTO commenter_whitelist (comment_user_id, status, reason, expires_at, created_by, created_at, updated_at)
                        VALUES (?, 'trusted', ?, NULL, NULL, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            status = 'trusted',
                            reason = VALUES(reason),
                            expires_at = NULL,
                            updated_at = NOW()
                    ");
                    $upsertTrustedStmt->execute([$userId, '系统自动：累计3条已通过评论']);
                }
            }

            if ($status === 2 && $currentWlStatus !== 'trusted') {
                $rejectedCountStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM comments WHERE user_id = ? AND status = 2");
                $rejectedCountStmt->execute([$userId]);
                $rejectedCount = intval($rejectedCountStmt->fetch()['cnt'] ?? 0);

                if ($rejectedCount >= 2) {
                    $upsertBlockedStmt = $pdo->prepare("
                        INSERT INTO commenter_whitelist (comment_user_id, status, reason, expires_at, created_by, created_at, updated_at)
                        VALUES (?, 'blocked', ?, NULL, NULL, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            status = 'blocked',
                            reason = VALUES(reason),
                            expires_at = NULL,
                            updated_at = NOW()
                    ");
                    $upsertBlockedStmt->execute([$userId, '系统自动：累计2条被拒评论']);
                }
            }
        }
    } catch (PDOException $e) {
        // 白名单表不存在或自动规则写入失败时，不影响评论审核主流程
    }
    
    success([
        'id' => $id,
        'status' => $status,
        'status_text' => $statusText[$status]
    ], '评论审核成功');
    
} catch (PDOException $e) {
    serverError('审核评论失败: ' . $e->getMessage());
}
