<?php
/**
 * Validate Login
 * 
 * Process login form submission with:
 * - CSRF protection
 * - Brute force protection (account lockout after 5 failed attempts)
 * - Remember me functionality (secure selector/validator pattern)
 * - Secure password verification
 */

// Define secure access
define('SECURE_ACCESS', true);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize secure session
initSecureSession();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auth/login.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    redirectWithMessage('/auth/login.php', 'Invalid security token. Please try again.', 'error');
}

// Get and sanitize inputs
$username = sanitizeString($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

// Validate inputs
if (empty($username) || empty($password)) {
    redirectWithMessage('/auth/login.php', 'Username and password are required.', 'error');
}

if (!validateUsername($username)) {
    redirectWithMessage('/auth/login.php', 'Invalid username format.', 'error');
}

try {
    $pdo = getDb();
    
    // Check if account is locked due to failed attempts
    $lock_status = checkAccountLock($pdo, $username);
    
    if ($lock_status['locked']) {
        $unlock_time = date('H:i', strtotime($lock_status['until']));
        redirectWithMessage(
            '/auth/login.php',
            "Account is temporarily locked due to multiple failed login attempts. Please try again after {$unlock_time}.",
            'error'
        );
    }
    
    // Fetch user from database
    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash, full_name, role, is_active 
         FROM users 
         WHERE username = :username"
    );
    
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    // User not found or password incorrect
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Record failed attempt for brute force protection
        recordFailedAttempt($pdo, $username);
        
        // Generic error message to prevent username enumeration
        redirectWithMessage(
            '/auth/login.php',
            'Invalid username or password.',
            'error'
        );
    }
    
    // Check if user account is active
    if (!$user['is_active']) {
        redirectWithMessage(
            '/auth/login.php',
            'Your account has been disabled. Please contact the administrator.',
            'error'
        );
    }
    
    // Check if password needs rehashing (if algorithm improved)
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare(
            "UPDATE users SET password_hash = :password_hash WHERE id = :id"
        );
        
        $stmt->execute([
            ':password_hash' => $new_hash,
            ':id' => $user['id']
        ]);
    }
    
    // Reset failed login attempts
    resetFailedAttempts($pdo, $username);
    
    // Update last login timestamp
    updateLastLogin($pdo, $user['id']);
    
    // Set user session
    setUserSession($user);
    
    // Handle "Remember Me" functionality
    if ($remember_me) {
        createRememberToken($pdo, $user['id']);
    }
    
    // Redirect to intended page or default dashboard
    $redirect = $_SESSION['redirect_after_login'] ?? '/app.php';
    unset($_SESSION['redirect_after_login']);
    
    // All users go to main app dashboard
    if (!isset($_POST['redirect_after_login'])) {
        $redirect = '/app.php';
    }
    
    redirectWithMessage($redirect, 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!', 'success');
    
} catch (Exception $e) {
    // Log error securely
    error_log('Login Error: ' . $e->getMessage());
    
    // Generic error message to user
    redirectWithMessage(
        '/auth/login.php',
        'An error occurred during login. Please try again.',
        'error'
    );
}
