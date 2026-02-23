<?php
/**
 * Test Optional Projects Search API
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = getDb();

echo "<h1>Testing Optional Projects Search API</h1>\n";

// Test 1: All optional projects (no search)
echo "<h2>Test 1: All Optional Projects (no search filter)</h2>\n";
$sql = "SELECT p.*, 
               u1.full_name as owner_name,
               COUNT(DISTINCT b.id) as bom_count
        FROM projects p
        LEFT JOIN users u1 ON p.owner_id = u1.id
        LEFT JOIN boms b ON p.id = b.project_id
        WHERE p.is_optional = 1
        GROUP BY p.id
        ORDER BY p.name ASC";
$stmt = $pdo->query($sql);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Results: " . count($all) . " optional projects</p>\n";
echo "<pre>" . print_r($all, true) . "</pre>\n";

// Test 2: Search with 'asm'
echo "<hr><h2>Test 2: Optional Projects matching 'asm'</h2>\n";
$searchTerm = '%asm%';
$sql = "SELECT p.*, 
               u1.full_name as owner_name,
               COUNT(DISTINCT b.id) as bom_count
        FROM projects p
        LEFT JOIN users u1 ON p.owner_id = u1.id
        LEFT JOIN boms b ON p.id = b.project_id
        WHERE p.is_optional = 1 
        AND (p.code LIKE ? OR p.name LIKE ? OR p.description LIKE ?)
        GROUP BY p.id
        ORDER BY p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Results: " . count($matches) . " optional projects</p>\n";
echo "<pre>" . print_r($matches, true) . "</pre>\n";

// Test 3: Check all projects and their is_optional status
echo "<hr><h2>Test 3: All Projects (with is_optional flag)</h2>\n";
$sql = "SELECT id, code, name, is_optional FROM projects ORDER BY name ASC";
$stmt = $pdo->query($sql);
$allProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Total projects: " . count($allProjects) . "</p>\n";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>is_optional</th></tr>\n";
foreach ($allProjects as $p) {
    $optionalClass = $p['is_optional'] ? 'style="background-color: #cfc;"' : '';
    echo "<tr $optionalClass><td>{$p['id']}</td><td>{$p['code']}</td><td>{$p['name']}</td><td>{$p['is_optional']}</td></tr>\n";
}
echo "</table>\n";
