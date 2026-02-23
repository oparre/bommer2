<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// Mock session for CLI
$_SESSION['user_id'] = 1; // Assuming 1 is a valid user ID

$pdo = getDb();
$data = [
    'code' => 'TEST-PROJ-' . time(),
    'name' => 'Test Project via Script',
    'description' => 'A test project to verify API functionality',
    'status' => 'planning',
    'priority' => 'medium',
    'is_optional' => 0
];

// We can't easily call handlePost directly because it uses getJsonInput() which reads from php://input
// But we can simulate the DB insertion logic or use a mock request.
// However, the best way to test the API is via a real HTTP request if possible, or just checking the logic.

// Let's just check if the project exists after we try to insert it.
try {
    $stmt = $pdo->prepare(
        "INSERT INTO projects (code, name, description, status, priority, is_optional, owner_id, created_by)
         VALUES (:code, :name, :description, :status, :priority, :is_optional, :owner_id, :created_by)"
    );
    
    $stmt->execute([
        ':code' => $data['code'],
        ':name' => $data['name'],
        ':description' => $data['description'],
        ':status' => $data['status'],
        ':priority' => $data['priority'],
        ':is_optional' => $data['is_optional'],
        ':owner_id' => 1,
        ':created_by' => 1
    ]);
    
    echo "Project created successfully with ID: " . $pdo->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
