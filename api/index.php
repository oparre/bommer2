<?php
/**
 * API Base Configuration
 * 
 * Common configuration and utilities for all API endpoints
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize session
initSecureSession();

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/**
 * Send JSON response
 */
function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400, $details = null) {
    $response = ['error' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    sendJson($response, $statusCode);
}

/**
 * Send success response
 */
function sendSuccess($data = null, $message = null) {
    $response = ['success' => true];
    if ($message !== null) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendJson($response);
}

/**
 * Require authentication for API
 */
function requireApiAuth() {
    if (!isLoggedIn()) {
        sendError('Authentication required', 401);
    }
}

/**
 * Require admin for API
 */
function requireApiAdmin() {
    requireApiAuth();
    if (!isAdmin()) {
        sendError('Admin access required', 403);
    }
}

/**
 * Get JSON input
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON input', 400);
    }
    
    return $data;
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendError('Missing required fields: ' . implode(', ', $missing), 400);
    }
}

/**
 * Log audit event
 */
function logAudit($pdo, $action, $entity_type, $entity_id, $details = null) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
             VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent)"
        );
        
        $stmt->execute([
            ':user_id' => getCurrentUserId(),
            ':action' => $action,
            ':entity_type' => $entity_type,
            ':entity_id' => $entity_id,
            ':details' => $details ? json_encode($details) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('Audit Log Error: ' . $e->getMessage());
    }
}
