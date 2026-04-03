<?php
/**
 * PeachtreesCMS API - 删除媒体文件
 * DELETE /api/media/delete.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    error('Method not allowed', 405);
}

requireAdmin();

$input = getJsonInput();
$path = $input['path'] ?? '';
if (!is_string($path) || trim($path) === '') {
    error('缺少文件路径');
}

$path = ltrim(trim($path), '/');
if (str_starts_with($path, 'upload/')) {
    $path = substr($path, 7);
}

if (!str_starts_with($path, 'media/')) {
    error('仅允许删除 media 目录下的文件');
}

$fullPath = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
$realPath = realpath($fullPath);
$uploadRoot = realpath(UPLOAD_DIR);
if ($realPath === false || $uploadRoot === false || strpos($realPath, $uploadRoot) !== 0) {
    error('文件路径无效');
}

if (!is_file($realPath)) {
    error('文件不存在');
}

if (!@unlink($realPath)) {
    serverError('删除失败');
}

// 清理空目录（最多向上清理到 media 目录）
$currentDir = dirname($realPath);
$mediaRoot = realpath(rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'media');
while ($mediaRoot && strpos($currentDir, $mediaRoot) === 0 && $currentDir !== $mediaRoot) {
    $items = scandir($currentDir);
    if ($items && count($items) === 2) {
        @rmdir($currentDir);
        $currentDir = dirname($currentDir);
        continue;
    }
    break;
}

success([
    'path' => 'upload/' . str_replace('\\', '/', $path)
], '删除成功');
