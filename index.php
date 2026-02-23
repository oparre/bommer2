<?php
/**
 * Main Entry Point for Bommer Application
 * 
 * Handles authentication redirect and app initialization
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// Initialize session
initSecureSession();

// Check if user is logged in
if (!isLoggedIn()) {
    // Try remember-me token
    $pdo = getDb();
    $user = validateRememberToken($pdo);
    
    if ($user) {
        // Auto-login via remember token
        setUserSession($user);
        updateLastLogin($pdo, $user['id']);
    } else {
        // Redirect to login
        header('Location: /auth/login.php');
        exit;
    }
}

// User is authenticated, redirect to app
header('Location: /app.php');
exit;
