<?php
require_once __DIR__ . '/config/database.php';

$pdo = getDb();

// Get all assemblies
$stmt = $pdo->query("SELECT id, name, code FROM assemblies ORDER BY name");
$assemblies = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode([
    'assemblies' => $assemblies,
    'count' => count($assemblies)
]);
?>