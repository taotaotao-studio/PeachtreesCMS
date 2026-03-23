<?php
/**
 * PeachtreesCMS API - 上传普通文章媒体（图片/视频/音频）
 * POST /api/posts/upload-media.php
 * 需要登录
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

requireAuth();

if (!isset($_FILES['file'])) {
    error('请上传媒体文件');
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    error('文件上传失败');
}

// 检查 fileinfo 扩展是否可用
if (!function_exists('finfo_open')) {
    serverError('服务器配置错误：fileinfo 扩展未启用，请启用该扩展后再试');
}

try {
    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimeToExt[$mime])) {
        error('仅支持 jpg/png/webp/gif/mp4/mp3/wav/ogg/m4a/aac 媒体文件');
    }

    $year = date('Y');
    $month = date('m');
    $day = date('d');
    $relativeDir = "media/{$year}/{$month}";
    $absoluteDir = UPLOAD_DIR . $relativeDir;
    $uploadRoot = UPLOAD_DIR;

    if (!is_dir($absoluteDir)) {
        if (!is_dir($uploadRoot) || !is_writable($uploadRoot)) {
            serverError('上传目录不可写，请检查 upload 目录权限: ' . $uploadRoot);
        }
        if (!@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            serverError('创建上传目录失败: ' . $absoluteDir);
        }
    }

    $ext = $allowedMimeToExt[$mime];
    $hash = bin2hex(random_bytes(8));
    $filename = "{$day}-{$hash}.{$ext}";
    $relativePath = "upload/{$relativeDir}/{$filename}";
    $absolutePath = UPLOAD_DIR . "{$relativeDir}/{$filename}";

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        serverError('保存文件失败');
    }

    success([
        'path' => $relativePath,
        'url' => '/' . $relativePath
    ], '上传成功');
} catch (Exception $e) {
    serverError('上传失败: ' . $e->getMessage());
}
