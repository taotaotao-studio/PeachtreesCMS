<?php
/**
 * PeachtreesCMS API - Media Upload Helpers
 *
 * 三个上传端点（posts/upload-media.php、posts/upload-bigpicture.php、
 * media/upload.php）原本各自手写一份 MIME 白名单、一份 normalizeUploadFiles()
 * 和一套错误文案，三份互相不一致（见 OPTIMIZATION-SUGGESTIONS.md P-08）。
 * 本文件是这些逻辑的**单一事实来源**：白名单、文件数组规范化、校验顺序、
 * 错误文案、落盘与落库都在这里实现，端点只负责鉴权和响应形状。
 *
 * 统一后的处理顺序（三个端点完全一致）：
 *   1. 取文件 -> 2. 逐个校验（PHP 错误码 / 空文件 / MIME 白名单）
 *   -> 3. 检查并创建目标目录 -> 4. 落盘 -> 5. 落库
 * 校验先于任何文件系统写入，因此"类型不合法的文件"不会再在半个批次已经落盘之后才报错
 * （原先 bigpicture / media 两个端点是先做目录守卫再校验类型，上传目录不可用时
 * 任何请求都塌陷成 500，而不是给出准确的 400）。
 *
 * 注意：本文件只做"校验 + 落盘"，**尚未**实现 P-09 要求的整批事务回滚 ——
 * move_uploaded_file() 中途失败时，此前已落盘的文件仍会留在磁盘上。
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../media/_helpers.php';

/**
 * 允许上传的 MIME 分组（扩展名由此推导，全站唯一一份）。
 *
 * 说明：audio/ogg 与 video/ogg 都落到 .ogg —— Ogg 只是容器，单凭 MIME
 * 无法区分音频还是视频，这也是这两个键必须同时保留的原因。
 *
 * @return array<string, array<string, string>>
 */
function uploadMimeGroups(): array
{
    return [
        'image' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ],
        'video' => [
            'video/mp4'  => 'mp4',
            'video/webm' => 'webm',
            'video/ogg'  => 'ogg',
        ],
        'audio' => [
            'audio/mpeg'  => 'mp3',
            'audio/mp3'   => 'mp3',
            'audio/wav'   => 'wav',
            'audio/x-wav' => 'wav',
            'audio/ogg'   => 'ogg',
            'audio/mp4'   => 'm4a',
            'audio/aac'   => 'aac',
        ],
    ];
}

/**
 * 按用途取白名单（MIME => 扩展名）。
 *
 * 档案定义刻意保持"够用即可"，而不是把三处并成一个超集 —— 每个端点的
 * 前端 file picker 都写死了 accept，服务端放宽只会扩大攻击面：
 *
 *   media      通用媒体。编辑器内联媒体（TiptapEditor 的 accept 会给出 video/*，
 *              其中包含 webm/ogv）与后台媒体库都用它。
 *   post_cover 大图封面。前端 accept="image/*,video/mp4"，所以只放行图片 + mp4，
 *              不加音频 —— 封面位渲染不了音频，加进来没有意义。
 *
 * @param string $profile 档案名，见上方说明
 * @return array<string, string>
 */
function uploadWhitelist(string $profile): array
{
    $groups = uploadMimeGroups();

    switch ($profile) {
        case 'media':
            return $groups['image'] + $groups['video'] + $groups['audio'];
        case 'post_cover':
            return $groups['image'] + ['video/mp4' => 'mp4'];
        default:
            // 编程错误（未知档案名），交给调用方的 try/catch 记录
            throw new InvalidArgumentException('Unknown upload whitelist profile: ' . $profile);
    }
}

/**
 * 白名单允许的扩展名列表（用于拼错误文案）
 *
 * @param array<string, string> $whitelist
 * @return string[]
 */
function uploadAllowedExtensions(array $whitelist): array
{
    return array_values(array_unique(array_values($whitelist)));
}

