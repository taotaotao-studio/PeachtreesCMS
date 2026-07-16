<?php
/**
 * POST /api/plugin/mail-publish/ingest.php
 * Publish post via email (text + optional single image)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

$rawBody = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

$payload = [];
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$secretHeader = $_SERVER['HTTP_X_MAIL_TOKEN'] ?? '';
$signature = $_SERVER['HTTP_X_MAIL_SIGNATURE'] ?? '';
$token = $payload['token'] ?? ($_POST['token'] ?? '') ?: $secretHeader;

$pdo = getDB();

function getOptionValue(PDO $pdo, string $key): ?string {
    $stmt = $pdo->prepare('SELECT option_value FROM pt_options WHERE option_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['option_value'] : null;
}

$secret = getOptionValue($pdo, 'mail_publish_secret');
if (!$secret) {
    serverError('Mail publish secret is not configured');
}

if ($signature !== '') {
    $expected = hash_hmac('sha256', $rawBody, $secret);
    if (!hash_equals($expected, $signature)) {
        unauthorized('Invalid signature');
    }
} elseif ($token !== '') {
    if (!hash_equals($secret, $token)) {
        unauthorized('Invalid token');
    }
} else {
    unauthorized('Missing token');
}

$subject = trim((string)($payload['subject'] ?? ($_POST['subject'] ?? '')));
$from = trim((string)($payload['from'] ?? ($_POST['from'] ?? '')));
$text = trim((string)($payload['text'] ?? ($payload['plain'] ?? ($_POST['text'] ?? ($_POST['plain'] ?? '')))));

if ($subject === '' || $text === '') {
    error('Subject and text are required');
}

function extractEmail(string $from): string {
    if (preg_match('/<([^>]+)>/', $from, $matches)) {
        return trim($matches[1]);
    }
    return trim($from);
}

$sender = extractEmail($from);
if ($sender === '') {
    error('Invalid sender');
}

$whitelistRaw = getOptionValue($pdo, 'mail_publish_whitelist');
if ($whitelistRaw !== null && trim($whitelistRaw) !== '') {
    $parts = preg_split('/[\s,;]+/', $whitelistRaw);
    $allowed = array_filter(array_map('strtolower', array_map('trim', $parts)));
    if (!in_array(strtolower($sender), $allowed, true)) {
        forbidden('Sender not allowed');
    }
}

$defaultTag = getOptionValue($pdo, 'mail_publish_default_tag');
$tag = null;
if ($defaultTag) {
    $checkTag = $pdo->prepare('SELECT tag FROM pt_tags WHERE tag = ? LIMIT 1');
    $checkTag->execute([$defaultTag]);
    if ($checkTag->fetch()) {
        $tag = $defaultTag;
    }
}

// Receive pre-uploaded image URLs from mail_handler.php
$uploadImageUrls = [];
if (!empty($_POST['image_urls'])) {
    $uploadImageUrls = json_decode($_POST['image_urls'], true) ?: [];
}

function safeTextToHtml(string $text): string {
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escaped = preg_replace("/\r\n|\r|\n/", "\n", $escaped);
    $paragraphs = preg_split("/\n\n+/", $escaped);
    $paragraphs = array_map(function ($p) {
        $lineBreaks = nl2br($p);
        return '<p>' . $lineBreaks . '</p>';
    }, array_filter($paragraphs, fn($p) => trim($p) !== ''));
    return implode("\n", $paragraphs);
}

// CID mapping: replace cid: references with actual uploaded image URLs
$cidMapping = [];
if (!empty($_POST['cid_mapping'])) {
    $cidMapping = json_decode($_POST['cid_mapping'], true) ?: [];
}

// Determine content HTML
$htmlInput = !empty($_POST['html']) ? $_POST['html'] : '';

if (!empty($htmlInput)) {
    // Use HTML body directly, then replace cid: references
    $contentHtml = $htmlInput;

    // Replace cid: references with uploaded image URLs
    if (!empty($cidMapping)) {
        foreach ($cidMapping as $cid => $url) {
            $contentHtml = str_replace('cid:' . $cid, $url, $contentHtml);
        }
    }

    // Remove any remaining cid: img tags (broken references)
    $contentHtml = preg_replace('/<img[^>]*cid:[^>]*>/i', '', $contentHtml);

    // Append non-CID images at the top
    $cidUrls = array_values($cidMapping);
    $extraImages = [];
    foreach ($uploadImageUrls as $url) {
        if (!in_array($url, $cidUrls)) {
            $extraImages[] = '<p><img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" /></p>';
        }
    }
    if (!empty($extraImages)) {
        $contentHtml = implode("\n", $extraImages) . "\n" . $contentHtml;
    }
} else {
    // Fallback: plain text to HTML
    $contentHtml = safeTextToHtml($text);
    if (!empty($uploadImageUrls)) {
        $imageHtmlParts = [];
        foreach ($uploadImageUrls as $url) {
            $imageHtmlParts[] = '<p><img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" /></p>';
        }
        $contentHtml = implode("\n", $imageHtmlParts) . "\n" . $contentHtml;
    }
}



$plainSummary = strip_tags($contentHtml);
if (function_exists('mb_substr')) {
    $summary = mb_substr($plainSummary, 0, 160, 'UTF-8');

} else {
    $summary = substr($plainSummary, 0, 160);
}

// Generate slug from title
function generateSlug(string $title): string {
    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $title)) {
        return 'mail-' . substr(md5($title . microtime()), 0, 8);
    }
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    return 'mail-' . substr($slug, 0, 50);
}

// Ensure slug uniqueness
$baseSlug = generateSlug($subject);
$slug = $baseSlug;
$slugSuffix = 1;
$slugCheck = $pdo->prepare('SELECT id FROM pt_posts WHERE slug = ? LIMIT 1');
while (true) {
    $slugCheck->execute([$slug]);
    if (!$slugCheck->fetch()) break;
    $slug = $baseSlug . '-' . $slugSuffix++;
}

try {
    $insertSql = "INSERT INTO pt_posts (tag, post_type, title, slug, summary, cover_media, content, allow_comments, active, created_at, updated_at)
                  VALUES (?, 'normal', ?, ?, ?, '[]', ?, 1, 1, NOW(), NOW())";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        $tag,
        $subject,
        $slug,
        $summary,
        $contentHtml
    ]);

    $postId = $pdo->lastInsertId();

    if ($tag) {
        $updateCount = $pdo->prepare("UPDATE pt_tags SET post_count = (SELECT COUNT(*) FROM pt_posts WHERE tag = ?) WHERE tag = ?");
        $updateCount->execute([$tag, $tag]);
    }

    success([
        'id' => (int)$postId,
        'slug' => $slug,
        'title' => $subject
    ], 'Post published via email');

} catch (PDOException $e) {
    serverError('Failed to create post: ' . $e->getMessage());
}
