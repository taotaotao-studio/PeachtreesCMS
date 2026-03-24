<?php
/**
 * PeachtreesCMS API - 获取评论列表
 * GET /api/comments/index.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

// 只接受 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// 获取请求参数
$postId = intval($_GET['post_id'] ?? 0);
$status = intval($_GET['status'] ?? 1); // 默认只显示已通过的评论
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
$flat = isset($_GET['flat']) && $_GET['flat'] === '1'; // 是否返回平铺列表（后台需要）

// 是否需要管理员权限（查看所有状态的评论）
$isAdmin = isAdmin();

// 验证输入
// 如果不是管理员，必须提供post_id
if (!$isAdmin && $postId <= 0) {
    error('文章ID无效');
}

try {
    $pdo = getDB();

    // 如果提供了post_id，检查文章是否存在
    if ($postId > 0) {
        $postStmt = $pdo->prepare("SELECT id FROM pt_posts WHERE id = ?");
        $postStmt->execute([$postId]);
        if (!$postStmt->fetch()) {
            notFound('文章不存在');
        }
    }

    // 构建查询条件
    $whereConditions = [];
    $params = [];

    if ($postId > 0) {
        $whereConditions[] = "c.post_id = ?";
        $params[] = $postId;
    }

    if ($isAdmin) {
        // 管理员可以查看所有状态的评论
        if ($status > 0) {
            $whereConditions[] = "c.status = ?";
            $params[] = $status;
        }
    } else {
        // 普通用户只能看到已通过的评论
        $whereConditions[] = "c.status = 1";
    }

    // 如果没有where条件，使用1=1
    if (empty($whereConditions)) {
        $whereClause = "1=1";
    } else {
        $whereClause = implode(' AND ', $whereConditions);
    }
    // 计算总数
    $countSql = "SELECT COUNT(*) as total FROM pt_comments c WHERE " . $whereClause;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // 获取评论列表
    $offset = ($page - 1) * $pageSize;
    $sql = "SELECT
                c.id,
                c.post_id,
                c.user_id,
                c.content,
                c.status,
                c.parent_id,
                c.created_at,
                cu.email,
                cu.nickname,
                cu.website,
                cu.avatar
            FROM pt_comments c
            LEFT JOIN pt_comment_users cu ON c.user_id = cu.id
            WHERE " . $whereClause . "
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";

    $params[] = $pageSize;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $comments = $stmt->fetchAll();

    // 根据flat参数决定返回树形结构还是平铺列表
    if ($flat) {
        // 返回平铺列表（后台需要）
        $finalComments = $comments;
    } else {
        // 构建树形结构（处理回复）
        $commentMap = [];
        $rootComments = [];

        foreach ($comments as $comment) {
            $commentMap[$comment['id']] = $comment;
            $comment['replies'] = [];
            $comment['can_reply'] = true; // 是否可以回复
        }

        foreach ($comments as $comment) {
            if ($comment['parent_id'] === null) {
                $rootComments[] = &$commentMap[$comment['id']];
            } else {
                if (isset($commentMap[$comment['parent_id']])) {
                    $commentMap[$comment['parent_id']]['replies'][] = &$commentMap[$comment['id']];
                }
            }
        }

        $finalComments = $rootComments;
    }

    success([
        'comments' => $finalComments,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => ceil($total / $pageSize)
        ],
        'is_admin' => $isAdmin
    ], '获取评论列表成功');

} catch (PDOException $e) {
    serverError('获取评论列表失败: ' . $e->getMessage());
}