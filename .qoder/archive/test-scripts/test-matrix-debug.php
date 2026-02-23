<?php
/**
 * Debug Matrix API Call
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';

// Start session
session_start();

// Set a test user if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
}

echo "<h1>Matrix API Debug</h1>";
echo "<pre>";

$pdo = getDb();

// Test Project ID 6
$projectId = 6;

echo "Testing Project ID: $projectId\n\n";

// Step 1: Get project info
echo "=== STEP 1: Get Project Info ===\n";
$stmt = $pdo->prepare("SELECT name, code FROM projects WHERE id = :id");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();
var_dump($project);
echo "\n";

// Step 2: Get BOMs for project
echo "=== STEP 2: Get BOMs for Project ===\n";
$stmt = $pdo->prepare(
    "SELECT b.id, b.sku, b.name, b.description, b.current_revision,
            br.status, br.notes as revision_notes
     FROM boms b
     JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
     WHERE b.project_id = :project_id
     ORDER BY b.sku
     LIMIT 10"
);
$stmt->execute([':project_id' => $projectId]);
$boms = $stmt->fetchAll();
echo "Found " . count($boms) . " BOMs\n";
var_dump($boms);
echo "\n";

if (count($boms) < 2) {
    echo "ERROR: Need at least 2 BOMs for matrix view\n";
    exit;
}

// Step 3: Get components for BOMs
echo "=== STEP 3: Get Components for BOMs ===\n";
$bomIds = array_column($boms, 'id');
echo "BOM IDs: " . implode(', ', $bomIds) . "\n\n";

$placeholders = implode(',', array_fill(0, count($bomIds), '?'));

try {
    $stmt = $pdo->prepare(
        "SELECT b.id as bom_id, b.sku as bom_sku,
                g.name as group_name, g.display_order as group_order,
                bi.quantity, bi.unit_cost, bi.notes,
                bi.display_order as item_order,
                COALESCE(c.part_number, ec.part_number) AS part_number,
                COALESCE(c.name, ec.name) AS component_name,
                COALESCE(c.description, ec.description) AS description,
                COALESCE(c.unit, ec.unit, 'pcs') AS unit,
                COALESCE(c.manufacturer, ec.manufacturer) AS manufacturer,
                COALESCE(c.mpn, ec.mpn) AS mpn
         FROM bom_items bi
         JOIN bom_groups g ON bi.group_id = g.id
         JOIN bom_revisions br ON g.revision_id = br.id
         JOIN boms b ON br.bom_id = b.id AND br.revision_number = b.current_revision
         LEFT JOIN components c ON bi.component_id = c.id AND bi.component_source = 'bommer'
         LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
         WHERE b.id IN ($placeholders)
         ORDER BY b.sku, g.display_order, bi.display_order"
    );
    
    $stmt->execute($bomIds);
    $components = $stmt->fetchAll();
    
    echo "Found " . count($components) . " component entries\n";
    var_dump($components);
    
} catch (Exception $e) {
    echo "ERROR in Step 3: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
