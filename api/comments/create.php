<?php
/**
 * PeachtreesCMS API - 创建评论
 * POST /api/comments/create.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 获取请求参数
$input = getJsonInput();
$postId = intval($input['post_id'] ?? 0);
$email = trim($input['email'] ?? '');
$nickname = trim($input['nickname'] ?? '');
$content = trim($input['content'] ?? '');
$website = trim($input['website'] ?? '');
$parentId = intval($input['parent_id'] ?? 0);
$captcha = trim($input['captcha'] ?? '');

// 验证输入
if ($postId <= 0) {
    error('文章ID无效');
}

if (empty($email)) {
    error('邮箱不能为空');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error('邮箱格式不正确');
}

if (empty($nickname)) {
    error('昵称不能为空');
}

if (empty($content)) {
    error('评论内容不能为空');
}

if (strlen($content) > 1000) {
    error('评论内容不能超过1000字');
}

// 验证验证码
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['captcha']) || strtoupper($captcha) !== strtoupper($_SESSION['captcha'])) {
    error('验证码错误');
}
unset($_SESSION['captcha']); // 验证后删除验证码

try {
    $pdo = getDB();
    
    // 检查文章是否存在且允许评论
    $postStmt = $pdo->prepare("SELECT id, allow_comments FROM posts WHERE id = ?");
    $postStmt->execute([$postId]);
    $post = $postStmt->fetch();
    
    if (!$post) {
        notFound('文章不存在');
    }
    
    if ($post['allow_comments'] != 1) {
        error('该文章不允许评论');
    }
    
    // 如果有父评论，验证父评论是否存在
    if ($parentId > 0) {
        $parentStmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND post_id = ?");
        $parentStmt->execute([$parentId, $postId]);
        if (!$parentStmt->fetch()) {
            error('父评论不存在');
        }
    }
    
    // 查找或创建评论用户
    $userStmt = $pdo->prepare("SELECT id FROM comment_users WHERE email = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();
    
    if ($user) {
        $userId = $user['id'];
        // 更新用户信息
        $updateUserStmt = $pdo->prepare("UPDATE comment_users SET nickname = ?, website = ? WHERE id = ?");
        $updateUserStmt->execute([$nickname, $website, $userId]);
    } else {
        // 创建新用户
        $insertUserStmt = $pdo->prepare("INSERT INTO comment_users (email, nickname, website, created_at) VALUES (?, ?, ?, NOW())");
        $insertUserStmt->execute([$email, $nickname, $website]);
        $userId = $pdo->lastInsertId();
    }
    
    // 根据白名单决定评论状态
    // 默认待审核(0)；trusted 自动通过(1)；blocked 直接拒绝
    $commentStatus = 0;
    $successMessage = '评论提交成功，等待审核';
    try {
        $whitelistStmt = $pdo->prepare("
            SELECT status
            FROM commenter_whitelist
            WHERE comment_user_id = ?
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $whitelistStmt->execute([$userId]);
        $whitelist = $whitelistStmt->fetch();

        if ($whitelist) {
            if ($whitelist['status'] === 'blocked') {
                error('留言受限，请联系管理员');
            }
            if ($whitelist['status'] === 'trusted') {
                $commentStatus = 1;
                $successMessage = '评论提交成功';
            }
        }
    } catch (PDOException $e) {
        // 白名单表不存在或查询失败时，兼容回退到原有待审核逻辑
    }

    // 获取评论者IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // 插入评论
    $sql = "INSERT INTO comments (post_id, user_id, content, status, parent_id, ip, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$postId, $userId, $content, $commentStatus, $parentId > 0 ? $parentId : null, $ip]);
    
    $commentId = $pdo->lastInsertId();
    
    success([
        'id' => $commentId,
        'post_id' => $postId,
        'user_id' => $userId,
        'status' => $commentStatus
    ], $successMessage);
    
} catch (PDOException $e) {
    serverError('创建评论失败: ' . $e->getMessage());
}
