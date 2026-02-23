<?php
/**
 * Test Password Hash
 * Verify if the password hash in the database is correct
 */

$password = 'Admin@123';
$hash_from_schema = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

echo "Testing password: 'Admin@123'\n";
echo "Hash from schema.sql:\n";
echo $hash_from_schema . "\n\n";

echo "Verification test: ";
if (password_verify($password, $hash_from_schema)) {
    echo "✓ PASS - Password matches the hash\n";
} else {
    echo "✗ FAIL - Password does NOT match the hash\n";
}

echo "\nGenerating correct hash for 'Admin@123':\n";
echo password_hash($password, PASSWORD_DEFAULT) . "\n";

// Test database connection
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDb();
    echo "\n✓ Database connection successful\n";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Users table exists\n";
        
        // Check if admin user exists
        $stmt = $pdo->prepare("SELECT username, password_hash, is_active, failed_login_attempts, locked_until FROM users WHERE username = 'admin'");
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user) {
            echo "✓ Admin user found in database\n";
            echo "  - Username: " . $user['username'] . "\n";
            echo "  - Is Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
            echo "  - Failed Attempts: " . $user['failed_login_attempts'] . "\n";
            echo "  - Locked Until: " . ($user['locked_until'] ?? 'Not locked') . "\n";
            echo "  - Password Hash: " . $user['password_hash'] . "\n\n";
            
            echo "Testing stored hash:\n";
            if (password_verify('Admin@123', $user['password_hash'])) {
                echo "✓ PASS - Stored password hash is correct for 'Admin@123'\n";
            } else {
                echo "✗ FAIL - Stored password hash does NOT match 'Admin@123'\n";
                echo "  The password in the database may have been changed.\n";
            }
        } else {
            echo "✗ Admin user NOT found in database\n";
            echo "  You need to run the schema.sql file to create the admin user.\n";
        }
    } else {
        echo "✗ Users table does NOT exist\n";
        echo "  You need to run the schema.sql file to create the database tables.\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ Database error: " . $e->getMessage() . "\n";
}
