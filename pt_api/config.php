<?php
/**
 * PeachtreesCMS API - Database Configuration
 * Uses PDO to connect to MySQL
 * Sensitive information is read from .env file
 */

// Load environment variables from .env file
function loadEnv($file) {
    if (!file_exists($file)) {
        return false;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comment lines
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove surrounding quotes
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }

            // Set to $_ENV and putenv
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    return true;
}

// Load .env file — optional override for production config
// If .env is absent, built-in defaults below will be used
$searchDirs = [__DIR__];
$parent = __DIR__;
for ($i = 0; $i < 3; $i++) {
    $next = dirname($parent);
    if ($next === $parent) break; // reached filesystem root
    $searchDirs[] = $next;
    $parent = $next;
}

// .env found anywhere in parent chain will override defaults
foreach ($searchDirs as $dir) {
    if (loadEnv($dir . '/.env')) {
        break;
    }
}

// .env.local in pt_api/ overrides for local development
loadEnv(__DIR__ . '/.env.local');

// Database configuration — defaults used when .env is absent
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'peachtrees');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Timezone setting
date_default_timezone_set('Asia/Shanghai');

// Force output encoding to UTF-8, avoid 0x00 bytes from UTF-16/UTF-32 output
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('pass');
}
if (function_exists('mb_regex_encoding')) {
    mb_regex_encoding('UTF-8');
}
ini_set('mbstring.encoding_translation', '0');
// mbstring.http_output is deprecated (PHP 8.2+), avoid triggering Deprecated output
ini_set('mbstring.func_overload', '0');
ini_set('output_handler', '');
ini_set('zlib.output_compression', '0');

// ---------------------------------------------------------------------------
// Content directory configuration ("wp-content" model)
//
// All user-mutable content lives under ONE directory (default: content/ under
// the project root):
//   content/uploads/    attachments      (was upload/)
//   content/themes/     theme packages   (was theme/)
//   content/patterns/   page styles      (was pattern/)
//
// CONTENT_DIR overrides the whole content root (absolute path, or relative to
// the project root). The legacy UPLOAD_DIR / THEME_DIR / STYLE_DIR overrides
// still take precedence for their own subtree (separated deployments).
// ---------------------------------------------------------------------------
$projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

if (!function_exists('ptResolveContentDir')) {
    /** Resolve a directory setting: absolute kept as-is, relative resolved against $projectRoot. */
    function ptResolveContentDir(string $value, string $defaultAbs): string {
        $value = rtrim($value, '/\\');
        if ($value === '') {
            return $defaultAbs;
        }
        if (!preg_match('/^(\/|[a-zA-Z]:[\/\\\\])/', $value)) {
            global $projectRoot;
            $value = $projectRoot . '/' . $value;
        }
        return rtrim($value, '/\\');
    }
}

$contentRoot = ptResolveContentDir($_ENV['CONTENT_DIR'] ?? '', $projectRoot . '/content');
define('CONTENT_DIR', $contentRoot);

$uploadDir = ptResolveContentDir($_ENV['UPLOAD_DIR'] ?? '', CONTENT_DIR . '/uploads');
define('UPLOAD_DIR', $uploadDir . '/');

$themeDir = ptResolveContentDir($_ENV['THEME_DIR'] ?? '', CONTENT_DIR . '/themes');
define('THEME_DIR', $themeDir);

$styleDir = ptResolveContentDir($_ENV['STYLE_DIR'] ?? '', CONTENT_DIR . '/patterns');
define('STYLE_DIR', $styleDir);

// Site-relative path prefixes returned by backend APIs — the single source of
// truth for the path contract with the frontend (pt_frontend/src/utils/path.js).
define('UPLOAD_REL_PREFIX', 'content/uploads');
define('THEMES_REL_PREFIX', 'content/themes');
define('PATTERNS_REL_PREFIX', 'content/patterns');

// CMS version (shown in the admin panel; the updater compares it against the
// latest GitHub release tag)
define('PT_VERSION', '0.9.0');

// Upload URL configuration
// ⚠️ DEPRECATED (historical only): since the path convention update, backend APIs
// return SITE-RELATIVE paths (e.g. content/uploads/2026/04/x.jpg) and the frontend
// converts them via pt_frontend/src/utils/path.js. Do NOT use UPLOAD_URL /
// UPLOAD_URL_BASE to build absolute upload URLs anymore.
// For shared hosting in subdirectory, set UPLOAD_URL_BASE environment variable
// Example: /PeachtreesCMS/content/uploads/
$uploadUrlBase = $_ENV['UPLOAD_URL_BASE'] ?? '';
if (!empty($uploadUrlBase)) {
    // Use custom URL base from environment
    define('UPLOAD_URL', rtrim($uploadUrlBase, '/') . '/');
} else {
    // Default: try to detect from request
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    // Remove /pt_api/... from path to get base directory
    // Example: /PeachtreesCMS/pt_api/media => /PeachtreesCMS
    $baseDir = preg_replace('#/pt_api(/.*)?$#i', '', $scriptDir);
    if ($baseDir === '' || $baseDir === '/') {
        define('UPLOAD_URL', '/' . UPLOAD_REL_PREFIX . '/');
    } else {
        define('UPLOAD_URL', rtrim($baseDir, '/') . '/' . UPLOAD_REL_PREFIX . '/');
    }
}

// Create PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    // Session storage directory.
    //
    // SECURITY: session files contain a PLAINTEXT captcha answer, and the file
    // name is the session id — if the web server ever serves this directory as
    // static content, the captcha is bypassed entirely. Three layers guard it:
    //
    //   1. the web-server rules in <site root>/.htaccess and the nginx example
    //      (both deny /pt_api/sessions/);
    //   2. the deny-all .htaccess auto-written into the directory below;
    //   3. this SESSION_DIR override, which lets production move the directory
    //      OUTSIDE the web root altogether (strongest option).
    //
    // Set SESSION_DIR in .env to an absolute path (recommended: a sibling of
    // the site root, e.g. /var/www/php/sessions) or a path relative to the
    // project root. Defaults to pt_api/sessions for backwards compatibility.
    $sessionPath = $_ENV['SESSION_DIR'] ?? '';
    if ($sessionPath === '') {
        $sessionPath = __DIR__ . '/sessions';
    } else {
        $sessionPath = rtrim($sessionPath, '/\\');
        if (!preg_match('/^(\/|[a-zA-Z]:[\/\\\\])/', $sessionPath)) {
            $sessionPath = $projectRoot . '/' . $sessionPath;
        }
    }

    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }

    // Layer 2: drop a deny-all .htaccess so the files are never handed out as
    // static content, even on a host whose root .htaccess does not apply.
    // (Ignored harmlessly when AllowOverride forbids authentication directives.)
    if (is_dir($sessionPath)) {
        $sessionGuard = $sessionPath . '/.htaccess';
        if (!file_exists($sessionGuard)) {
            @file_put_contents($sessionGuard,
                "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
    }

    if (is_dir($sessionPath)) {
        session_save_path($sessionPath);
    }

    // Set secure session cookie parameters
    // secure: true when HTTPS is detected, ensures cookies only sent over HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
