<?php
/**
 * PeachtreesCMS API - Update Apply
 * POST /api/update/apply.php
 * Requires admin privileges.
 *
 * Downloads the latest release.zip from the update source — the URL is
 * re-resolved server-side, the client cannot choose one (no SSRF surface) —
 * verifies the archive, then copies the program files over the installation.
 *
 * NEVER touched / overwritten:
 *   content/uploads/**                                  user attachments
 *   pt_api/.env, pt_api/.env.local, pt_api/.installed   local config & state
 *   pt_api/sessions/**                                  runtime sessions
 * content/themes/** and content/patterns/** are ADDITIVE: existing slug
 * directories are preserved (user-modified packages survive updates); new
 * packages ship with the release.
 *
 * Requires: ZipArchive (php-zip), outbound HTTP. Uses a temp directory OUTSIDE
 * the web root, so nothing extracted is ever web-accessible.
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

requireAdmin();

@set_time_limit(300);

/** Which entries MUST be inside the archive (mirrors scripts/package.js). */
const UPDATE_REQUIRED_ENTRIES = [
    '.htaccess',
    'index.html',
    'admin.html',
    'content/uploads/.gitkeep',
    'content/themes/default/style.css',
    'pt_api/install.php',
    'pt_api/config.php',
    'pt_api/.env.example',
];

/** Entry-name regexes that must NEVER appear (mirrors scripts/package.js). */
const UPDATE_FORBIDDEN_ENTRIES = [
    '/(^|\/)\.env$/',
    '/(^|\/)\.env\.(?!example$)[^\/]+$/',
    '/(^|\/)\.installed$/',
    '/(^|\/)pt_api\/sessions\/(?!\.htaccess$)[^\/]+$/',
];

function updateRepo(): string {
    $repo = trim((string)($_ENV['UPDATE_REPO'] ?? ''));
    return $repo !== '' ? $repo : 'taotaotao-studio/PeachtreesCMS';
}

function normalizeVersion(string $version): string {
    return ltrim(trim($version), "vV \t\n\r\0\x0B");
}

/**
 * Resolve the latest release. Returns [tag => string, zipUrl => string|null].
 */
function fetchLatestRelease(): array {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/vnd.github+json\r\n"
                . 'User-Agent: PeachtreesCMS-Updater/' . normalizeVersion(PT_VERSION),
            'timeout' => 15,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
    ]);
    $body = @file_get_contents('https://api.github.com/repos/' . updateRepo() . '/releases/latest', false, $context);
    if ($body === false && function_exists('curl_init')) {
        // stream_context may be blocked (allow_url_fopen=0); retry with curl
        $ch = curl_init('https://api.github.com/repos/' . updateRepo() . '/releases/latest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: PeachtreesCMS-Updater/' . normalizeVersion(PT_VERSION),
            ],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
    }
    if (!is_string($body) || $body === '') {
        throw new RuntimeException("Could not reach the update source. Check the server's outbound network.");
    }
    $release = json_decode($body, true);
    if (!is_array($release) || empty($release['tag_name'])) {
        throw new RuntimeException('Update source returned an unexpected response.');
    }
    $zipUrl = null;
    foreach (($release['assets'] ?? []) as $asset) {
        if (($asset['name'] ?? '') === 'release.zip' && !empty($asset['browser_download_url'])) {
            $zipUrl = (string)$asset['browser_download_url'];
            break;
        }
    }
    if ($zipUrl === null) {
        throw new RuntimeException('The latest release does not contain a release.zip package.');
    }
    return ['tag' => normalizeVersion((string)$release['tag_name']), 'zipUrl' => $zipUrl];
}

/** Download a file to $destFile. curl downloads streaming to disk. */
function updateDownloadToFile(string $url, string $destFile): bool {
    $headers = [
        'User-Agent: PeachtreesCMS-Updater/' . normalizeVersion(PT_VERSION),
        'Accept: application/octet-stream',
    ];
    if (function_exists('curl_init')) {
        $fh = fopen($destFile, 'wb');
        if ($fh === false) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fh);
        return $ok !== false && $status >= 200 && $status < 300;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 300,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
    ]);
    // Fallback loads the whole archive into memory — fine for small releases.
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return false;
    }
    return @file_put_contents($destFile, $body) !== false;
}

function updateShouldSkip(string $rel, array $skipRel): bool {
    foreach ($skipRel as $skip) {
        if ($rel === $skip || str_starts_with($rel, $skip . '/')) {
            return true;
        }
    }
    return false;
}

/**
 * Recursively copy $root/$srcDir into $dstDir.
 *  - $skipRel:    relative paths whose entry (and subtree) is never copied;
 *  - $additiveRel: parent dirs whose EXISTING children are preserved — new
 *    slug directories arrive, user-modified ones are never overwritten.
 * Appends failed entries to $errors (non-fatal warnings).
 */
