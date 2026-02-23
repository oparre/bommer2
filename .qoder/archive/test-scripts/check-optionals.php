<?php
/**
 * Quick check of optional projects data
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$pdo = getDb();

// Get all optional projects
$stmt = $pdo->query("SELECT id, code, name, SUBSTRING(description, 1, 50) as description_preview, is_optional 
                     FROM projects 
                     WHERE is_optional = 1 
                     ORDER BY name ASC");
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Search for 'asm'
$searchTerm = '%asm%';
$stmt = $pdo->prepare("SELECT id, code, name, SUBSTRING(description, 1, 50) as description_preview, is_optional 
                       FROM projects 
                       WHERE is_optional = 1 
                       AND (code LIKE ? OR name LIKE ? OR description LIKE ?)
                       ORDER BY name ASC");
$stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'total_optional_projects' => count($all),
    'matches_for_asm' => count($matches),
    'all_optionals' => $all,
    'asm_matches' => $matches
], JSON_PRETTY_PRINT);
