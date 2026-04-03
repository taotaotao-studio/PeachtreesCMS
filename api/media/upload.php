<?php
/**
 * PeachtreesCMS API - 上传媒体文件
 * POST /api/media/upload.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

requireAdmin();

if (!isset($_FILES['files']) && !isset($_FILES['file'])) {
    error('请上传媒体文件');
}

if (!function_exists('finfo_open')) {
    serverError('服务器配置错误：fileinfo 扩展未启用，请启用该扩展后再试');
}

function normalizeUploadFiles(): array {
    if (isset($_FILES['files'])) {
        $files = $_FILES['files'];
    } else {
        $files = $_FILES['file'];
    }

    if (!is_array($files['name'])) {
        return [[
            'name' => $files['name'],
            'type' => $files['type'],
            'tmp_name' => $files['tmp_name'],
            'error' => $files['error'],
            'size' => $files['size'],
        ]];
    }

    $normalized = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }
    return $normalized;
}

try {
    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
    ];

    $uploadFiles = normalizeUploadFiles();
    $saved = [];

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

    foreach ($uploadFiles as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errMsgs = [
                UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制(upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
                UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            ];
            error($errMsgs[$file['error']] ?? '文件上传失败(错误代码:' . $file['error'] . ')');
        }

        if ($file['size'] === 0) {
            error('上传的文件内容为空');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedMimeToExt[$mime])) {
            error('仅支持 jpg/png/webp/gif/mp4/webm/ogg/mp3/wav/m4a/aac 媒体文件');
        }

        $ext = $allowedMimeToExt[$mime];
        $hash = bin2hex(random_bytes(8));
        $filename = "{$day}-{$hash}.{$ext}";
        $relativePath = "upload/{$relativeDir}/{$filename}";
        $absolutePath = UPLOAD_DIR . "{$relativeDir}/{$filename}";

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            serverError('保存文件失败');
        }

        $saved[] = [
            'path' => $relativePath,
            'url' => '/' . $relativePath
        ];
    }

    success([
        'files' => $saved
    ], '上传成功');
} catch (Exception $e) {
    serverError('上传失败: ' . $e->getMessage());
}
