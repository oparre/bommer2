<?php
/**
 * Session Management
 * 
 * Secure session configuration and management functions
 */

// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Initialize secure session
 */
function initSecureSession() {
    // Session configuration for security
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Enable secure cookies if HTTPS is available
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Prevent session fixation
    ini_set('session.use_trans_sid', 0);
    
    // Set session name
    session_name('BOMMER_SESSION');
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate after 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Validate session fingerprint
    validateSessionFingerprint();
}

/**
 * Create session fingerprint to prevent session hijacking
 */
function createSessionFingerprint() {
    $fingerprint = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_prefix' => getIpPrefix()
    ];
    
    $_SESSION['fingerprint'] = hash('sha256', json_encode($fingerprint));
}

/**
 * Validate session fingerprint
 */
function validateSessionFingerprint() {
    if (!isset($_SESSION['user_id'])) {
        return true; // Not logged in, no need to validate
    }
    
    $current_fingerprint = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_prefix' => getIpPrefix()
    ];
    
    $current_hash = hash('sha256', json_encode($current_fingerprint));
    
    if (!isset($_SESSION['fingerprint']) || $_SESSION['fingerprint'] !== $current_hash) {
        // Fingerprint mismatch - possible session hijacking
        destroySession();
        return false;
    }
    
    return true;
}

/**
 * Get IP address prefix for fingerprinting (first 3 octets for IPv4)
 */
function getIpPrefix() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // For IPv4, use first 3 octets
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return implode('.', array_slice($parts, 0, 3));
    }
    
    // For IPv6, use first 64 bits
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 4));
    }
    
    return '';
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user is admin
 * 
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require login - redirect to login page if not logged in
 * 
 * @param string $redirect_url URL to redirect to after login
 */
function requireLogin($redirect_url = '/auth/login.php') {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Require admin access - redirect if not admin
 * 
 * @param string $redirect_url URL to redirect to
 */
function requireAdmin($redirect_url = '/auth/login.php') {
    requireLogin($redirect_url);
    
    if (!isAdmin()) {
        header('Location: /app.php');
        exit;
    }
}

/**
 * Set user session after successful login
 * 
 * @param array $user User data from database
 */
function setUserSession($user) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in_at'] = time();
    
    // Create session fingerprint
    createSessionFingerprint();
}

/**
 * Destroy session completely
 */
function destroySession() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Get current user ID
 * 
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 * 
 * @return string|null
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}
