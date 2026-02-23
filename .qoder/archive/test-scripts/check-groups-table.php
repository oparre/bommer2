<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDb();
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'bom_component_groups'");
    $tableExists = $stmt->fetch();
    
    echo "Table 'bom_component_groups' exists: " . ($tableExists ? 'YES' : 'NO') . "\n\n";
    
    if ($tableExists) {
        // Check structure
        echo "Table structure:\n";
        $stmt = $pdo->query("DESCRIBE bom_component_groups");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  {$row['Field']}: {$row['Type']}\n";
        }
        
        echo "\nRow count: ";
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM bom_component_groups");
        echo $stmt->fetch()['cnt'] . "\n";
        
        echo "\nSample data:\n";
        $stmt = $pdo->query("SELECT * FROM bom_component_groups LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  ID: {$row['id']}, Name: {$row['name']}, Icon: {$row['icon']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
