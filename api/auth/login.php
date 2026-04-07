<?php
/**
 * PeachtreesCMS API - User Login
 * POST /api/auth/login.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../password.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
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
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM pt_users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        error('User does not exist');
    }

    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        error('Incorrect password');
    }

    // Set Session
    $_SESSION['uid'] = $user['id'];
    $_SESSION['user'] = $user['username'];

    // Update login time
    $updateStmt = $pdo->prepare("UPDATE pt_users SET last_login_at = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    // Return success
    success([
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email']
    ], 'Login successful');

} catch (PDOException $e) {
    serverError('Login failed: ' . $e->getMessage());
}
