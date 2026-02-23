<?php
/**
 * Fix Admin Password
 * Updates the admin user password to 'Admin@123' and resets failed login attempts
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDb();
    
    // Generate correct password hash for 'Admin@123'
    $correct_hash = password_hash('Admin@123', PASSWORD_DEFAULT);
    
    echo "Updating admin user...\n";
    
    // Update admin user with correct password hash and reset failed attempts
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = :password_hash,
            failed_login_attempts = 0,
            locked_until = NULL
        WHERE username = 'admin'
    ");
    
    $stmt->execute([':password_hash' => $correct_hash]);
    
    if ($stmt->rowCount() > 0) {
        echo "✓ SUCCESS - Admin password updated to 'Admin@123'\n";
        echo "✓ Failed login attempts reset to 0\n";
        echo "✓ Account lock removed\n";
        echo "\nYou can now login with:\n";
        echo "  Username: admin\n";
        echo "  Password: Admin@123\n";
    } else {
        echo "✗ FAILED - Admin user not found or no changes made\n";
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}
