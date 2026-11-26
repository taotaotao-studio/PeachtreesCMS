<?php
/**
 * PeachtreesCMS API - Security helpers (rate limiting)
 *
 * File-based fixed-window rate limiter. Counters live in pt_api/rate_limits/
 * (override with RATE_LIMIT_DIR in .env), which is protected from web access
 * the same way as pt_api/sessions: the root .htaccess / nginx rules deny
 * pt_api/(sessions|rate_limits), and a deny-all .htaccess guard is written
 * into the directory as a second layer.
 */

if (!function_exists('ptRateLimitDir')) {
    function ptRateLimitDir(): string {
        global $projectRoot;
        $dir = trim((string)($_ENV['RATE_LIMIT_DIR'] ?? ''));
        if ($dir !== '') {
            if (!preg_match('/^(\/|[a-zA-Z]:[\/\\\\])/', $dir)) {
                $dir = $projectRoot . '/' . $dir;
            }
        } else {
            $dir = __DIR__ . '/rate_limits';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir)) {
            $guard = $dir . '/.htaccess';
            if (!file_exists($guard)) {
                @file_put_contents($guard,
                    "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
                    "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
            }
        }
        return $dir;
    }
}

if (!function_exists('ptClientIp')) {
    /** Client IP for rate-limit keys. REMOTE_ADDR only — never trust proxy headers. */
    function ptClientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

if (!function_exists('ptRateLimitCheck')) {
    /**
     * Fixed-window limiter. Returns true when the action is still ALLOWED
     * (fewer than $max attempts recorded within the current window).
     * Does NOT record anything — pair failures with ptRateLimitRecord().
     */
    function ptRateLimitCheck(string $key, int $max, int $windowSec): bool {
        $file = ptRateLimitDir() . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $data = ['count' => 0, 'start' => $now];
        if (is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            if (is_array($decoded) && isset($decoded['count'], $decoded['start'])) {
                if ($now - (int)$decoded['start'] > $windowSec) {
                    // Window expired — start a fresh one
                    @unlink($file);
                    return true;
                }
                $data = $decoded;
            }
        }
        return (int)$data['count'] < $max;
    }
}

if (!function_exists('ptRateLimitRecord')) {
    /** Record one attempt for $key (failures only, by convention). */
    function ptRateLimitRecord(string $key, int $windowSec): void {
        $file = ptRateLimitDir() . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $data = ['count' => 0, 'start' => $now];
        if (is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            if (is_array($decoded) && isset($decoded['count'], $decoded['start']) && $now - (int)$decoded['start'] <= $windowSec) {
                $data = $decoded;
            }
        }
        $data['count'] = (int)$data['count'] + 1;
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
