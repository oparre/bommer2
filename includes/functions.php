<?php
/**
 * Security and Helper Functions
 * 
 * CSRF protection, validation, sanitization, and other security utilities
 */

// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Use hash_equals to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF hidden input field
 * 
 * @return string HTML input field
 */
function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// ============================================================================
// INPUT VALIDATION & SANITIZATION
// ============================================================================

/**
 * Sanitize string input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeString($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate username format
 * 
 * @param string $username Username to validate
 * @return bool True if valid
 */
function validateUsername($username) {
    // 3-50 characters, alphanumeric and underscore only
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

/**
 * Validate password strength
 * 
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    $result = ['valid' => false, 'message' => ''];
    
    // Minimum 8 characters
    if (strlen($password) < 8) {
        $result['message'] = 'Password must be at least 8 characters long';
        return $result;
    }
    
    // Maximum 255 characters (bcrypt limit)
    if (strlen($password) > 255) {
        $result['message'] = 'Password must not exceed 255 characters';
        return $result;
    }
    
    // Require at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $result['message'] = 'Password must contain at least one uppercase letter';
        return $result;
    }
    
    // Require at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $result['message'] = 'Password must contain at least one lowercase letter';
        return $result;
    }
    
    // Require at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $result['message'] = 'Password must contain at least one number';
        return $result;
    }
    
    // Require at least one special character
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $result['message'] = 'Password must contain at least one special character';
        return $result;
    }
    
    $result['valid'] = true;
    return $result;
}

// ============================================================================
// REMEMBER ME FUNCTIONALITY (Secure Selector/Validator Pattern)
// ============================================================================

/**
 * Create remember me token for user
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool Success status
 */
function createRememberToken($pdo, $user_id) {
    try {
        // Generate cryptographically secure random values
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        
        // Hash the validator before storing (like password hashing)
        $validator_hash = password_hash($validator, PASSWORD_DEFAULT);
        
        // Token expires in 30 days
        $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
        
        // Store in database
        $stmt = $pdo->prepare(
            "INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) 
             VALUES (:user_id, :selector, :validator_hash, :expires_at)"
        );
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':selector' => $selector,
            ':validator_hash' => $validator_hash,
            ':expires_at' => $expires_at
        ]);
        
        // Set cookie with selector:validator (not hashed)
        $token_value = $selector . ':' . $validator;
        setcookie(
            'remember_me',
            $token_value,
            time() + (30 * 24 * 60 * 60), // 30 days
            '/',
            '',
            isset($_SERVER['HTTPS']), // Secure flag if HTTPS
            true // HTTP only
        );
        
        return true;
        
    } catch (Exception $e) {
        error_log('Remember Token Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Validate and process remember me token
 * 
 * @param PDO $pdo Database connection
 * @return array|null User data if valid, null otherwise
 */
function validateRememberToken($pdo) {
    if (!isset($_COOKIE['remember_me'])) {
        return null;
    }
    
    try {
        // Split token into selector and validator
        $token_parts = explode(':', $_COOKIE['remember_me'], 2);
        
        if (count($token_parts) !== 2) {
            deleteRememberToken($pdo);
            return null;
        }
        
        list($selector, $validator) = $token_parts;
        
        // Clean up expired tokens first
        cleanupExpiredTokens($pdo);
        
        // Find token by selector
        $stmt = $pdo->prepare(
            "SELECT rt.*, u.id, u.username, u.full_name, u.role, u.is_active
             FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.id
             WHERE rt.selector = :selector AND rt.expires_at > NOW()"
        );
        
        $stmt->execute([':selector' => $selector]);
        $token_data = $stmt->fetch();
        
        if (!$token_data) {
            deleteRememberToken($pdo);
            return null;
        }
        
        // Verify validator hash
        if (!password_verify($validator, $token_data['validator_hash'])) {
            // Invalid validator - possible token theft, delete all user tokens
            deleteAllUserTokens($pdo, $token_data['user_id']);
            deleteRememberToken($pdo);
            return null;
        }
        
        // Check if user is still active
        if (!$token_data['is_active']) {
            deleteRememberToken($pdo);
            return null;
        }
        
        // Valid token - regenerate for security (token rotation)
        deleteRememberToken($pdo, $selector);
        createRememberToken($pdo, $token_data['user_id']);
        
        // Return user data
        return [
            'id' => $token_data['id'],
            'username' => $token_data['username'],
            'full_name' => $token_data['full_name'],
            'role' => $token_data['role']
        ];
        
    } catch (Exception $e) {
        error_log('Validate Remember Token Error: ' . $e->getMessage());
        deleteRememberToken($pdo);
        return null;
    }
}

/**
 * Delete remember me token (logout)
 * 
 * @param PDO $pdo Database connection
 * @param string|null $selector Specific selector to delete
 */
function deleteRememberToken($pdo = null, $selector = null) {
    // Delete cookie
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    
    // Delete from database if PDO provided
    if ($pdo && $selector) {
        try {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = :selector");
            $stmt->execute([':selector' => $selector]);
        } catch (Exception $e) {
            error_log('Delete Remember Token Error: ' . $e->getMessage());
        }
    } elseif ($pdo && isset($_COOKIE['remember_me'])) {
        $token_parts = explode(':', $_COOKIE['remember_me'], 2);
        if (count($token_parts) === 2) {
            try {
                $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = :selector");
                $stmt->execute([':selector' => $token_parts[0]]);
            } catch (Exception $e) {
                error_log('Delete Remember Token Error: ' . $e->getMessage());
            }
        }
    }
}

/**
 * Delete all remember tokens for a user
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 */
function deleteAllUserTokens($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
    } catch (Exception $e) {
        error_log('Delete All User Tokens Error: ' . $e->getMessage());
    }
}

/**
 * Clean up expired remember tokens
 * 
 * @param PDO $pdo Database connection
 */
function cleanupExpiredTokens($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (Exception $e) {
        error_log('Cleanup Expired Tokens Error: ' . $e->getMessage());
    }
}

// ============================================================================
// BRUTE FORCE PROTECTION
// ============================================================================

/**
 * Check if account is locked due to failed login attempts
 * 
 * @param PDO $pdo Database connection
 * @param string $username Username to check
 * @return array ['locked' => bool, 'until' => datetime|null]
 */
function checkAccountLock($pdo, $username) {
    try {
        $stmt = $pdo->prepare(
            "SELECT failed_login_attempts, locked_until 
             FROM users 
             WHERE username = :username"
        );
        
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['locked' => false, 'until' => null];
        }
        
        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return [
                'locked' => true,
                'until' => $user['locked_until']
            ];
        }
        
        // If lock has expired, reset it
        if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
            resetFailedAttempts($pdo, $username);
        }
        
        return ['locked' => false, 'until' => null];
        
    } catch (Exception $e) {
        error_log('Check Account Lock Error: ' . $e->getMessage());
        return ['locked' => false, 'until' => null];
    }
}

