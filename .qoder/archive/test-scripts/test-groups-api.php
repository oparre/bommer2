<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/includes/session.php';
initSecureSession();
requireLogin();

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDb();
    
    // Test the exact query from the API
    $sql = "SELECT g.*, u.username as created_by_username, u.full_name as created_by_name
            FROM bom_component_groups g
            JOIN users u ON g.created_by = u.id
            WHERE is_active = 1
            ORDER BY g.display_order, g.name";
    
    echo "Executing query...\n";
    $stmt = $pdo->query($sql);
    $groups = $stmt->fetchAll();
    
    echo "Success! Found " . count($groups) . " groups:\n";
    foreach ($groups as $group) {
        echo "  - {$group['name']} (created by {$group['created_by_username']})\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
