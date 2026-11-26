<?php
/**
 * PeachtreesCMS API - Update Check
 * GET /api/update/check.php
 * Requires admin privileges.
 *
 * Compares the installed version against the latest release of the update
 * source. The release must ship a `release.zip` asset (produced by
 * scripts/package.js) — that asset is what update/apply.php installs.
 *
 * The update source defaults to taotaotao-studio/PeachtreesCMS and can be
 * overridden in .env with UPDATE_REPO="owner/repo".
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

requireAdmin();

define('PT_UPDATE_TIMEOUT', 15);

function updateRepo(): string {
    $repo = trim((string)($_ENV['UPDATE_REPO'] ?? ''));
    return $repo !== '' ? $repo : 'taotaotao-studio/PeachtreesCMS';
}

function normalizeVersion(string $version): string {
    return ltrim(trim($version), "vV \t\n\r\0\x0B");
}

/** HTTP GET (curl preferred, stream-context fallback). Returns body or null.
 *  Records the last HTTP status in $GLOBALS['pt_update_last_status']. */
function updateHttpGet(string $url, array $headers = []): ?string {
    $GLOBALS['pt_update_last_status'] = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => PT_UPDATE_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $GLOBALS['pt_update_last_status'] = $status;
        return ($body !== false && $status >= 200 && $status < 300) ? $body : null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => PT_UPDATE_TIMEOUT,
            'follow_location' => 1,
            'max_redirects' => 5,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $GLOBALS['pt_update_last_status'] = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $GLOBALS['pt_update_last_status'] = (int)$m[1];
        }
    }
    return $body === false ? null : $body;
}

/**
 * Current installed version: pt_options.pt_version (written at install/update
 * time), falling back to the code constant when the DB is unavailable.
 */
function currentInstalledVersion(): string {
    try {
        $stmt = getDB()->prepare("SELECT option_value FROM pt_options WHERE option_key = 'pt_version' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && trim((string)$row['option_value']) !== '') {
            return normalizeVersion((string)$row['option_value']);
        }
    } catch (Throwable $e) {
        // DB unavailable or option missing: fall back to the code constant.
    }
    return normalizeVersion(PT_VERSION);
}

try {
    $body = updateHttpGet(
        'https://api.github.com/repos/' . updateRepo() . '/releases/latest',
        [
            'Accept: application/vnd.github+json',
            'User-Agent: PeachtreesCMS-Updater/' . normalizeVersion(PT_VERSION),
        ]
    );
    if ($GLOBALS['pt_update_last_status'] === 404) {
        serverError('The repository has no releases yet. Publish the first GitHub Release (v* tag) to enable the updater.');
    }
    if ($body === null) {
        serverError("Could not reach the update source. Check the server's outbound network.");
    }
    $release = json_decode($body, true);
    if (!is_array($release) || empty($release['tag_name'])) {
        serverError('Update source returned an unexpected response.');
    }

    $zipUrl = null;
    foreach (($release['assets'] ?? []) as $asset) {
        if (($asset['name'] ?? '') === 'release.zip' && !empty($asset['browser_download_url'])) {
            $zipUrl = (string)$asset['browser_download_url'];
            break;
        }
    }

    $current = currentInstalledVersion();
    $latest = normalizeVersion((string)$release['tag_name']);

    success([
        'current_version' => $current,
        'latest_version' => $latest,
        'update_available' => $zipUrl !== null && version_compare($current, $latest) === -1,
        'release_url' => (string)($release['html_url'] ?? ''),
        'zip_url' => $zipUrl,
        'published_at' => (string)($release['published_at'] ?? ''),
        'notes' => mb_substr(trim(strip_tags((string)($release['body'] ?? ''))), 0, 2000),
    ], 'Check complete');
} catch (Throwable $e) {
    error_log('[peachtrees] update check failed: ' . $e->getMessage());
    serverError('Update check failed.');
}