function updateCopyTree(string $root, string $srcDir, string $dstDir, array $skipRel, array $additiveRel, array &$errors): void {
    $entries = scandir($srcDir);
    if ($entries === false) {
        $errors[] = 'scandir failed: ' . str_replace('\\', '/', substr($srcDir, strlen($root) + 1));
        return;
    }
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $srcPath = $srcDir . '/' . $name;
        $rel = str_replace('\\', '/', substr($srcPath, strlen($root) + 1));
        $dstPath = $dstDir . '/' . $name;

        if (updateShouldSkip($rel, $skipRel)) {
            continue;
        }

        // Additive subtree: preserve any slug directory that already exists
        $parentRel = substr($rel, 0, (int)strrpos($rel, '/'));
        if (in_array($parentRel, $additiveRel, true) && file_exists($dstPath)) {
            continue;
        }

        if (is_dir($srcPath)) {
            if (!is_dir($dstPath) && !@mkdir($dstPath, 0755, true) && !is_dir($dstPath)) {
                $errors[] = 'mkdir failed: ' . $rel;
                continue;
            }
            updateCopyTree($root, $srcPath, $dstPath, $skipRel, $additiveRel, $errors);
        } elseif (!@copy($srcPath, $dstPath)) {
            $errors[] = 'copy failed: ' . $rel;
        }
    }
}

/** Best-effort recursive delete (temp dirs only). */
function updateRmTree(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_dir($path)) {
            updateRmTree($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

$projectRoot = dirname(__DIR__, 2); // pt_api/update/../.. = project root
$tmpZip = sys_get_temp_dir() . '/pt_update_' . bin2hex(random_bytes(8)) . '.zip';
$extractDir = sys_get_temp_dir() . '/pt_update_extract_' . bin2hex(random_bytes(8));

try {
    // 0. Quick writability pre-check (a read-only install can never be updated)
    if (!is_writable($projectRoot) || !is_writable($projectRoot . '/pt_api')) {
        serverError('Installation directory is not writable. Fix permissions before updating.');
    }
    if (!class_exists('ZipArchive')) {
        serverError('PHP zip extension (ZipArchive) is required for the updater.');
    }

    // 1. Resolve the latest package server-side
    $release = fetchLatestRelease();

    // 2. Download into a private temp path OUTSIDE the web root
    if (!updateDownloadToFile($release['zipUrl'], $tmpZip) || !is_file($tmpZip)) {
        throw new RuntimeException('Failed to download the update package.');
    }

    // 3. Verify the archive (required entries present, forbidden entries absent)
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        throw new RuntimeException('The downloaded package is not a valid ZIP archive.');
    }
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = (string)$zip->getNameIndex($i);
    }
    foreach (UPDATE_REQUIRED_ENTRIES as $required) {
        if (!in_array($required, $names, true)) {
            throw new RuntimeException('Package verification failed: missing ' . $required);
        }
    }
    foreach ($names as $name) {
        foreach (UPDATE_FORBIDDEN_ENTRIES as $forbidden) {
            if (preg_match($forbidden, $name)) {
                throw new RuntimeException('Package verification failed: forbidden entry ' . $name);
            }
        }
    }

    // 4. Extract to a private temp dir
    if (!@mkdir($extractDir, 0755, true) && !is_dir($extractDir)) {
        throw new RuntimeException('Failed to create the extraction directory.');
    }
    if (!$zip->extractTo($extractDir)) {
        throw new RuntimeException('Failed to extract the update package.');
    }
    $zip->close();

    // 5. Copy program files over the installation.
    //    content/uploads, sessions and local config/state are never touched;
    //    content/themes + content/patterns are additive per slug.
    $errors = [];
    updateCopyTree(
        $extractDir,
        $extractDir,
        $projectRoot,
        ['content/uploads', 'pt_api/sessions', 'pt_api/.env', 'pt_api/.env.local', 'pt_api/.installed'],
        ['content/themes', 'content/patterns'],
        $errors
    );

    // 6. Record the new version (PT_VERSION in the freshly copied config.php
    //    already matches; the option keeps a durable copy for the updater).
    try {
        $stmt = getDB()->prepare(
            "INSERT INTO pt_options (option_key, option_value) VALUES ('pt_version', ?) "
            . 'ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)'
        );
        $stmt->execute([$release['tag']]);
    } catch (Throwable $dbErr) {
        error_log('[peachtrees] pt_version option write failed: ' . $dbErr->getMessage());
    }

    // 7. Best-effort cache reset so the next request runs the new code
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    updateRmTree($extractDir);

    success([
        'version' => $release['tag'],
        'errors' => $errors,
    ], $errors ? 'Update installed with warnings' : 'Update installed successfully');
} catch (Throwable $e) {
    error_log('[peachtrees] update apply failed: ' . $e->getMessage());
    updateRmTree($extractDir);
    @unlink($tmpZip);
    serverError($e->getMessage());
} finally {
    // Belt-and-braces cleanup — never leave the archive in the temp dir
    if (is_file($tmpZip)) {
        @unlink($tmpZip);
    }
}