/**
 * 文件类型不被允许时的统一文案。
 *
 * 统一句式的意义：前端可以只匹配这一种句式。同时**不回显探测到的 MIME**
 * （bigpicture 端点原本会把它回显出去，既是轻微信息泄漏，也让文案变成非常量）。
 *
 * @param array<string, string> $whitelist
 */
function uploadTypeRejectionMessage(array $whitelist): string
{
    return 'Unsupported file type. Allowed types: ' . implode(', ', uploadAllowedExtensions($whitelist));
}

/** PHP 上传错误码 -> 统一文案 */
function uploadErrorMessage(int $code): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form size limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is unavailable',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file',
        UPLOAD_ERR_EXTENSION  => 'Upload was stopped by a server extension',
    ];
    return $map[$code] ?? ('File upload failed (error code: ' . $code . ')');
}

/** ini 尺寸写法（如 "8M" / "512K"）转字节数；无法解析时返回 0 */
function uploadIniSizeToBytes(string $size): int
{
    $size = trim($size);
    if ($size === '') {
        return 0;
    }

    $value = (int)$size;
    switch (strtoupper(substr($size, -1))) {
        case 'G':
            return $value * 1024 * 1024 * 1024;
        case 'M':
            return $value * 1024 * 1024;
        case 'K':
            return $value * 1024;
        default:
            return $value;
    }
}

/**
 * 请求里没有任何上传文件时的统一报错。
 *
 * 优先识别"请求体超过 post_max_size，PHP 直接把 $_FILES 整个丢掉"这种最常见的情形 ——
 * 否则用户只看到"没收到文件"，完全无从下手。原先只有 bigpicture 端点做了这个诊断。
 */
function uploadMissingFileError(): never
{
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxSize = (string)ini_get('post_max_size');
    $limit = uploadIniSizeToBytes($postMaxSize);

    if ($limit > 0 && $contentLength > $limit) {
        error("Upload content too large ({$contentLength} bytes), exceeds the server post_max_size limit ({$postMaxSize}). Please reduce the number of files or compress them and try again.");
    }

    error('No upload file received. Please make sure at least one file is selected and that it does not exceed the server upload limit.');
}

/**
 * 取出请求里的上传文件并规范化（兼容单文件与 files[] 数组两种形式）。
 *
 * 本函数取代原先在 bigpicture / media 两个端点里各写一份的同名实现 ——
 * 那两份签名还不一样（一份带 ": array"、一份不带），只是因为每个请求
 * 只加载一个端点文件才没有触发 "Cannot redeclare"。
 *
 * @param string[] $keys 依次尝试的 $_FILES 键名
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function normalizeUploadFiles(array $keys = ['files', 'file']): array
{
    $source = null;
    foreach ($keys as $key) {
        if (isset($_FILES[$key])) {
            $source = $_FILES[$key];
            break;
        }
    }

    if ($source === null || !isset($source['name'])) {
        uploadMissingFileError();
    }

    // 单文件形式：name 是字符串；多文件形式：name 是数组
    if (!is_array($source['name'])) {
        return [[
            'name'     => (string)$source['name'],
            'type'     => (string)($source['type'] ?? ''),
            'tmp_name' => (string)($source['tmp_name'] ?? ''),
            'error'    => (int)($source['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int)($source['size'] ?? 0),
        ]];
    }

    $normalized = [];
    $count = count($source['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name'     => (string)$source['name'][$i],
            'type'     => (string)($source['type'][$i] ?? ''),
            'tmp_name' => (string)($source['tmp_name'][$i] ?? ''),
            'error'    => (int)($source['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int)($source['size'][$i] ?? 0),
        ];
    }
    return $normalized;
}

/**
 * 校验并规范化待上传文件 —— **不触碰文件系统**。
 *
 * 类型判定一律用 finfo 嗅探真实内容，不信任 $_FILES['type']（浏览器可伪造）。
 *
 * @param array $files normalizeUploadFiles() 的结果
 * @param array<string, string> $whitelist uploadWhitelist() 的结果
 * @return array<int, array{name:string,mime:string,ext:string,size:int,tmp_name:string}>
 */
