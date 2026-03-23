<?php
/**
 * PeachtreesCMS API - 上传大片文章封面媒体
 * POST /api/posts/upload-bigpicture.php
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

if (!isset($_FILES['files']) && !isset($_FILES['file'])) {
    // 检查是否是因为超出了 post_max_size
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
    $postMaxSize = ini_get('post_max_size');
    
    // 将 post_max_size 转换为字节
    $multiplier = 1;
    $unit = strtoupper(substr($postMaxSize, -1));
    if ($unit === 'G') $multiplier = 1024 * 1024 * 1024;
    elseif ($unit === 'M') $multiplier = 1024 * 1024;
    elseif ($unit === 'K') $multiplier = 1024;
    $limit = intval($postMaxSize) * $multiplier;

    if ($contentLength > $limit) {
        error("上传内容太大(当前 {$contentLength} 字节)，超过了服务器限制的 post_max_size ({$postMaxSize})。请减少图片数量或压缩图片后再试。");
    }

    error('未接收到上传文件，请检查是否选择了文件或单个文件是否过大。');
}

/**
 * 标准化文件数组，兼容单文件和多文件。
 * @return array
 */
function normalizeUploadFiles() {
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
    ];

    $uploadFiles = normalizeUploadFiles();
    $savedPaths = [];

    $year = date('Y');
    $month = date('m');
    $day = date('d');
    $relativeDir = "bigpicture/{$year}/{$month}";
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
            error('不支持的文件类型：' . $mime . '。仅支持 jpg/png/webp/gif/mp4');
        }

        $ext = $allowedMimeToExt[$mime];
        $hash = bin2hex(random_bytes(8));
        $filename = "{$day}-{$hash}.{$ext}";
        $relativePath = "upload/{$relativeDir}/{$filename}";
        $absolutePath = UPLOAD_DIR . "{$relativeDir}/{$filename}";

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            serverError('保存文件失败');
        }

        $savedPaths[] = $relativePath;
    }

    success([
        'paths' => $savedPaths
    ], '上传成功');
} catch (Exception $e) {
    serverError('上传失败: ' . $e->getMessage());
}
