<?php
/**
 * Direct API test for compare endpoint
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$pdo = getDb();
$_GET['ids'] = '10,12';

echo "<h2>Testing compareBOMs function directly</h2>";
echo "<p>IDs: 10, 12</p>";

// Manually call the function to see the error
try {
    $idsParam = $_GET['ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $idsParam)));
    
    echo "<p>Parsed IDs: " . implode(', ', $ids) . "</p>";
    
    if (empty($ids)) {
        die('No BOM IDs provided');
    }
    
    $boms = [];
    foreach ($ids as $id) {
        echo "<h3>Processing BOM ID: $id</h3>";
        
        // Get BOM basic info
        $stmt = $pdo->prepare(
            "SELECT b.*, p.name as project_name, p.code as project_code,
                    br.status as current_status, br.notes as revision_notes,
                    u.username as created_by_username, u.full_name as created_by_name
             FROM boms b
             JOIN projects p ON b.project_id = p.id
             LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
             JOIN users u ON b.created_by = u.id
             WHERE b.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $bom = $stmt->fetch();
        
        if (!$bom) {
            echo "<p style='color: red;'>BOM not found</p>";
            continue;
        }
        
        echo "<p>✅ BOM found</p>";
        
        // Get current revision details
        $stmt = $pdo->prepare(
            "SELECT * FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision"
        );
        $stmt->execute([
            ':bom_id' => $id,
            ':revision' => $bom['current_revision']
        ]);
        $revision = $stmt->fetch();
        
        if (!$revision) {
            echo "<p style='color: red;'>Revision not found</p>";
            continue;
        }
        
        echo "<p>✅ Revision found (ID: {$revision['id']})</p>";
        
        // Get groups
        $stmt = $pdo->prepare(
            "SELECT g.*, 
                    COUNT(bi.id) as item_count
             FROM bom_groups g
             LEFT JOIN bom_items bi ON g.id = bi.group_id
             WHERE g.revision_id = :revision_id
             GROUP BY g.id
             ORDER BY g.display_order"
        );
        $stmt->execute([':revision_id' => $revision['id']]);
        $groups = $stmt->fetchAll();
        
        echo "<p>✅ Found " . count($groups) . " groups</p>";
        
        // Process each group
        foreach ($groups as &$group) {
            $stmt = $pdo->prepare(
                "SELECT bi.*, 
                        COALESCE(c.part_number, ec.part_number) AS part_number,
                        COALESCE(c.name, ec.name) AS component_name,
                        COALESCE(c.description, ec.description) AS description,
                        COALESCE(c.category, ec.category) AS category,
                        COALESCE(c.unit_cost, ec.unit_cost) AS unit_cost,
                        bi.unit AS unit
                 FROM bom_items bi
                 LEFT JOIN components c ON bi.component_id = c.id AND bi.component_source = 'bommer'
                 LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
                 WHERE bi.group_id = :group_id
                 ORDER BY bi.display_order"
            );
            $stmt->execute([':group_id' => $group['id']]);
            $items = $stmt->fetchAll();
            
            $group['items'] = $items;
            echo "<p>  Group '{$group['name']}': " . count($items) . " items</p>";
        }
        
        $bom['groups'] = $groups;
        $boms[] = $bom;
    }
    
    echo "<hr>";
    echo "<h3>Success! BOMs fetched:</h3>";
    echo "<pre>";
    echo "Total BOMs: " . count($boms) . "\n";
    foreach ($boms as $bom) {
        echo "- {$bom['sku']}: {$bom['name']} (" . count($bom['groups']) . " groups)\n";
    }
    echo "</pre>";
    
    echo "<hr>";
    echo "<h3>JSON Output:</h3>";
    echo "<pre>";
    echo json_encode(['success' => true, 'data' => $boms], JSON_PRETTY_PRINT);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>";
    echo "<pre>";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
    echo "</pre>";
}
