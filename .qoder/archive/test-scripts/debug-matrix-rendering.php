<?php
/**
 * Check assemblies and debug matrix rendering
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDb();

echo "<h1>Assembly and Matrix Debug</h1>\n";
echo "<pre>";

// Get all assemblies
$stmt = $pdo->query("SELECT id, name, code FROM assemblies ORDER BY name");
$assemblies = $stmt->fetchAll();

echo "All Assemblies:\n";
foreach ($assemblies as $assembly) {
    echo "ID: {$assembly['id']}, Name: {$assembly['name']}, Code: {$assembly['code']}\n";
}

echo "\n";

// Check if there's an assembly with DP1
$dp1Found = false;
foreach ($assemblies as $assembly) {
    if (stripos($assembly['name'], 'DP1') !== false || stripos($assembly['code'], 'DP1') !== false) {
        echo "Found DP1 assembly: ID {$assembly['id']}, Name: {$assembly['name']}\n";
        $dp1Found = true;
        $dp1Id = $assembly['id'];
        break;
    }
}

if (!$dp1Found) {
    echo "No assembly with 'DP1' found.\n";
    if (!empty($assemblies)) {
        echo "Using first assembly (ID: {$assemblies[0]['id']}) for testing.\n";
        $testId = $assemblies[0]['id'];
    } else {
        echo "No assemblies found in database.\n";
        exit;
    }
} else {
    $testId = $dp1Id;
}

echo "\n=== Testing Matrix API Call ===\n";

// Test direct API call
$url = "http://localhost/bommer/api/boms.php?action=matrix&scope=assembly&id=$testId";
echo "Calling: $url\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Cookie: ' . session_name() . '=' . session_id()
        ]
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "ERROR: Failed to call API\n";
    $error = error_get_last();
    echo "Error: " . $error['message'] . "\n";
} else {
    echo "Raw API Response:\n";
    echo $response . "\n";
    
    // Try to decode JSON
    $data = json_decode($response, true);
    if ($data) {
        echo "\nDecoded JSON:\n";
        print_r($data);
    } else {
        echo "\nFailed to decode JSON. Response may contain PHP errors.\n";
    }
}

echo "\n=== Checking matrix.html for PHP code ===\n";

// Read matrix.html and check for PHP code at the end
$content = file_get_contents(__DIR__ . '/matrix.html');
$lines = explode("\n", $content);
$totalLines = count($lines);

echo "matrix.html has $totalLines lines\n";

// Check last 20 lines for PHP code
echo "\nLast 20 lines of matrix.html:\n";
for ($i = max(0, $totalLines - 20); $i < $totalLines; $i++) {
    $lineNum = $i + 1;
    echo sprintf("%4d: %s\n", $lineNum, $lines[$i]);
}

// Look for PHP opening tags
if (strpos($content, '<?php') !== false) {
    echo "\nWARNING: Found PHP code in matrix.html!\n";
    $phpPositions = [];
    $pos = 0;
    while (($pos = strpos($content, '<?php', $pos)) !== false) {
        $phpPositions[] = $pos;
        $pos += 5;
    }
    echo "PHP tags found at positions: " . implode(', ', $phpPositions) . "\n";
}

echo "</pre>";
?>