<?php
/**
 * Test Optional Projects Search
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/config/database.php';

$pdo = getDb();

echo "<h1>Testing Optional Projects Search with 'asm'</h1>\n\n";

// Test the exact query used in the API
$searchTerm = '%asm%';
$sql = "SELECT p.code, p.name, p.description, p.is_optional
        FROM projects p
        WHERE p.is_optional = 1 
        AND (p.code LIKE ? OR p.name LIKE ? OR p.description LIKE ?)
        ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Results: " . count($results) . " optional projects found</h2>\n";
echo "<pre>";
print_r($results);
echo "</pre>";

// Also check all optional projects
echo "\n<hr>\n<h2>All Optional Projects (for comparison)</h2>\n";
$sql2 = "SELECT code, name, is_optional FROM projects WHERE is_optional = 1 ORDER BY name ASC";
$stmt2 = $pdo->query($sql2);
$allOptionals = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Total optional projects: " . count($allOptionals) . "</p>";
echo "<pre>";
print_r($allOptionals);
echo "</pre>";
