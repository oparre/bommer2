<?php
/**
 * BOM Component Groups API Endpoints
 * 
 * Handles CRUD operations for centralized BOM component group templates
 */

require_once __DIR__ . '/index.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDb();

// Require authentication
requireApiAuth();

// Route to appropriate handler
switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'PUT':
        handlePut($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        sendError('Method not allowed', 405);
}

/**
 * GET - List all component groups or get single group
 */
function handleGet($pdo) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        getGroupById($pdo, $id);
    } else {
        listGroups($pdo);
    }
}

/**
 * List all component groups
 */
function listGroups($pdo) {
    try {
        $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
        
        $where = $includeInactive ? '' : 'WHERE g.is_active = 1';
        
        $sql = "SELECT g.*, u.username as created_by_username, u.full_name as created_by_name
                FROM bom_component_groups g
                JOIN users u ON g.created_by = u.id
                $where
                ORDER BY g.display_order, g.name";
        
        $stmt = $pdo->query($sql);
        $groups = $stmt->fetchAll();
        
        sendSuccess($groups);
    } catch (Exception $e) {
        error_log('List Component Groups Error: ' . $e->getMessage());
        error_log('List Component Groups Trace: ' . $e->getTraceAsString());
        sendError('Failed to retrieve component groups: ' . $e->getMessage(), 500);
    }
}

/**
 * Get single component group by ID
 */
function getGroupById($pdo, $id) {
    try {
        $stmt = $pdo->prepare(
            "SELECT g.*, u.username as created_by_username, u.full_name as created_by_name
             FROM bom_component_groups g
             JOIN users u ON g.created_by = u.id
             WHERE g.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendError('Component group not found', 404);
        }
        
        sendSuccess($group);
    } catch (Exception $e) {
        error_log('Get Component Group Error: ' . $e->getMessage());
        sendError('Failed to retrieve component group', 500);
    }
}

/**
 * POST - Create new component group (Admin only)
 */
function handlePost($pdo) {
    try {
        // Check if user is admin
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            sendError('Unauthorized. Admin role required.', 403);
        }
        
        $data = getJsonInput();
        validateRequired($data, ['name']);
        
        // Check name uniqueness
        $stmt = $pdo->prepare("SELECT id FROM bom_component_groups WHERE name = :name");
        $stmt->execute([':name' => $data['name']]);
        if ($stmt->fetch()) {
            sendError('Group name already exists', 400);
        }
        
        // Create group
        $stmt = $pdo->prepare(
            "INSERT INTO bom_component_groups (name, description, icon, display_order, is_active, created_by)
             VALUES (:name, :description, :icon, :display_order, :is_active, :created_by)"
        );
        $stmt->execute([
            ':name' => sanitizeString($data['name']),
            ':description' => $data['description'] ?? null,
            ':icon' => $data['icon'] ?? 'badge',
            ':display_order' => $data['display_order'] ?? 999,
            ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ':created_by' => getCurrentUserId()
        ]);
        
        $groupId = $pdo->lastInsertId();
        
        // Log audit
        logAudit($pdo, 'create_component_group', 'component', $groupId, ['name' => $data['name']]);
        
        sendSuccess(['id' => $groupId], 'Component group created successfully');
    } catch (Exception $e) {
        error_log('Create Component Group Error: ' . $e->getMessage());
        sendError('Failed to create component group', 500);
    }
}

/**
 * PUT - Update component group (Admin only)
 */
function handlePut($pdo) {
    try {
        // Check if user is admin
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            sendError('Unauthorized. Admin role required.', 403);
        }
        
        $data = getJsonInput();
        validateRequired($data, ['id']);
        
        $id = $data['id'];
        
        // Check if group exists
        $stmt = $pdo->prepare("SELECT * FROM bom_component_groups WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendError('Component group not found', 404);
        }
        
        // Check name uniqueness if name is being changed
        if (isset($data['name']) && $data['name'] !== $group['name']) {
            $stmt = $pdo->prepare("SELECT id FROM bom_component_groups WHERE name = :name AND id != :id");
            $stmt->execute([':name' => $data['name'], ':id' => $id]);
            if ($stmt->fetch()) {
                sendError('Group name already exists', 400);
            }
        }
        
        // Build update query
        $updates = [];
        $params = [':id' => $id];
        
        if (isset($data['name'])) {
            $updates[] = 'name = :name';
            $params[':name'] = sanitizeString($data['name']);
        }
        
        if (isset($data['description'])) {
            $updates[] = 'description = :description';
            $params[':description'] = $data['description'];
        }
        
        if (isset($data['icon'])) {
            $updates[] = 'icon = :icon';
            $params[':icon'] = $data['icon'];
        }
        
        if (isset($data['display_order'])) {
            $updates[] = 'display_order = :display_order';
            $params[':display_order'] = (int)$data['display_order'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = 'is_active = :is_active';
            $params[':is_active'] = (int)$data['is_active'];
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $sql = "UPDATE bom_component_groups SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log audit
        logAudit($pdo, 'update_component_group', 'component', $id, $data);
        
        sendSuccess(null, 'Component group updated successfully');
    } catch (Exception $e) {
        error_log('Update Component Group Error: ' . $e->getMessage());
        sendError('Failed to update component group', 500);
    }
}

/**
 * DELETE - Delete component group (Admin only)
 * Note: This is a soft delete (sets is_active = 0)
 */
function handleDelete($pdo) {
    try {
        // Check if user is admin
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            sendError('Unauthorized. Admin role required.', 403);
        }
        
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            sendError('Group ID required', 400);
        }
        
        // Check if group exists
        $stmt = $pdo->prepare("SELECT * FROM bom_component_groups WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendError('Component group not found', 404);
        }
        
        // Soft delete by setting is_active = 0
        $stmt = $pdo->prepare("UPDATE bom_component_groups SET is_active = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Log audit
        logAudit($pdo, 'delete_component_group', 'component', $id, ['name' => $group['name']]);
        
        sendSuccess(null, 'Component group deleted successfully');
    } catch (Exception $e) {
        error_log('Delete Component Group Error: ' . $e->getMessage());
        sendError('Failed to delete component group', 500);
    }
}
