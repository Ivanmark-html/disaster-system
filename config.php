<?php
session_start([
    'cookie_lifetime' => 900, // 15 min timeout
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Auto-logout inactive users
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 900)) {
    session_unset(); 
    session_destroy();
}
$_SESSION['last_activity'] = time();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database and Path Constants
define('DB_HOST', '127.0.0.1:3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'disaster_system');
define('UPLOAD_DIR', 'uploads/');

/**
 * Database Connection Setup
 */
function getDB() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

/**
 * Role-Based Access Control
 * Prevents unauthorized users from accessing specific dashboard types
 */
function requireRole($roles) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], (array)$roles)) {
        header('Location: login.php');
        exit;
    }
}

/**
 * CSRF Protection Verification
 */
function csrf_verify() {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
        die('CSRF token invalid');
    }
}

// Logical addition: Ensure authority_id is handled if user data is provided during login
// Note: This specific snippet usually goes inside your actual login.php logic, 
// but it is referenced here for context.
if (isset($user) && isset($user['id'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['authority_id'] = $user['authority_id'] ?? null; 
}
?>