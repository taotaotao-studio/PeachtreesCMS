<?php
/**
 * PeachtreesCMS API - RSS Feed
 * GET /api/rss.php
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

try {
    $pdo = getDB();

    // Get latest 30 published posts
    $sql = "SELECT p.id, p.slug, p.title, p.content, p.created_at, t.display_name
            FROM pt_posts p
            LEFT JOIN pt_tags t ON p.tag = t.tag
            WHERE p.active = 1
            ORDER BY p.created_at DESC
            LIMIT 30";
    $stmt = $pdo->query($sql);
    $posts = $stmt->fetchAll();

    // Site identity: scheme/host/base path from the current request; title and
    // description from site options. Links use the frontend HashRouter form
    // (/#/post/...) so they work on any deployment base path over http(s).
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $basePath = preg_replace('#/pt_api(/.*)?$#i', '', $scriptDir);
    if ($basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }
    $siteUrl = $scheme . '://' . $host . rtrim($basePath, '/');

    $siteTitle = 'PeachtreesCMS';
    $siteDesc = 'Article Feed';
    $defaultLang = 'en-US';
    try {
        $optStmt = $pdo->query(
            "SELECT option_key, option_value FROM pt_options WHERE option_key IN ('site_title', 'site_description', 'default_lang')"
        );
        foreach ($optStmt->fetchAll() as $opt) {
            $value = trim((string)$opt['option_value']);
            if ($value === '') {
                continue;
            }
            if ($opt['option_key'] === 'site_title') {
                $siteTitle = $value;
            } elseif ($opt['option_key'] === 'site_description') {
                $siteDesc = mb_substr(strip_tags($value), 0, 300);
            } elseif ($opt['option_key'] === 'default_lang') {
                $defaultLang = $value;
            }
        }
    } catch (Throwable $optErr) {
        // Site options unavailable — keep the defaults above
    }

    $rssLang = strtolower(str_replace('_', '-', $defaultLang));
    $now = date('D, d M Y H:i:s T');

    // Build RSS XML
    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
    $xml .= '<rss version="2.0">' . "\n";
    $xml .= '  <channel>' . "\n";
    $xml .= "    <title>" . htmlspecialchars($siteTitle, ENT_XML1) . "</title>\n";
    $xml .= "    <link>{$siteUrl}</link>\n";
    $xml .= "    <description>" . htmlspecialchars($siteDesc, ENT_XML1) . "</description>\n";
    $xml .= "    <language>{$rssLang}</language>\n";
    $xml .= "    <pubDate>{$now}</pubDate>\n";
    $xml .= "    <lastBuildDate>{$now}</lastBuildDate>\n";

    foreach ($posts as $post) {
        $title = htmlspecialchars($post['title'], ENT_XML1);
        $identifier = ($post['slug'] ?? '') !== '' ? $post['slug'] : $post['id'];
        $link = "{$siteUrl}/#/post/{$identifier}";
        $description = htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 500), ENT_XML1);
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
