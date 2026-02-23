<?php
require_once __DIR__ . '/api/index.php';

try {
    $pdo = getDb();
    
    echo "=== BOM_ITEMS TABLE STRUCTURE ===\n\n";
    
    $stmt = $pdo->query('SHOW CREATE TABLE bom_items');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $result['Create Table'] . "\n\n";
    
    echo "=== CHECKING FOREIGN KEY CONSTRAINTS ===\n\n";
    
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'bommer_auth'
        AND TABLE_NAME = 'bom_items'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($constraints as $constraint) {
        echo "Constraint: {$constraint['CONSTRAINT_NAME']}\n";
        echo "  Column: {$constraint['COLUMN_NAME']}\n";
        echo "  References: {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