function validateUploadFiles(array $files, array $whitelist): array
{
    if (!function_exists('finfo_open')) {
        serverError('Server configuration error: fileinfo extension not enabled. Please enable this extension and try again.');
    }

    $validated = [];
    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error(uploadErrorMessage($file['error']));
        }

        if ($file['size'] === 0) {
            error('Uploaded file is empty');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!is_string($mime) || !isset($whitelist[$mime])) {
            error(uploadTypeRejectionMessage($whitelist));
        }

        $validated[] = [
            'name'     => $file['name'],
            'mime'     => $mime,
            'ext'      => $whitelist[$mime],
            'size'     => $file['size'],
            'tmp_name' => $file['tmp_name'],
        ];
    }

    if (empty($validated)) {
        uploadMissingFileError();
    }
    return $validated;
}

/**
 * 建目录、落盘、写 pt_media。
 *
 * 目录结构沿用历史约定：UPLOAD_DIR/<Y>/<m>/<d>-<16位随机>.<ext>，
 * 对外路径固定为站点相对形式 upload/<Y>/<m>/<file>。
 *
 * @param array $validated validateUploadFiles() 的结果
 * @param int|null $userId 上传者 id（邮件发布等匿名场景为 null）
 * @return array<int, array{path:string,url:string}>
 */
function storeUploadedFiles(array $validated, ?int $userId): array
{
    $uploadRoot = rtrim(UPLOAD_DIR, '/\\');
    $relativeDir = date('Y') . '/' . date('m');
    $absoluteDir = $uploadRoot . '/' . $relativeDir;

    if (!is_dir($absoluteDir)) {
        if (!is_dir($uploadRoot) || !is_writable($uploadRoot)) {
            // 路径只写日志，不回显给客户端（避免泄漏服务器目录结构）
            error_log('[peachtrees] Upload directory is not writable: ' . $uploadRoot);
            serverError('Upload directory is not writable. Please check the upload directory permissions on the server.');
        }
        if (!@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            error_log('[peachtrees] Failed to create upload directory: ' . $absoluteDir);
            serverError('Failed to create the upload directory.');
        }
    }

    $saved = [];
    foreach ($validated as $item) {
        $filename = date('d') . '-' . bin2hex(random_bytes(8)) . '.' . $item['ext'];
        $absolutePath = $absoluteDir . '/' . $filename;
        $relativePath = 'upload/' . $relativeDir . '/' . $filename;

        if (!move_uploaded_file($item['tmp_name'], $absolutePath)) {
            error_log('[peachtrees] Failed to move uploaded file to ' . $absolutePath);
            serverError('Failed to save the uploaded file.');
        }

        // 落库：DB 失败不阻断上传，syncMediaLibrary() 会兜底补录
        try {
            $pdo = getDB();
            ensureMediaTable($pdo);
            addMediaRecord($pdo, $userId, $relativePath, $item['name'], $item['mime'], $item['size']);
        } catch (Throwable $e) {
            error_log('pt_media insert failed: ' . $e->getMessage());
        }

        $saved[] = [
            'path' => $relativePath,
            'url'  => $relativePath,
        ];
    }
    return $saved;
}

/**
 * 一个上传端点的完整流程（取文件 -> 校验 -> 建目录 -> 落盘 -> 落库）。
 *
 * @param string $profile 白名单档案名，见 uploadWhitelist()
 * @param int|null $userId 上传者 id，匿名场景传 null
 * @param string[] $fileKeys 依次尝试的 $_FILES 键名
 * @param int|null $maxFiles 单请求文件数上限（null = 不限）
 * @return array<int, array{path:string,url:string}>
 */
function processMediaUpload(string $profile, ?int $userId, array $fileKeys = ['files', 'file'], ?int $maxFiles = null): array
{
    $whitelist = uploadWhitelist($profile);
    $files = normalizeUploadFiles($fileKeys);

    if ($maxFiles !== null && count($files) > $maxFiles) {
        error('Too many files: at most ' . $maxFiles . ' can be uploaded per request');
    }

    return storeUploadedFiles(validateUploadFiles($files, $whitelist), $userId);
}
