<?php
/**
 * Database Configuration
 * 
 * SECURITY NOTE: This file contains sensitive credentials.
 * Ensure it's placed outside the web root or protected via .htaccess
 */

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_NAME', 'bommer_auth');  // Change to your database name
define('DB_USER', 'root');          // Change to your database user
define('DB_PASS', '');              // Change to your database password
define('DB_CHARSET', 'utf8mb4');

// PDO connection options for security and performance
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
];

/**
 * Get PDO database connection
 * 
 * @return PDO Database connection instance
 * @throws PDOException If connection fails
 */
function getDbConnection() {
    global $pdo_options;
    
    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error securely (don't expose to users)
        error_log('Database Connection Error: ' . $e->getMessage());
        
        // Return generic error to user
        throw new Exception('Database connection failed. Please contact the administrator.');
    }
}

/**
 * Get singleton PDO instance to avoid multiple connections
 */
function getDb() {
    static $pdo = null;
    
    if ($pdo === null) {
        $pdo = getDbConnection();
    }
    
    return $pdo;
}
