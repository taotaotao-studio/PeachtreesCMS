<?php
/**
 * PeachtreesCMS API - Authentication Middleware
 * Uses Session for authentication
 *
 * The database is the SINGLE authority for identity and role (fail-closed):
 * a session that no longer maps to a live `pt_users` row is treated as NOT
 * logged in, and a database failure likewise yields "not logged in". The
 * `role` kept in the session is NEVER trusted as an authority.
 */

require_once __DIR__ . '/config.php';

/**
 * Resolve the current user from the session, consulting the database.
 *
 * Rules (fail-closed — the DB is authoritative for identity AND role):
 *   1. No `uid` in the session           -> null (not logged in).
 *   2. Database row found                -> return the DB values and sync the
 *                                           session identity keys
 *                                           (`uid`/`user`/`role`) so a rename
 *                                           or a role change takes effect on
 *                                           the very next request.
 *   3. Query OK but no such row         -> clear the identity keys and return
 *                                           null (e.g. the user was deleted).
 *   4. Query throws (DB unavailable)    -> log and return null. The session
 *                                           role is never used as a fallback.
 *
 * Session keys are fixed: `uid` / `user` / `role` (see `auth/login.php`).
 *
 * @return array|null User info `{id, username, nickname, email, role}` or null.
 */
function getCurrentUser(): ?array {
    if (!isset($_SESSION['uid'])) {
        return null;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, username, nickname, email, role FROM pt_users WHERE id = ?");
        $stmt->execute([$_SESSION['uid']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            // Database is authoritative: keep the session identity keys in sync
            // so a username or role change applies on the next request.
            $_SESSION['uid'] = $user['id'];
            $_SESSION['user'] = $user['username'];
            $_SESSION['role'] = (int)($user['role'] ?? 2);
            return [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'] ?: $user['username'],
                'email' => $user['email'],
                'role' => (int)($user['role'] ?? 2)
            ];
        }

        // Query succeeded but there is no such user -> treat as logged out.
        // Clear ONLY the identity keys (the session may still hold other data,
        // e.g. `captcha`, so it must not be destroyed wholesale).
        unset($_SESSION['uid'], $_SESSION['user'], $_SESSION['role']);
        return null;
    } catch (Throwable $e) {
        // Database unavailable -> fail closed. Never trust the session role.
        error_log('[auth] getCurrentUser DB failure: ' . $e->getMessage());
        return null;
    }
}

/**
 * Require user to be logged in
 * Returns 401 error if not logged in
 * @return array User info
 */
function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        require_once __DIR__ . '/response.php';
        unauthorized('Please login first');
    }
    return $user;
}

/**
 * Check if user is admin (role = 1)
 * @return bool
 */
function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && (int)($user['role'] ?? 2) === 1;
}

/**
 * Require admin privileges
 * Returns 403 error if not admin
 */
function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        require_once __DIR__ . '/response.php';
        forbidden('Admin privileges required');
    }
}
