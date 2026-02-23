<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDb();
    
    echo "Checking created_by references in bom_component_groups:\n\n";
    
    $stmt = $pdo->query("SELECT id, name, created_by FROM bom_component_groups");
    $groups = $stmt->fetchAll();
    
    foreach ($groups as $group) {
        // Check if user exists
        $stmt2 = $pdo->prepare("SELECT id, username FROM users WHERE id = :id");
        $stmt2->execute([':id' => $group['created_by']]);
        $user = $stmt2->fetch();
        
        if ($user) {
            echo "✓ Group '{$group['name']}' -> created_by={$group['created_by']} (user: {$user['username']})\n";
        } else {
            echo "✗ Group '{$group['name']}' -> created_by={$group['created_by']} (USER NOT FOUND!)\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
