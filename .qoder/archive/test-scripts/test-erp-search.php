<?php
/**
 * Test ERP Component Search
 * Direct test of ERP component search functionality
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDb();

echo "=== Testing ERP Component Search ===\n\n";

// Test 1: Check if erp_components table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'erp_components'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "✓ erp_components table exists\n";
    } else {
        echo "✗ erp_components table does NOT exist\n";
        exit;
    }
} catch (Exception $e) {
    echo "✗ Error checking table: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Count total ERP components
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM erp_components");
    $result = $stmt->fetch();
    echo "✓ Total ERP components: " . $result['total'] . "\n\n";
    
    if ($result['total'] == 0) {
        echo "⚠ No ERP components in database. Please run:\n";
        echo "  database/erp-components-schema.sql\n";
        echo "  database/erp-components-seed.sql\n\n";
        exit;
    }
} catch (Exception $e) {
    echo "✗ Error counting components: " . $e->getMessage() . "\n";
    exit;
}

// Test 3: Sample ERP components
echo "=== Sample ERP Components ===\n";
try {
    $stmt = $pdo->query("SELECT id, part_number, name, category, status FROM erp_components LIMIT 5");
    $samples = $stmt->fetchAll();
    foreach ($samples as $comp) {
        echo sprintf("  ID: %d | PN: %s | Name: %s | Category: %s | Status: %s\n",
            $comp['id'], $comp['part_number'], $comp['name'], 
            $comp['category'] ?? 'N/A', $comp['status']);
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error fetching samples: " . $e->getMessage() . "\n";
}

// Test 4: Test search with 'RES' (resistors)
echo "=== Testing Search: 'RES' ===\n";
try {
    $searchTerm = '%RES%';
    $stmt = $pdo->prepare(
        "SELECT id, part_number, name, category, status 
         FROM erp_components 
         WHERE (part_number LIKE :search1 OR name LIKE :search2 OR description LIKE :search3 OR mpn LIKE :search4)
         LIMIT 10"
    );
    $stmt->execute([
        ':search1' => $searchTerm,
        ':search2' => $searchTerm,
        ':search3' => $searchTerm,
        ':search4' => $searchTerm
    ]);
    $results = $stmt->fetchAll();
    
    echo "Found " . count($results) . " components matching 'RES':\n";
    foreach ($results as $comp) {
        echo sprintf("  ID: %d | PN: %s | Name: %s | Category: %s | Status: %s\n",
            $comp['id'], $comp['part_number'], $comp['name'], 
            $comp['category'] ?? 'N/A', $comp['status']);
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error in search: " . $e->getMessage() . "\n";
}

// Test 5: Test API endpoint directly
echo "=== Testing API Endpoint ===\n";
$testUrl = 'http://localhost/api/components.php?source=erp&search=RES&limit=5';
echo "URL: $testUrl\n";
echo "Note: Run this in a browser with valid session, or use curl with cookies\n\n";

echo "=== Test Complete ===\n";
