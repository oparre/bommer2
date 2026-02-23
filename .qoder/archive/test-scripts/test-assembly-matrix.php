<?php
/**
 * Find assemblies and test matrix access
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';

// Start session
session_start();

// Set a test user if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
}

$pdo = getDb();

echo "<h1>Assembly Matrix Access</h1>\n";
echo "<pre>";

// Get all assemblies
$stmt = $pdo->query("SELECT id, name, code FROM assemblies ORDER BY name");
$assemblies = $stmt->fetchAll();

echo "Available Assemblies:\n";
foreach ($assemblies as $assembly) {
    echo "ID: {$assembly['id']}, Name: {$assembly['name']}, Code: {$assembly['code']}\n";
}

echo "\n";

// Look for DP1 assembly
$dp1Assembly = null;
foreach ($assemblies as $assembly) {
    if (stripos($assembly['name'], 'DP1') !== false || stripos($assembly['code'], 'DP1') !== false) {
        $dp1Assembly = $assembly;
        break;
    }
}

if ($dp1Assembly) {
    echo "Found DP1 assembly: ID {$dp1Assembly['id']}, Name: {$dp1Assembly['name']}\n";
    $assemblyId = $dp1Assembly['id'];
} else {
    echo "No DP1 assembly found.\n";
    if (!empty($assemblies)) {
        echo "Using first assembly for testing.\n";
        $assemblyId = $assemblies[0]['id'];
        echo "Assembly ID: $assemblyId\n";
    } else {
        echo "No assemblies in database.\n";
        exit;
    }
}

echo "\n=== Testing Matrix Access ===\n";

// Test the matrix API call
$apiUrl = "/api/boms.php?action=matrix&scope=assembly&id=$assemblyId";
echo "API URL: http://bommer.local$apiUrl\n";

// Make the API call
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Cookie: ' . session_name() . '=' . session_id()
        ]
    ]
]);

$response = @file_get_contents('http://bommer.local' . $apiUrl, false, $context);

if ($response === false) {
    echo "ERROR: Failed to call API\n";
    $error = error_get_last();
    echo "Error: " . $error['message'] . "\n";
} else {
    echo "API Response received (length: " . strlen($response) . " bytes)\n";
    
    // Try to parse JSON
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "SUCCESS: Matrix data retrieved\n";
        echo "Scope: {$data['data']['scope']}\n";
        echo "Scope Name: {$data['data']['scope_name']}\n";
        echo "Number of BOMs: " . count($data['data']['boms']) . "\n";
        echo "Number of Components: " . count($data['data']['components']) . "\n";
        
        if (count($data['data']['boms']) > 0) {
            echo "\nBOMs in matrix:\n";
            foreach ($data['data']['boms'] as $bom) {
                echo "- {$bom['sku']}: {$bom['name']} (Status: {$bom['status']})\n";
            }
        }
    } else {
        echo "ERROR: Failed to parse API response\n";
        if ($data && isset($data['error'])) {
            echo "API Error: {$data['error']}\n";
        } else {
            echo "Raw response (first 500 chars):\n";
            echo substr($response, 0, 500) . "\n";
        }
    }
}

echo "\n=== Direct Browser Access Links ===\n";
echo "Matrix Page URL: http://bommer.local/matrix.html?scope=assembly&id=$assemblyId\n";
echo "App Router URL: http://bommer.local/app.php#/assemblies/$assemblyId/matrix\n";

echo "</pre>";

echo "<p><a href='http://bommer.local/matrix.html?scope=assembly&id=$assemblyId' target='_blank'>Open Matrix Page</a></p>";
echo "<p><a href='http://bommer.local/app.php#/assemblies/$assemblyId/matrix' target='_blank'>Open in App Router</a></p>";
?>