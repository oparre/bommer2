<?php
/**
 * Audit Logs API Endpoints
 */

require_once __DIR__ . '/index.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDb();

requireApiAuth();

if ($method !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $filters = [];
    $params = [];
    
    if (isset($_GET['entity_type'])) {
        $filters[] = 'entity_type = :entity_type';
        $params[':entity_type'] = $_GET['entity_type'];
    }
    
    if (isset($_GET['entity_id'])) {
        $filters[] = 'entity_id = :entity_id';
        $params[':entity_id'] = $_GET['entity_id'];
    }
    
    if (isset($_GET['user_id'])) {
        $filters[] = 'al.user_id = :user_id';
        $params[':user_id'] = $_GET['user_id'];
    }
    
    if (isset($_GET['action'])) {
        $filters[] = 'action = :action';
        $params[':action'] = $_GET['action'];
    }
    
    $where = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 500) : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql = "SELECT al.*, u.username, u.full_name
            FROM audit_logs al
            JOIN users u ON al.user_id = u.id
            $where
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    // Parse JSON details
    foreach ($logs as &$log) {
        if ($log['details']) {
            $log['details'] = json_decode($log['details'], true);
        }
    }
    
    sendSuccess($logs);
} catch (Exception $e) {
    error_log('List Audit Logs Error: ' . $e->getMessage());
    sendError('Failed to retrieve audit logs', 500);
}
