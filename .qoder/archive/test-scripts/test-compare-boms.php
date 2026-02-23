<?php
/**
 * Diagnostic script to test BOM comparison
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDb();
$ids = [10, 12];

echo "<h2>Testing BOM Comparison for IDs: " . implode(', ', $ids) . "</h2>";

foreach ($ids as $id) {
    echo "<h3>BOM ID: $id</h3>";
    
    // Check if BOM exists
    $stmt = $pdo->prepare("SELECT * FROM boms WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $bom = $stmt->fetch();
    
    if (!$bom) {
        echo "<p style='color: red;'>❌ BOM not found</p>";
        continue;
    }
    
    echo "<p>✅ BOM found: {$bom['sku']} - {$bom['name']}</p>";
    echo "<p>Current revision: {$bom['current_revision']}</p>";
    
    // Check if revision exists
    $stmt = $pdo->prepare("SELECT * FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision");
    $stmt->execute([':bom_id' => $id, ':revision' => $bom['current_revision']]);
    $revision = $stmt->fetch();
    
    if (!$revision) {
        echo "<p style='color: red;'>❌ Revision not found for revision number {$bom['current_revision']}</p>";
        
        // Check what revisions exist
        $stmt = $pdo->prepare("SELECT * FROM bom_revisions WHERE bom_id = :bom_id");
        $stmt->execute([':bom_id' => $id]);
        $allRevisions = $stmt->fetchAll();
        
        if (empty($allRevisions)) {
            echo "<p style='color: orange;'>⚠️ No revisions exist for this BOM</p>";
        } else {
            echo "<p>Available revisions:</p><ul>";
            foreach ($allRevisions as $rev) {
                echo "<li>Revision {$rev['revision_number']} (ID: {$rev['id']}, Status: {$rev['status']})</li>";
            }
            echo "</ul>";
        }
        continue;
    }
    
    echo "<p>✅ Revision found: ID {$revision['id']}, Status: {$revision['status']}</p>";
    
    // Check groups
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bom_groups WHERE revision_id = :revision_id");
    $stmt->execute([':revision_id' => $revision['id']]);
    $groupCount = $stmt->fetch()['count'];
    
    echo "<p>Groups: $groupCount</p>";
    
    // Check items
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM bom_items bi 
        JOIN bom_groups g ON bi.group_id = g.id 
        WHERE g.revision_id = :revision_id
    ");
    $stmt->execute([':revision_id' => $revision['id']]);
    $itemCount = $stmt->fetch()['count'];
    
    echo "<p>Items: $itemCount</p>";
}

echo "<hr>";
echo "<p><a href='/'>Back to app</a></p>";
