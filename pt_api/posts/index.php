<?php
/**
 * PeachtreesCMS API - Get Post List
 * GET /api/posts/index.php
 * Parameters: page, perPage, tag
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// Get pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = max(1, min(100, intval($_GET['perPage'] ?? 10)));
$tag = $_GET['tag'] ?? null;
// Optional list filters (admin UI):
//   search  — title LIKE keyword
//   status  — published | draft | scheduled (draft/scheduled require login)
//   mine    — only the current user's posts (requires login)
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$mineOnly = isset($_GET['mine']) && $_GET['mine'] === '1';
// showInactive requires an authenticated user; anonymous visitors always see
// only published posts. Non-admin authors see only their own inactive posts.
require_once __DIR__ . '/../auth.php';
$requestUser = getCurrentUser();
$showInactive = isset($_GET['showInactive']) && $_GET['showInactive'] === 'true' && $requestUser !== null;
$mineOnly = $mineOnly && $requestUser !== null;
$offset = ($page - 1) * $perPage;

try {
    $pdo = getDB();

    // Build query conditions
    $conditions = [];
    $params = [];

    if ($tag) {
        $conditions[] = "p.tag = ?";
        $params[] = $tag;
    }

    if ($search !== '') {
        $conditions[] = "p.title LIKE ?";
        $params[] = '%' . $search . '%';
    }

    if ($mineOnly) {
        $conditions[] = "p.user_id = ?";
        $params[] = $requestUser['id'];
    }

    if ($statusFilter === 'published') {
        $conditions[] = "p.active = 1";
    } elseif ($showInactive && $statusFilter === 'draft') {
        // Inactive and NOT scheduled: scheduled posts carry a future created_at
        $conditions[] = "p.active = 0 AND (p.created_at IS NULL OR p.created_at <= NOW())";
    } elseif ($showInactive && $statusFilter === 'scheduled') {
        $conditions[] = "p.active = 0 AND p.created_at > NOW()";
    }

    // By default only show published posts
    if (!$showInactive) {
        $conditions[] = "p.active = 1";
    } elseif ((int)($requestUser['role'] ?? 2) !== 1) {
        // Non-admin authors can only see their own inactive posts
        $conditions[] = "(p.active = 1 OR p.user_id = ?)";
        $params[] = $requestUser['id'];
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // Get total count
    $countSql = "SELECT COUNT(*) FROM pt_posts p $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get post list
    $listSql = "SELECT p.id, p.user_id, p.tag, p.post_type, p.title, p.slug, p.summary, p.cover_media, p.allow_comments, p.active, p.created_at, p.updated_at,
                t.display_name, t.page_style AS tag_page_style
                FROM pt_posts p
                LEFT JOIN pt_tags t ON p.tag = t.tag
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";

    $listStmt = $pdo->prepare($listSql);

    // Bind parameters
    $paramIndex = 1;
    foreach ($params as $param) {
        $listStmt->bindValue($paramIndex++, $param, PDO::PARAM_STR);
    }
    $listStmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
    $listStmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);

    $listStmt->execute();
    $posts = $listStmt->fetchAll();
    
    // Process post excerpts
    foreach ($posts as &$post) {
        $coverMedia = json_decode($post['cover_media'] ?? '[]', true);
        $coverMedia = is_array($coverMedia) ? $coverMedia : [];
        $post['cover_media'] = array_map(function ($path) {
            if (is_string($path) && str_starts_with($path, UPLOAD_REL_PREFIX . '/bigpicture/')) {
                return UPLOAD_REL_PREFIX . '/' . substr($path, strlen(UPLOAD_REL_PREFIX . '/bigpicture/'));
            }
            // Legacy rows stored before the content/ layout
            if (is_string($path) && str_starts_with($path, 'upload/bigpicture/')) {
                return UPLOAD_REL_PREFIX . '/' . substr($path, strlen('upload/bigpicture/'));
            }
            return $path;
        }, $coverMedia);

        // Generate excerpt (remove HTML tags, take first 200 characters)
        $summarySource = trim($post['summary'] ?? '') !== '' ? $post['summary'] : '';
        $plainText = strip_tags($summarySource);
        $post['excerpt'] = mb_substr($plainText, 0, 200, 'UTF-8');
        if (mb_strlen($plainText, 'UTF-8') > 200) {
            $post['excerpt'] .= '...';
        }
    }
    
    $response = [
        'posts' => $posts,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => intval($total),
            'totalPages' => ceil($total / $perPage)
        ],
    ];

    // Diagnostic payload — opt-in only. This endpoint is public and anonymous, so
    // its internals (the resolved WHERE clause, raw counts) must not leak by
    // default. Enable with APP_DEBUG=1 in pt_api/.env when debugging locally.
    if (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $response['_debug'] = [
            'tag' => $tag,
            'showInactive' => $showInactive,
            'where' => $whereClause,
            'total_raw' => $total
        ];
    }

    success($response);
    
} catch (PDOException $e) {
    error_log('[peachtrees] Failed to get post list: ' . $e->getMessage());
    serverError('Failed to get post list');
}
