<?php
/**
 * Find assembly DP1 and access its matrix
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDb();

echo "<h1>Finding Assembly DP1</h1>\n";
echo "<pre>";

// Search for assemblies with DP1 in name or code
$stmt = $pdo->prepare("SELECT id, name, code FROM assemblies WHERE name LIKE ? OR code LIKE ?");
$searchTerm = '%DP1%';
$stmt->execute([$searchTerm, $searchTerm]);
$results = $stmt->fetchAll();

echo "Assemblies matching 'DP1':\n";
print_r($results);

if (empty($results)) {
    echo "\nNo assemblies found with 'DP1'. Checking all assemblies:\n";
    $stmt = $pdo->query("SELECT id, name, code FROM assemblies ORDER BY name");
    $allAssemblies = $stmt->fetchAll();
    print_r($allAssemblies);
    
    // Try to find any assembly to test the matrix
    if (!empty($allAssemblies)) {
        $firstAssembly = $allAssemblies[0];
        echo "\nUsing first assembly for testing: {$firstAssembly['name']} (ID: {$firstAssembly['id']})\n";
        
        // Test the matrix API
        echo "\n=== Testing Matrix API ===\n";
        $url = "/api/boms.php?action=matrix&scope=assembly&id={$firstAssembly['id']}";
        echo "API URL: $url\n";
        
        // Simulate the API call
        $_GET['action'] = 'matrix';
        $_GET['scope'] = 'assembly';
        $_GET['id'] = $firstAssembly['id'];
        
        ob_start();
        require_once __DIR__ . '/api/boms.php';
        $output = ob_get_clean();
        echo "API Response:\n$output\n";
    }
} else {
    $dp1Assembly = $results[0];
    echo "\nFound assembly: {$dp1Assembly['name']} (ID: {$dp1Assembly['id']})\n";
    
    // Test the matrix API for DP1
    echo "\n=== Testing Matrix API for DP1 ===\n";
    $url = "/api/boms.php?action=matrix&scope=assembly&id={$dp1Assembly['id']}";
    echo "API URL: $url\n";
    
    // Simulate the API call
    $_GET['action'] = 'matrix';
    $_GET['scope'] = 'assembly';
    $_GET['id'] = $dp1Assembly['id'];
    
    ob_start();
    require_once __DIR__ . '/api/boms.php';
    $output = ob_get_clean();
    echo "API Response:\n$output\n";
}

echo "</pre>";
?>