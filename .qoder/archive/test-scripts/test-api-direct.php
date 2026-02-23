<?php
// Mimic API call
define('SECURE_ACCESS', true);
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/api/index.php';

echo "Session status: " . (isLoggedIn() ? "LOGGED IN" : "NOT LOGGED IN") . "\n";
echo "User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NONE") . "\n";
echo "Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : "NONE") . "\n";

// Manually start session as admin
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['fingerprint'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);

echo "\nAfter manual session:\n";
echo "Session status: " . (isLoggedIn() ? "LOGGED IN" : "NOT LOGGED IN") . "\n";

// Now try the query
try {
    $pdo = getDb();
    $sql = "SELECT g.*, u.username as created_by_username, u.full_name as created_by_name
            FROM bom_component_groups g
            JOIN users u ON g.created_by = u.id
            WHERE is_active = 1
            ORDER BY g.display_order, g.name";
    
    $stmt = $pdo->query($sql);
    $groups = $stmt->fetchAll();
    
    echo "\nQuery successful! Found " . count($groups) . " groups\n";
    echo json_encode($groups, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "\nQuery failed: " . $e->getMessage() . "\n";
}
