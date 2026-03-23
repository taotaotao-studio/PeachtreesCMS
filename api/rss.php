<?php
/**
 * PeachtreesCMS API - RSS 订阅源
 * GET /api/rss.php
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';

// 设置 XML 响应头
header('Content-Type: application/xml; charset=utf-8');

try {
    $pdo = getDB();
    
    // 获取最新 30 篇已发布文章
    $sql = "SELECT p.id, p.title, p.content, p.created_at, t.display_name
            FROM posts p
            LEFT JOIN tags t ON p.tag = t.tag
            WHERE p.active = 1
            ORDER BY p.created_at DESC
            LIMIT 30";
    $stmt = $pdo->query($sql);
    $posts = $stmt->fetchAll();
    
    // 网站基本信息
    $siteTitle = 'PeachtreesCMS';
    $siteUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $siteDesc = '文章订阅';
    $now = date('D, d M Y H:i:s T');
    
    // 构建 RSS XML
    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
    $xml .= '<rss version="2.0">' . "\n";
    $xml .= '  <channel>' . "\n";
    $xml .= "    <title>{$siteTitle}</title>\n";
    $xml .= "    <link>{$siteUrl}</link>\n";
    $xml .= "    <description>{$siteDesc}</description>\n";
    $xml .= "    <language>zh-cn</language>\n";
    $xml .= "    <pubDate>{$now}</pubDate>\n";
    $xml .= "    <lastBuildDate>{$now}</lastBuildDate>\n";
    
    foreach ($posts as $post) {
        $title = htmlspecialchars($post['title'], ENT_XML1);
        $link = "{$siteUrl}/post/{$post['id']}";
        $description = htmlspecialchars(strip_tags($post['content']), ENT_XML1);
        $pubDate = date('D, d M Y H:i:s T', strtotime($post['created_at']));
        $category = htmlspecialchars($post['display_name'] ?? '', ENT_XML1);
        
        $xml .= "    <item>\n";
        $xml .= "      <title>{$title}</title>\n";
        $xml .= "      <link>{$link}</link>\n";
        $xml .= "      <description>{$description}</description>\n";
        $xml .= "      <pubDate>{$pubDate}</pubDate>\n";
        if ($category) {
            $xml .= "      <category>{$category}</category>\n";
        }
        $xml .= "    </item>\n";
    }
    
    $xml .= '  </channel>' . "\n";
    $xml .= '</rss>';
    
    echo $xml;
    
} catch (PDOException $e) {
    echo '<?xml version="1.0" encoding="utf-8"?>';
    echo '<error>Database error</error>';
}
