<?php
/**
 * PeachtreesCMS API - User Login
 * POST /api/auth/login.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../password.php';
require_once __DIR__ . '/../security.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// Brute-force protection: max 10 failed logins per IP per 10 minutes.
// Only FAILURES are recorded — a successful login never consumes the budget.
$rateKey = 'login:' . ptClientIp();
if (!ptRateLimitCheck($rateKey, 10, 600)) {
    error('Too many failed login attempts. Please try again later.', 429);
}

// Get request parameters
$input = getJsonInput();
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    error('Username and password cannot be empty');
}

try {
    $pdo = getDB();
    
    // Query user
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM pt_users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        ptRateLimitRecord($rateKey, 600);
        error('Invalid username or password');
    }

    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        ptRateLimitRecord($rateKey, 600);
        error('Invalid username or password');
    }

    // Regenerate session ID to prevent session fixation attack
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // Set Session
    $_SESSION['uid'] = $user['id'];
    $_SESSION['user'] = $user['username'];
    $_SESSION['role'] = (int)($user['role'] ?? 2);

    // Update login time
    $updateStmt = $pdo->prepare("UPDATE pt_users SET last_login_at = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    // Return success
    success([
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => (int)($user['role'] ?? 2)
    ], 'Login successful');

} catch (PDOException $e) {
    error_log('[peachtrees] Login failed: ' . $e->getMessage());
    serverError('Login failed');
}
