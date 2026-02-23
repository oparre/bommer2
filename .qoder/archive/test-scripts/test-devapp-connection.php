<?php
/**
 * Test devapp API endpoint
 */

// Test if we can connect to devapp database
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain');

echo "=== Testing devapp Connection ===\n\n";

try {
    $devappPdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=project_management;charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✓ Connection successful\n\n";
    
    echo "=== Fetching projects ===\n\n";
    $stmt = $devappPdo->query("SELECT id, name, status FROM projects LIMIT 3");
    $projects = $stmt->fetchAll();
    
    foreach ($projects as $p) {
        echo "ID: {$p['id']}, Name: {$p['name']}, Status: {$p['status']}\n";
    }
    
    echo "\n✓ Query successful\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
