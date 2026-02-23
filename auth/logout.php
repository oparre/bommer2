<?php
/**
 * Logout
 * 
 * Destroy session and remove remember me tokens
 */

// Define secure access
define('SECURE_ACCESS', true);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize secure session
initSecureSession();

// Delete remember me token if exists
try {
    $pdo = getDb();
    deleteRememberToken($pdo);
} catch (Exception $e) {
    error_log('Logout Error: ' . $e->getMessage());
}

// Destroy session
destroySession();

// Redirect to login page with message
session_start(); // Start new session just for the flash message
redirectWithMessage('/auth/login.php', 'You have been logged out successfully.', 'success');
