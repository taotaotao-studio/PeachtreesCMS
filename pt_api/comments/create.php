<?php
/**
 * PeachtreesCMS API - Create Comment
 * POST /api/comments/create.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// Get request parameters
$input = getJsonInput();
$postId = intval($input['post_id'] ?? 0);
$email = trim($input['email'] ?? '');
$nickname = trim($input['nickname'] ?? '');
$content = trim($input['content'] ?? '');
$website = trim($input['website'] ?? '');

// Validate website URL protocol to prevent javascript: XSS
if ($website !== '' && !preg_match('#^https?://#i', $website)) {
    error('Website URL must start with http:// or https://');
}
$parentId = intval($input['parent_id'] ?? 0);
$captcha = trim($input['captcha'] ?? '');

// Validate input
if ($postId <= 0) {
    error('Invalid post ID');
}

if (empty($email)) {
    error('Email cannot be empty');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error('Invalid email format');
}

if (empty($nickname)) {
    error('Nickname cannot be empty');
}

if (empty($content)) {
    error('Comment content cannot be empty');
}

if (strlen($content) > 1000) {
    error('Comment content cannot exceed 1000 characters');
}

// Verify captcha
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Always clear captcha after verification attempt to prevent replay
$storedCaptcha = $_SESSION['captcha'] ?? null;
$captchaTime   = $_SESSION['captcha_time'] ?? 0;
unset($_SESSION['captcha'], $_SESSION['captcha_time']);

if ($storedCaptcha === null || strtoupper($captcha) !== strtoupper($storedCaptcha)) {
    error('Invalid captcha');
}
// Reject captchas older than 5 minutes
if (time() - $captchaTime > 300) {
    error('Captcha expired, please refresh');
}

try {
    $pdo = getDB();
    
    // Check if post exists and allows comments
    $postStmt = $pdo->prepare("SELECT id, allow_comments FROM pt_posts WHERE id = ?");
    $postStmt->execute([$postId]);
    $post = $postStmt->fetch();
    
    if (!$post) {
        notFound('Post not found');
    }
    
    if ($post['allow_comments'] != 1) {
        error('This post does not allow comments');
    }
    
    // If there's a parent comment, verify it exists
    if ($parentId > 0) {
        $parentStmt = $pdo->prepare("SELECT id FROM pt_comments WHERE id = ? AND post_id = ?");
        $parentStmt->execute([$parentId, $postId]);
        if (!$parentStmt->fetch()) {
            error('Parent comment not found');
        }
    }
    
    // Find or create comment user
    $userStmt = $pdo->prepare("SELECT id FROM pt_comment_users WHERE email = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();
    
    if ($user) {
        $userId = $user['id'];
        // Update user info
        $updateUserStmt = $pdo->prepare("UPDATE pt_comment_users SET nickname = ?, website = ? WHERE id = ?");
        $updateUserStmt->execute([$nickname, $website, $userId]);
    } else {
        // Create new user
        $insertUserStmt = $pdo->prepare("INSERT INTO pt_comment_users (email, nickname, website, created_at) VALUES (?, ?, ?, NOW())");
        $insertUserStmt->execute([$email, $nickname, $website]);
        $userId = $pdo->lastInsertId();
    }
    
    // Determine comment status based on whitelist
    // Default pending review(0); trusted auto-approve(1); blocked directly reject
    $commentStatus = 0;
    $successMessage = 'Comment submitted successfully, pending review';
    try {
        $whitelistStmt = $pdo->prepare("
            SELECT status
            FROM pt_commenter_whitelist
            WHERE comment_user_id = ?
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $whitelistStmt->execute([$userId]);
        $whitelist = $whitelistStmt->fetch();

        if ($whitelist) {
            if ($whitelist['status'] === 'blocked') {
                error('Commenting restricted, please contact administrator');
            }
            if ($whitelist['status'] === 'trusted') {
                $commentStatus = 1;
                $successMessage = 'Comment submitted successfully';
            }
        }
    } catch (PDOException $e) {
        // Whitelist table doesn't exist or query failed, fall back to pending review
    }

    // Get commenter IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Insert comment
    $sql = "INSERT INTO pt_comments (post_id, user_id, content, status, parent_id, ip, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
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
    error_log('[peachtrees] Failed to create comment: ' . $e->getMessage());
    serverError('Failed to create comment');
}
