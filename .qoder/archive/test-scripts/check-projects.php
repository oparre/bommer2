<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDb();
    
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM projects");
    $count = $stmt->fetch()['cnt'];
    
    echo "Total projects: $count\n\n";
    
    if ($count > 0) {
        echo "Sample projects:\n";
        $stmt = $pdo->query("SELECT id, code, name, status FROM projects LIMIT 10");
        while ($row = $stmt->fetch()) {
            echo "  ID: {$row['id']}, Code: {$row['code']}, Name: {$row['name']}, Status: {$row['status']}\n";
        }
    } else {
        echo "No projects found in database!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