/**
 * Record failed login attempt
 * 
 * @param PDO $pdo Database connection
 * @param string $username Username
 */
function recordFailedAttempt($pdo, $username) {
    try {
        // Increment failed attempts
        $stmt = $pdo->prepare(
            "UPDATE users 
             SET failed_login_attempts = failed_login_attempts + 1 
             WHERE username = :username"
        );
        
        $stmt->execute([':username' => $username]);
        
        // Check if account should be locked (5 failed attempts)
        $stmt = $pdo->prepare(
            "SELECT failed_login_attempts FROM users WHERE username = :username"
        );
        
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && $user['failed_login_attempts'] >= 5) {
            // Lock account for 15 minutes
            $lock_until = date('Y-m-d H:i:s', time() + (15 * 60));
            
            $stmt = $pdo->prepare(
                "UPDATE users 
                 SET locked_until = :locked_until 
                 WHERE username = :username"
            );
            
            $stmt->execute([
                ':locked_until' => $lock_until,
                ':username' => $username
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Record Failed Attempt Error: ' . $e->getMessage());
    }
}

/**
 * Reset failed login attempts after successful login
 * 
 * @param PDO $pdo Database connection
 * @param string $username Username
 */
function resetFailedAttempts($pdo, $username) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE users 
             SET failed_login_attempts = 0, locked_until = NULL 
             WHERE username = :username"
        );
        
        $stmt->execute([':username' => $username]);
        
    } catch (Exception $e) {
        error_log('Reset Failed Attempts Error: ' . $e->getMessage());
    }
}

// ============================================================================
// USER MANAGEMENT
// ============================================================================

/**
 * Update user's last login timestamp
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 */
function updateLastLogin($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE users SET last_login = NOW() WHERE id = :user_id"
        );
        
        $stmt->execute([':user_id' => $user_id]);
        
    } catch (Exception $e) {
        error_log('Update Last Login Error: ' . $e->getMessage());
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Redirect with message
 * 
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear flash message
 * 
 * @return array ['message' => string, 'type' => string] or null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return $message;
    }
    
    return null;
}

/**
 * Generate random password
 * 
 * @param int $length Password length
 * @return string Random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    // Ensure at least one of each required character type
    $password .= chr(rand(65, 90));  // Uppercase
    $password .= chr(rand(97, 122)); // Lowercase
    $password .= chr(rand(48, 57));  // Number
    $password .= $chars[rand(62, strlen($chars) - 1)]; // Special char
    
    // Fill remaining length
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}
