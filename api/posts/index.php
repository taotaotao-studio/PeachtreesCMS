<?php
/**
 * PeachtreesCMS API - 获取文章列表
 * GET /api/posts/index.php
 * 参数: page, perPage, tag
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

// 只接受 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

// 获取分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = max(1, min(100, intval($_GET['perPage'] ?? 10)));
$tag = $_GET['tag'] ?? null;
$showInactive = isset($_GET['showInactive']) && $_GET['showInactive'] === 'true';
$offset = ($page - 1) * $perPage;

try {
    $pdo = getDB();

    // 构建查询条件
    $conditions = [];
    $params = [];

    if ($tag) {
        $conditions[] = "p.tag = ?";
        $params[] = $tag;
    }

    // 默认只显示发布的文章
    if (!$showInactive) {
        $conditions[] = "p.active = 1";
    }

    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // 获取总数
    $countSql = "SELECT COUNT(*) FROM pt_posts p $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // 获取文章列表
    $listSql = "SELECT p.id, p.tag, p.post_type, p.title, p.slug, p.summary, p.cover_media, p.content, p.allow_comments, p.active, p.created_at, p.updated_at,
                t.display_name
                FROM pt_posts p
                LEFT JOIN pt_tags t ON p.tag = t.tag
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";

    $listStmt = $pdo->prepare($listSql);

    // 绑定参数
    $paramIndex = 1;
    foreach ($params as $param) {
        $listStmt->bindValue($paramIndex++, $param, PDO::PARAM_STR);
    }
    $listStmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
    $listStmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);

    $listStmt->execute();
    $posts = $listStmt->fetchAll();
    
    // 处理文章摘要
    foreach ($posts as &$post) {
        $coverMedia = json_decode($post['cover_media'] ?? '[]', true);
        $coverMedia = is_array($coverMedia) ? $coverMedia : [];
        $post['cover_media'] = array_map(function ($path) {
            if (is_string($path) && str_starts_with($path, 'upload/bigpicture/')) {
                return 'upload/media/' . substr($path, strlen('upload/bigpicture/'));
            }
            return $path;
        }, $coverMedia);

        // 生成摘要 (去除HTML标签，截取前200字符)
        $summarySource = trim($post['summary'] ?? '') !== '' ? $post['summary'] : $post['content'];
        $plainText = strip_tags($summarySource);
        $post['excerpt'] = mb_substr($plainText, 0, 200, 'UTF-8');
        if (mb_strlen($plainText, 'UTF-8') > 200) {
            $post['excerpt'] .= '...';
        }
    }
    
    success([
        'posts' => $posts,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => intval($total),
            'totalPages' => ceil($total / $perPage)
        ],
        '_debug' => [
            'tag' => $tag,
            'showInactive' => $showInactive,
            'where' => $whereClause,
            'total_raw' => $total
        ]
    ]);
    
} catch (PDOException $e) {
    serverError('获取文章列表失败: ' . $e->getMessage());
}
