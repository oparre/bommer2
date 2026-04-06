<?php
/**
 * BOM API Endpoints
 * 
 * Handles CRUD operations for BOMs
 */

require_once __DIR__ . '/index.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDb();

// Require authentication
requireApiAuth();

// Check for special actions
if ($method === 'GET' && isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'suggest_sku':
            suggestSKU($pdo);
            exit;
        case 'export':
            exportBOM($pdo);
            exit;
        case 'compare':
            compareBOMs($pdo);
            exit;
        case 'matrix':
            getMatrixData($pdo);
            exit;
    }
}

if ($method === 'POST' && isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'create_revision':
            createRevision($pdo);
            exit;
        case 'create_variant':
            createVariant($pdo);
            exit;
    }
}

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
 * GET - List BOMs or get single BOM
 */
function handleGet($pdo) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        getBomById($pdo, $id);
    } else {
        listBoms($pdo);
    }
}

/**
 * List all BOMs with filters
 */
function listBoms($pdo) {
    try {
        $filters = [];
        $params = [];
        
        if (isset($_GET['project_id'])) {
            $filters[] = 'b.project_id = :project_id';
            $params[':project_id'] = $_GET['project_id'];
        }
        
        if (isset($_GET['status'])) {
            $filters[] = 'br.status = :status';
            $params[':status'] = $_GET['status'];
        }
        
        if (isset($_GET['search'])) {
            $searchTerm = '%' . $_GET['search'] . '%';
            $filters[] = '(b.sku LIKE :search1 OR b.name LIKE :search2 OR b.description LIKE :search3)';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }
        
        $where = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
        
        $sql = "SELECT b.*, p.name as project_name, p.code as project_code,
                       br.status as current_status, br.notes as revision_notes,
                       u.username as created_by_username, u.full_name as created_by_name
                FROM boms b
                JOIN projects p ON b.project_id = p.id
                LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
                JOIN users u ON b.created_by = u.id
                $where
                ORDER BY b.updated_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $boms = $stmt->fetchAll();
        
        // Calculate total cost for each BOM
        foreach ($boms as &$bom) {
            $totalCost = 0;
            
            // Get the current revision ID
            $revStmt = $pdo->prepare(
                "SELECT id FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision"
            );
            $revStmt->execute([
                ':bom_id' => $bom['id'],
                ':revision' => $bom['current_revision']
            ]);
            $revision = $revStmt->fetch();
            
            if ($revision) {
                // Calculate total cost from all items in this revision
                $costStmt = $pdo->prepare(
                    "SELECT 
                        SUM(bi.quantity * COALESCE(c.unit_cost, ec.unit_cost, 0)) as total_cost
                    FROM bom_items bi
                    INNER JOIN bom_groups bg ON bi.group_id = bg.id
                    LEFT JOIN components c ON bi.component_id = c.id AND bi.component_source = 'bommer'
                    LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
                    WHERE bg.revision_id = :revision_id"
                );
                $costStmt->execute([':revision_id' => $revision['id']]);
                $result = $costStmt->fetch();
                
                $totalCost = $result['total_cost'] ?? 0;
            }
            
            // Add total_cost to BOM data
            $bom['total_cost'] = floatval($totalCost);
        }
        
        sendSuccess($boms);
    } catch (Exception $e) {
        error_log('List BOMs Error: ' . $e->getMessage());
        sendError('Failed to retrieve BOMs', 500);
    }
}

/**
 * Get single BOM with full details
 */
function getBomById($pdo, $id) {
    try {
        // Get BOM basic info
        $stmt = $pdo->prepare(
            "SELECT b.*, p.name as project_name, p.code as project_code,
                    br.status as current_status, br.notes as revision_notes,
                    u.username as created_by_username, u.full_name as created_by_name
             FROM boms b
             JOIN projects p ON b.project_id = p.id
             LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
             JOIN users u ON b.created_by = u.id
             WHERE b.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $bom = $stmt->fetch();
        
        if (!$bom) {
            sendError('BOM not found', 404);
        }
        
        // Get current revision details
        $stmt = $pdo->prepare(
            "SELECT * FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision"
        );
        $stmt->execute([
            ':bom_id' => $id,
            ':revision' => $bom['current_revision']
        ]);
        $revision = $stmt->fetch();
        
        // Get groups and items
        $stmt = $pdo->prepare(
            "SELECT g.*, 
                    COUNT(bi.id) as item_count
             FROM bom_groups g
             LEFT JOIN bom_items bi ON g.id = bi.group_id
             WHERE g.revision_id = :revision_id
             GROUP BY g.id
             ORDER BY g.display_order"
        );
        $stmt->execute([':revision_id' => $revision['id']]);
        $groups = $stmt->fetchAll();
        
        // Consolidate duplicate groups by name
        $consolidatedGroups = [];
        $groupsByName = [];
        
        foreach ($groups as $group) {
            $groupName = $group['name'];
            
            if (!isset($groupsByName[$groupName])) {
                // First occurrence - keep original group
                $groupsByName[$groupName] = $group;
                $consolidatedGroups[] = &$groupsByName[$groupName];
            } else {
                // Duplicate - merge items into first occurrence
                // Items will be fetched and merged below
                $groupsByName[$groupName]['_merge_group_ids'][] = $group['id'];
            }
        }
        
        // Get items for each group (including merged duplicates)
        foreach ($consolidatedGroups as &$group) {
            $groupIds = [$group['id']];
            
            // Add IDs of duplicate groups to merge
            if (isset($group['_merge_group_ids'])) {
                $groupIds = array_merge($groupIds, $group['_merge_group_ids']);
                unset($group['_merge_group_ids']); // Remove temporary field
            }
            
            $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT bi.*, 
                        COALESCE(c.part_number, ec.part_number) AS part_number,
                        COALESCE(c.name, ec.name) AS component_name,
                        COALESCE(c.description, ec.description) AS description,
                        COALESCE(c.category, ec.category) AS category,
                        COALESCE(c.manufacturer, ec.manufacturer) AS manufacturer,
                        COALESCE(c.mpn, ec.mpn) AS mpn,
                        COALESCE(c.supplier, ec.supplier) AS supplier,
                        COALESCE(c.unit_cost, ec.unit_cost) AS unit_cost,
                        COALESCE(c.status, ec.status) AS component_status,
                        ec.last_sync_at,
                        ec.erp_sync_status
                 FROM bom_items bi
                 LEFT JOIN components c ON bi.component_id = c.id AND bi.component_source = 'bommer'
                 LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
                 WHERE bi.group_id IN ($placeholders)
                 ORDER BY bi.display_order"
            );
            $stmt->execute($groupIds);
            $group['items'] = $stmt->fetchAll();
            
            // Update item count to reflect merged items
            $group['item_count'] = count($group['items']);
        }
        
        $bom['groups'] = $consolidatedGroups;
        
        sendSuccess($bom);
    } catch (Exception $e) {
        error_log('Get BOM Error: ' . $e->getMessage());
        sendError('Failed to retrieve BOM', 500);
    }
}

/**
 * Compare multiple BOMs - returns array of full BOM structures
 */
function compareBOMs($pdo) {
    try {
        $idsParam = $_GET['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $idsParam)));
        
        if (empty($ids)) {
            sendError('No BOM IDs provided', 400);
        }
        
        if (count($ids) > 10) {
            sendError('Maximum 10 BOMs allowed for comparison', 400);
        }
        
        $boms = [];
        foreach ($ids as $id) {
            // Get BOM basic info
            $stmt = $pdo->prepare(
                "SELECT b.*, p.name as project_name, p.code as project_code,
                        br.status as current_status, br.notes as revision_notes,
                        u.username as created_by_username, u.full_name as created_by_name
                 FROM boms b
                 JOIN projects p ON b.project_id = p.id
                 LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
                 JOIN users u ON b.created_by = u.id
                 WHERE b.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $bom = $stmt->fetch();
            
            if (!$bom) {
                error_log("Compare BOMs: BOM ID $id not found");
                continue; // Skip invalid BOMs
            }
            
            // Get current revision details
            $stmt = $pdo->prepare(
                "SELECT * FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision"
            );
            $stmt->execute([
                ':bom_id' => $id,
                ':revision' => $bom['current_revision']
            ]);
            $revision = $stmt->fetch();
            
            if (!$revision) {
                error_log("Compare BOMs: Revision not found for BOM ID $id, revision " . $bom['current_revision']);
                // Skip BOMs without valid revision
                continue;
            }
            
            // Get groups and items
            $stmt = $pdo->prepare(
                "SELECT g.*, 
                        COUNT(bi.id) as item_count
                 FROM bom_groups g
                 LEFT JOIN bom_items bi ON g.id = bi.group_id
                 WHERE g.revision_id = :revision_id
                 GROUP BY g.id
                 ORDER BY g.display_order"
            );
            $stmt->execute([':revision_id' => $revision['id']]);
            $groups = $stmt->fetchAll();
            
            // Consolidate duplicate groups by name
            $consolidatedGroups = [];
            $groupsByName = [];
            
            foreach ($groups as $group) {
                $groupName = $group['name'];
                
                if (!isset($groupsByName[$groupName])) {
                    $groupsByName[$groupName] = $group;
                    $consolidatedGroups[] = &$groupsByName[$groupName];
                } else {
                    $groupsByName[$groupName]['_merge_group_ids'][] = $group['id'];
                }
            }
            
            // Get items for each group
            foreach ($consolidatedGroups as &$group) {
                $groupIds = [$group['id']];
                
                if (isset($group['_merge_group_ids'])) {
                    $groupIds = array_merge($groupIds, $group['_merge_group_ids']);
                    unset($group['_merge_group_ids']);
                }
                
                $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
                $stmt = $pdo->prepare(
                    "SELECT bi.*, 
                            COALESCE(c.part_number, ec.part_number) AS part_number,
                            COALESCE(c.name, ec.name) AS component_name,
                            COALESCE(c.description, ec.description) AS description,
                            COALESCE(c.category, ec.category) AS category,
                            COALESCE(c.unit_cost, ec.unit_cost) AS unit_cost,
                            'pcs' AS unit
                     FROM bom_items bi
                     LEFT JOIN components c ON bi.component_id = c.id AND bi.component_source = 'bommer'
                     LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
                     WHERE bi.group_id IN ($placeholders)
                     ORDER BY bi.display_order"
                );
                $stmt->execute($groupIds);
                $group['items'] = $stmt->fetchAll();
                $group['item_count'] = count($group['items']);
            }
            
            $bom['groups'] = $consolidatedGroups;
            $boms[] = $bom;
        }
        
        if (empty($boms)) {
            sendError('No valid BOMs found with the provided IDs', 404);
        }
        
        sendSuccess($boms);
    } catch (Exception $e) {
        error_log('Compare BOMs Error: ' . $e->getMessage());
        error_log('Compare BOMs Stack Trace: ' . $e->getTraceAsString());
        sendError('Failed to compare BOMs', 500);
    }
}

/**
 * Suggest SKU based on project
 */
function suggestSKU($pdo) {
    try {
        $projectId = $_GET['project_id'] ?? null;
        
        if (!$projectId) {
            sendError('Project ID required', 400);
        }
        
        // Get project code
        $stmt = $pdo->prepare("SELECT code FROM projects WHERE id = :id");
        $stmt->execute([':id' => $projectId]);
        $project = $stmt->fetch();
        
        if (!$project) {
            sendError('Project not found', 404);
        }
        
        // Find next available sequence number for this project
        $stmt = $pdo->prepare(
            "SELECT sku FROM boms WHERE project_id = :project_id AND sku LIKE :pattern ORDER BY sku DESC LIMIT 1"
        );
        $pattern = 'BOM-' . $project['code'] . '-%';
        $stmt->execute([':project_id' => $projectId, ':pattern' => $pattern]);
        $lastBom = $stmt->fetch();
        
        $sequence = 1;
        if ($lastBom) {
            // Extract sequence number from last SKU
            if (preg_match('/BOM-' . preg_quote($project['code'], '/') . '-(\d+)$/', $lastBom['sku'], $matches)) {
                $sequence = (int)$matches[1] + 1;
            }
        }
        
        $suggestedSKU = sprintf('BOM-%s-%03d', $project['code'], $sequence);
        
        sendSuccess(['sku' => $suggestedSKU]);
    } catch (Exception $e) {
        error_log('Suggest SKU Error: ' . $e->getMessage());
        sendError('Failed to suggest SKU', 500);
    }
}

/**
 * Validate components against banned list
 */
function validateComponents($pdo, $componentIds) {
    if (empty($componentIds)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, part_number, name FROM components WHERE id IN ($placeholders) AND status = 'banned'"
    );
    $stmt->execute($componentIds);
    
    return $stmt->fetchAll();
}

/**
 * POST - Create new BOM
 */
function handlePost($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['sku', 'name', 'project_id', 'notes']);
        
        // Validate notes is not empty
        if (empty(trim($data['notes']))) {
            sendError('Reason for creation (notes) cannot be empty', 400);
        }
        
        // Check SKU uniqueness
        $stmt = $pdo->prepare("SELECT id FROM boms WHERE sku = :sku");
        $stmt->execute([':sku' => $data['sku']]);
        if ($stmt->fetch()) {
            sendError('SKU already exists', 400);
        }
        
        // Collect all component IDs for banned component check (Bommer only)
        $componentIds = [];
        $erpComponentIds = [];
        if (isset($data['groups']) && is_array($data['groups'])) {
            foreach ($data['groups'] as $groupData) {
                if (isset($groupData['items']) && is_array($groupData['items'])) {
                    foreach ($groupData['items'] as $itemData) {
                        if (isset($itemData['component_id'])) {
                            $source = $itemData['component_source'] ?? 'bommer';
                            if ($source === 'bommer') {
                                $componentIds[] = $itemData['component_id'];
                            } elseif ($source === 'erp') {
                                $erpComponentIds[] = $itemData['component_id'];
                            }
                        }
                    }
                }
            }
        }
        
        // Check for banned components (Bommer only)
        $bannedComponents = validateComponents($pdo, $componentIds);
        if (!empty($bannedComponents)) {
            $bannedList = array_map(function($c) {
                return $c['part_number'] . ' (' . $c['name'] . ')';
            }, $bannedComponents);
            sendError('BOM contains banned components: ' . implode(', ', $bannedList), 400);
        }
        
        // Validate that referenced ERP components exist
        if (!empty($erpComponentIds)) {
            $placeholders = implode(',', array_fill(0, count($erpComponentIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT id FROM erp_components WHERE id IN ($placeholders)"
            );
            $stmt->execute($erpComponentIds);
            $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_diff($erpComponentIds, $foundIds);
            if (!empty($missing)) {
                sendError('BOM references unknown ERP components: ' . implode(', ', $missing), 400);
            }
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Create BOM
        $stmt = $pdo->prepare(
            "INSERT INTO boms (sku, name, project_id, description, current_revision, created_by)
             VALUES (:sku, :name, :project_id, :description, 1, :created_by)"
        );
        $stmt->execute([
            ':sku' => sanitizeString($data['sku']),
            ':name' => sanitizeString($data['name']),
            ':project_id' => $data['project_id'],
            ':description' => $data['description'] ?? null,
            ':created_by' => getCurrentUserId()
        ]);
        
        $bomId = $pdo->lastInsertId();
        
        // Create first revision
        $stmt = $pdo->prepare(
            "INSERT INTO bom_revisions (bom_id, revision_number, status, notes, created_by)
             VALUES (:bom_id, 1, 'draft', :notes, :created_by)"
        );
        $stmt->execute([
            ':bom_id' => $bomId,
            ':notes' => sanitizeString($data['notes']),
            ':created_by' => getCurrentUserId()
        ]);
        
        $revisionId = $pdo->lastInsertId();
        
        // Create groups and items if provided
        if (isset($data['groups']) && is_array($data['groups'])) {
            foreach ($data['groups'] as $index => $groupData) {
                $stmt = $pdo->prepare(
                    "INSERT INTO bom_groups (revision_id, name, display_order)
                     VALUES (:revision_id, :name, :display_order)"
                );
                $stmt->execute([
                    ':revision_id' => $revisionId,
                    ':name' => sanitizeString($groupData['name']),
                    ':display_order' => $index
                ]);
                
                $groupId = $pdo->lastInsertId();
                
                if (isset($groupData['items']) && is_array($groupData['items'])) {
                    foreach ($groupData['items'] as $itemIndex => $itemData) {
                        $stmt = $pdo->prepare(
                            "INSERT INTO bom_items (group_id, component_id, component_source, quantity, reference_designator, notes, display_order)
                             VALUES (:group_id, :component_id, :component_source, :quantity, :reference_designator, :notes, :display_order)"
                        );
                        $stmt->execute([
                            ':group_id' => $groupId,
                            ':component_id' => $itemData['component_id'],
                            ':component_source' => $itemData['component_source'] ?? 'bommer',
                            ':quantity' => $itemData['quantity'] ?? 1.0,
                            ':reference_designator' => $itemData['reference_designator'] ?? null,
                            ':notes' => $itemData['notes'] ?? null,
                            ':display_order' => $itemIndex
                        ]);
                    }
                }
            }
        }
        
        // Log audit
        logAudit($pdo, 'create_bom', 'bom', $bomId, ['sku' => $data['sku'], 'name' => $data['name']]);
        
        $pdo->commit();
        
        sendSuccess(['id' => $bomId], 'BOM created successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Create BOM Error: ' . $e->getMessage());
        sendError('Failed to create BOM', 500);
    }
}

/**
 * PUT - Update BOM
 */
function handlePut($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['id']);
        
        $id = $data['id'];
        
        // Check if BOM exists
        $stmt = $pdo->prepare("SELECT * FROM boms WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $bom = $stmt->fetch();
        
        if (!$bom) {
            sendError('BOM not found', 404);
        }
        
        // Get current revision
        $stmt = $pdo->prepare(
            "SELECT * FROM bom_revisions 
             WHERE bom_id = :bom_id AND revision_number = :revision"
        );
        $stmt->execute([
            ':bom_id' => $id,
            ':revision' => $bom['current_revision']
        ]);
        $revision = $stmt->fetch();
        
        if (!$revision) {
            sendError('BOM revision not found', 404);
        }
        
        // Only allow editing draft BOMs
        if ($revision['status'] !== 'draft') {
            sendError('Only draft BOMs can be edited', 400);
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update BOM basic info
        if (isset($data['name']) || isset($data['description'])) {
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
            
            $sql = "UPDATE boms SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Update revision notes
        if (isset($data['notes'])) {
            $stmt = $pdo->prepare(
                "UPDATE bom_revisions SET notes = :notes WHERE id = :revision_id"
            );
            $stmt->execute([
                ':notes' => $data['notes'],
                ':revision_id' => $revision['id']
            ]);
        }
        
        // Update groups and items if provided
        if (isset($data['groups']) && is_array($data['groups'])) {
            // Delete existing groups and items for this revision
            $stmt = $pdo->prepare("DELETE FROM bom_groups WHERE revision_id = :revision_id");
            $stmt->execute([':revision_id' => $revision['id']]);
            
            // Insert new groups and items
            foreach ($data['groups'] as $groupData) {
                $stmt = $pdo->prepare(
                    "INSERT INTO bom_groups (revision_id, name, display_order)
                     VALUES (:revision_id, :name, :display_order)"
                );
                $stmt->execute([
                    ':revision_id' => $revision['id'],
                    ':name' => sanitizeString($groupData['name']),
                    ':display_order' => $groupData['display_order'] ?? 0
                ]);
                
                $groupId = $pdo->lastInsertId();
                
                // Insert items for this group
                if (isset($groupData['items']) && is_array($groupData['items'])) {
                    foreach ($groupData['items'] as $itemData) {
                        $stmt = $pdo->prepare(
                            "INSERT INTO bom_items (group_id, component_id, component_source, quantity, reference_designator, notes, display_order)
                             VALUES (:group_id, :component_id, :component_source, :quantity, :reference_designator, :notes, :display_order)"
                        );
                        $stmt->execute([
                            ':group_id' => $groupId,
                            ':component_id' => $itemData['component_id'],
                            ':component_source' => $itemData['component_source'] ?? 'bommer',
                            ':quantity' => $itemData['quantity'] ?? 1,
                            ':reference_designator' => $itemData['reference_designator'] ?? null,
                            ':notes' => $itemData['notes'] ?? null,
                            ':display_order' => $itemData['display_order'] ?? 0
                        ]);
                    }
                }
            }
        }
        
        // If updating revision status
        if (isset($data['status'])) {
            $stmt = $pdo->prepare(
                "UPDATE bom_revisions SET status = :status 
                 WHERE bom_id = :bom_id AND revision_number = :revision"
            );
            $stmt->execute([
                ':status' => $data['status'],
                ':bom_id' => $id,
                ':revision' => $bom['current_revision']
            ]);
            
            logAudit($pdo, 'update_bom_status', 'bom', $id, ['status' => $data['status']]);
        }
        
        logAudit($pdo, 'update_bom', 'bom', $id, $data);
        
        $pdo->commit();
        
        sendSuccess(null, 'BOM updated successfully');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Update BOM Error: ' . $e->getMessage());
        sendError('Failed to update BOM', 500);
    }
}

/**
 * Create new revision - Clone existing BOM and increment version
 */
function createRevision($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['bom_id', 'notes']);
        
        $bomId = $data['bom_id'];
        $notes = trim($data['notes']);
        
        // Validate notes is not empty
        if (empty($notes)) {
            sendError('Reason for revision cannot be empty', 400);
        }
        
        // Get existing BOM
        $stmt = $pdo->prepare("SELECT * FROM boms WHERE id = :id");
        $stmt->execute([':id' => $bomId]);
        $bom = $stmt->fetch();
        
        if (!$bom) {
            sendError('BOM not found', 404);
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Calculate new revision number
        $newRevisionNumber = $bom['current_revision'] + 1;
        
        // Get current revision data
        $stmt = $pdo->prepare(
            "SELECT * FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision"
        );
        $stmt->execute([
            ':bom_id' => $bomId,
            ':revision' => $bom['current_revision']
        ]);
        $currentRevision = $stmt->fetch();
        
        if (!$currentRevision) {
            sendError('Current revision not found', 404);
        }
        
        // Create new revision
        $stmt = $pdo->prepare(
            "INSERT INTO bom_revisions (bom_id, revision_number, status, notes, created_by)
             VALUES (:bom_id, :revision_number, 'draft', :notes, :created_by)"
        );
        $stmt->execute([
            ':bom_id' => $bomId,
            ':revision_number' => $newRevisionNumber,
            ':notes' => sanitizeString($notes),
            ':created_by' => getCurrentUserId()
        ]);
        
        $newRevisionId = $pdo->lastInsertId();
        
        // Clone groups from current revision
        $stmt = $pdo->prepare(
            "SELECT * FROM bom_groups WHERE revision_id = :revision_id ORDER BY display_order"
        );
        $stmt->execute([':revision_id' => $currentRevision['id']]);
        $groups = $stmt->fetchAll();
        
        foreach ($groups as $group) {
            // Insert new group
            $stmt = $pdo->prepare(
                "INSERT INTO bom_groups (revision_id, name, display_order)
                 VALUES (:revision_id, :name, :display_order)"
            );
            $stmt->execute([
                ':revision_id' => $newRevisionId,
                ':name' => $group['name'],
                ':display_order' => $group['display_order']
            ]);
            
            $newGroupId = $pdo->lastInsertId();
            
            // Clone items for this group
            $stmt = $pdo->prepare(
                "SELECT * FROM bom_items WHERE group_id = :group_id ORDER BY display_order"
            );
            $stmt->execute([':group_id' => $group['id']]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                $stmt = $pdo->prepare(
                    "INSERT INTO bom_items (group_id, component_id, component_source, quantity, reference_designator, notes, display_order)
                     VALUES (:group_id, :component_id, :component_source, :quantity, :reference_designator, :notes, :display_order)"
                );
                $stmt->execute([
                    ':group_id' => $newGroupId,
                    ':component_id' => $item['component_id'],
                    ':component_source' => $item['component_source'],
                    ':quantity' => $item['quantity'],
                    ':reference_designator' => $item['reference_designator'],
                    ':notes' => $item['notes'],
                    ':display_order' => $item['display_order']
                ]);
            }
        }
        
        // Update BOM to point to new revision
        $stmt = $pdo->prepare(
            "UPDATE boms SET current_revision = :revision WHERE id = :id"
        );
        $stmt->execute([
            ':revision' => $newRevisionNumber,
            ':id' => $bomId
        ]);
        
        // Log audit
        logAudit($pdo, 'create_revision', 'bom', $bomId, [
            'revision_number' => $newRevisionNumber,
            'notes' => $notes
        ]);
        
        $pdo->commit();
        
        sendSuccess([
            'id' => $bomId,
            'revision_number' => $newRevisionNumber
        ], 'Revision created successfully');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Create Revision Error: ' . $e->getMessage());
        sendError('Failed to create revision: ' . $e->getMessage(), 500);
    }
}

/**
 * Create BOM variant (new SKU) from existing BOM
 */
function createVariant($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['source_bom_id', 'sku', 'notes']);

        $bomId = $data['source_bom_id'];
        $sku = trim($data['sku']);
        $notes = trim($data['notes']);

        if (empty($sku)) {
            sendError('SKU cannot be empty', 400);
        }

        if (empty($notes)) {
            sendError('Reason for variant cannot be empty', 400);
        }

        // Check SKU uniqueness
        $stmt = $pdo->prepare("SELECT id FROM boms WHERE sku = :sku");
        $stmt->execute([':sku' => $sku]);
        if ($stmt->fetch()) {
            sendError('SKU already exists', 400);
        }

        // Get source BOM
        $stmt = $pdo->prepare("SELECT * FROM boms WHERE id = :id");
        $stmt->execute([':id' => $bomId]);
        $sourceBom = $stmt->fetch();

        if (!$sourceBom) {
            sendError('Source BOM not found', 404);
        }

        // Determine variant group identifier
        $variantGroup = null;
        if (isset($data['variant_group']) && strlen(trim($data['variant_group'])) > 0) {
            $variantGroup = sanitizeString(trim($data['variant_group']));
        } elseif (!empty($sourceBom['variant_group'])) {
            $variantGroup = $sourceBom['variant_group'];
        } else {
            // Default: use source SKU as the variant family key
            $variantGroup = $sourceBom['sku'];
        }

        // Start transaction
        $pdo->beginTransaction();

        // If source BOM did not have a variant group yet, assign it now
        if (empty($sourceBom['variant_group']) && !empty($variantGroup)) {
            $stmt = $pdo->prepare(
                "UPDATE boms SET variant_group = :variant_group WHERE id = :id"
            );
            $stmt->execute([
                ':variant_group' => $variantGroup,
                ':id' => $bomId
            ]);
        }

        // Derive new BOM basic fields
        $newName = isset($data['name']) && trim($data['name']) !== ''
            ? sanitizeString($data['name'])
            : $sourceBom['name'];
        $newDescription = array_key_exists('description', $data)
            ? $data['description']
            : $sourceBom['description'];

        // Create new BOM record in same project
        $stmt = $pdo->prepare(
            "INSERT INTO boms (sku, name, project_id, variant_group, description, current_revision, created_by)
             VALUES (:sku, :name, :project_id, :variant_group, :description, 1, :created_by)"
        );
        $stmt->execute([
            ':sku' => $sku,
            ':name' => $newName,
            ':project_id' => $sourceBom['project_id'],
            ':variant_group' => $variantGroup,
            ':description' => $newDescription,
            ':created_by' => getCurrentUserId()
        ]);

        $newBomId = $pdo->lastInsertId();

        // Create first revision for the new BOM
        $stmt = $pdo->prepare(
            "INSERT INTO bom_revisions (bom_id, revision_number, status, notes, created_by)
             VALUES (:bom_id, 1, 'draft', :notes, :created_by)"
        );
        $stmt->execute([
            ':bom_id' => $newBomId,
            ':notes' => sanitizeString($notes),
            ':created_by' => getCurrentUserId()
        ]);

        $newRevisionId = $pdo->lastInsertId();

        // Load source BOM current revision
        $stmt = $pdo->prepare(
            "SELECT * FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision"
        );
        $stmt->execute([
            ':bom_id' => $bomId,
            ':revision' => $sourceBom['current_revision']
        ]);
        $currentRevision = $stmt->fetch();

        if ($currentRevision) {
            // Clone groups from source revision
            $stmt = $pdo->prepare(
                "SELECT * FROM bom_groups WHERE revision_id = :revision_id ORDER BY display_order"
            );
            $stmt->execute([':revision_id' => $currentRevision['id']]);
            $groups = $stmt->fetchAll();

            foreach ($groups as $group) {
                // Insert new group for the variant BOM
                $stmt = $pdo->prepare(
                    "INSERT INTO bom_groups (revision_id, name, display_order)
                     VALUES (:revision_id, :name, :display_order)"
                );
                $stmt->execute([
                    ':revision_id' => $newRevisionId,
                    ':name' => $group['name'],
                    ':display_order' => $group['display_order']
                ]);

                $newGroupId = $pdo->lastInsertId();

                // Clone items for this group
                $stmt = $pdo->prepare(
                    "SELECT * FROM bom_items WHERE group_id = :group_id ORDER BY display_order"
                );
                $stmt->execute([':group_id' => $group['id']]);
                $items = $stmt->fetchAll();

                foreach ($items as $item) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO bom_items (group_id, component_id, component_source, quantity, reference_designator, notes, display_order)
                         VALUES (:group_id, :component_id, :component_source, :quantity, :reference_designator, :notes, :display_order)"
                    );
                    $stmt->execute([
                        ':group_id' => $newGroupId,
                        ':component_id' => $item['component_id'],
                        ':component_source' => $item['component_source'],
                        ':quantity' => $item['quantity'],
                        ':reference_designator' => $item['reference_designator'],
                        ':notes' => $item['notes'],
                        ':display_order' => $item['display_order']
                    ]);
                }
            }
        }

        // Log audit
        logAudit($pdo, 'create_variant', 'bom', $newBomId, [
            'source_bom_id' => $bomId,
            'sku' => $sku,
            'variant_group' => $variantGroup
        ]);

        $pdo->commit();

        sendSuccess(['id' => $newBomId], 'Variant BOM created successfully');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Create Variant Error: ' . $e->getMessage());
        sendError('Failed to create variant: ' . $e->getMessage(), 500);
    }
}

/**
 * Export BOM to CSV or XLS format
 */
function exportBOM($pdo) {
    try {
        $bomId = $_GET['bom_id'] ?? null;
        $format = $_GET['format'] ?? 'csv';
        
        if (!$bomId) {
            sendError('BOM ID required', 400);
        }
        
        // Validate format
        if (!in_array($format, ['csv', 'xls'])) {
            $format = 'csv';
        }
        
        // Get BOM with full details
        $stmt = $pdo->prepare(
            "SELECT b.*, p.name as project_name, p.code as project_code,
                    br.status as current_status, br.notes as revision_notes,
                    u.username as created_by_username, u.full_name as created_by_name
             FROM boms b
             JOIN projects p ON b.project_id = p.id
             LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
             JOIN users u ON b.created_by = u.id
             WHERE b.id = :id"
        );
        $stmt->execute([':id' => $bomId]);
        $bom = $stmt->fetch();
        
        if (!$bom) {
            sendError('BOM not found', 404);
        }
        
        // Get current revision
        $stmt = $pdo->prepare(
            "SELECT * FROM bom_revisions WHERE bom_id = :bom_id AND revision_number = :revision"
        );
        $stmt->execute([
            ':bom_id' => $bomId,
            ':revision' => $bom['current_revision']
        ]);
        $revision = $stmt->fetch();
        
        // Get groups and items
        $stmt = $pdo->prepare(
            "SELECT g.* FROM bom_groups g
             WHERE g.revision_id = :revision_id
             ORDER BY g.display_order"
        );
        $stmt->execute([':revision_id' => $revision['id']]);
        $groups = $stmt->fetchAll();
        
        // Prepare export data
        $exportData = [];
        
        foreach ($groups as $group) {
            $stmt = $pdo->prepare(
                "SELECT bi.*, 
                        COALESCE(c.part_number, ec.part_number) AS part_number,
                        COALESCE(c.name, ec.name) AS component_name,
                        COALESCE(c.description, ec.description) AS description,
                        COALESCE(c.category, ec.category) AS category,
                        COALESCE(c.manufacturer, ec.manufacturer) AS manufacturer,
                        COALESCE(c.mpn, ec.mpn) AS mpn,
                        COALESCE(c.supplier, ec.supplier) AS supplier,
                        COALESCE(c.unit_cost, ec.unit_cost) AS unit_cost
                 FROM bom_items bi
                 LEFT JOIN components c ON bi.component_id = c.id AND bi.component_source = 'bommer'
                 LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
                 WHERE bi.group_id = :group_id
                 ORDER BY bi.display_order"
            );
            $stmt->execute([':group_id' => $group['id']]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                $exportData[] = [
                    'Group' => $group['name'],
                    'Part Number' => $item['part_number'],
                    'Component Name' => $item['component_name'],
                    'Description' => $item['description'] ?? '',
                    'Quantity' => $item['quantity'],
                    'Unit Cost' => number_format($item['unit_cost'], 4),
                    'Total Cost' => number_format($item['quantity'] * $item['unit_cost'], 2),
                    'Ref Designator' => $item['reference_designator'] ?? '',
                    'Manufacturer' => $item['manufacturer'] ?? '',
                    'MPN' => $item['mpn'] ?? '',
                    'Supplier' => $item['supplier'] ?? '',
                    'Notes' => $item['notes'] ?? ''
                ];
            }
        }
        
        // Generate filename
        $fileExtension = $format === 'xls' ? 'xls' : 'csv';
        $filename = 'BOM_' . $bom['sku'] . '_R' . $bom['current_revision'] . '_' . date('Ymd_His') . '.' . $fileExtension;
        
        // Set headers based on format
        if ($format === 'xls') {
            // Excel will open this CSV as XLS if we set the right content type
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Output header info
        echo "BOM Export\n";
        echo "SKU:," . $bom['sku'] . "\n";
        echo "Name:," . $bom['name'] . "\n";
        echo "Project:," . $bom['project_name'] . "\n";
        echo "Revision:," . $bom['current_revision'] . "\n";
        echo "Status:," . $bom['current_status'] . "\n";
        echo "Exported:," . date('Y-m-d H:i:s') . "\n";
        echo "\n";
        
        // Output CSV data
        $output = fopen('php://output', 'w');
        if (!empty($exportData)) {
            fputcsv($output, array_keys($exportData[0]));
            foreach ($exportData as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        
        // Log audit
        logAudit($pdo, 'export_bom', 'bom', $bomId, ['format' => $format]);
        
        exit;
    } catch (Exception $e) {
        error_log('Export BOM Error: ' . $e->getMessage());
        sendError('Failed to export BOM: ' . $e->getMessage(), 500);
    }
}

/**
 * GET - Matrix data for project or product scope
 * Returns BOMs and their components for matrix comparison view
 */
function getMatrixData($pdo) {
    try {
        $scope = $_GET['scope'] ?? null;
        $id = $_GET['id'] ?? null;
        
        // Validate parameters
        if (!$scope || !$id) {
            sendError('Missing required parameters: scope and id', 400);
        }
        
        if (!in_array($scope, ['project', 'product'])) {
            sendError('Invalid scope. Must be "project" or "product"', 400);
        }
        
        if (!is_numeric($id)) {
            sendError('Invalid ID', 400);
        }
        
        // Fetch BOMs based on scope
        if ($scope === 'project') {
            $bomData = fetchBOMsByProject($pdo, $id);
        } else {
            $bomData = fetchBOMsByProduct($pdo, $id);
        }
        
        if (empty($bomData['boms'])) {
            sendError('No BOMs found for this ' . $scope, 404);
        }
        
        if (count($bomData['boms']) < 2) {
            sendError('At least 2 BOMs are required for matrix comparison. This ' . $scope . ' has only ' . count($bomData['boms']) . ' BOM(s).', 400);
        }
        
        // Fetch all components for these BOMs
        $bomIds = array_column($bomData['boms'], 'id');
        $components = fetchComponentsForBOMs($pdo, $bomIds);
        
        // Build unified component list with occurrence data
        $unifiedComponents = buildComponentMatrix($components, $bomIds);
        
        // Calculate summary statistics
        $summary = calculateMatrixSummary($components, $bomData['boms']);
        
        sendSuccess([
            'scope' => $scope,
            'scope_id' => (int)$id,
            'scope_name' => $bomData['scope_name'],
            'boms' => $bomData['boms'],
            'components' => $unifiedComponents,
            'summary' => $summary
        ]);
    } catch (Exception $e) {
        error_log('Matrix Data Error: ' . $e->getMessage());
        sendError('Failed to retrieve matrix data: ' . $e->getMessage(), 500);
    }
}

/**
 * Fetch BOMs for a project (max 10)
 */
function fetchBOMsByProject($pdo, $projectId) {
    // Get project info
    $stmt = $pdo->prepare("SELECT name, code FROM projects WHERE id = :id");
    $stmt->execute([':id' => $projectId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        sendError('Project not found', 404);
    }
    
    // Get BOMs
    $stmt = $pdo->prepare(
        "SELECT b.id, b.sku, b.name, b.description, b.current_revision,
                br.status, br.notes as revision_notes
         FROM boms b
         JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
         WHERE b.project_id = :project_id
         ORDER BY b.sku
         LIMIT 10"
    );
    $stmt->execute([':project_id' => $projectId]);
    $boms = $stmt->fetchAll();
    
    return [
        'scope_name' => $project['name'] . ' (' . $project['code'] . ')',
        'boms' => $boms
    ];
}

/**
 * Fetch BOMs for a product (max 10)
 */
function fetchBOMsByProduct($pdo, $productId) {
    // Get product info
    $stmt = $pdo->prepare("SELECT name, code FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        sendError('Product not found', 404);
    }
    
    // Get BOMs from all projects in this product
    $stmt = $pdo->prepare(
        "SELECT b.id, b.sku, b.name, b.description, b.current_revision,
                br.status, br.notes as revision_notes,
                p.name as project_name, p.code as project_code
         FROM boms b
         JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
         JOIN projects p ON b.project_id = p.id
         JOIN product_projects pp ON p.id = pp.project_id
         WHERE pp.product_id = :product_id
         ORDER BY p.name, b.sku
         LIMIT 10"
    );
    $stmt->execute([':product_id' => $productId]);
    $boms = $stmt->fetchAll();
    
    return [
        'scope_name' => $product['name'] . ' (' . $product['code'] . ')',
        'boms' => $boms
    ];
}

/**
 * Fetch all components for given BOM IDs
 */
function fetchComponentsForBOMs($pdo, $bomIds) {
    if (empty($bomIds)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($bomIds), '?'));
    
    $stmt = $pdo->prepare(
        "SELECT b.id as bom_id, b.sku as bom_sku,
                g.name as group_name, g.display_order as group_order,
                bi.quantity, bi.notes,
                bi.display_order as item_order,
                COALESCE(c.part_number, ec.part_number) AS part_number,
                COALESCE(c.name, ec.name) AS component_name,
                COALESCE(c.description, ec.description) AS description,
                COALESCE(c.unit_cost, ec.unit_cost, 0) AS unit_cost,
                COALESCE(c.manufacturer, ec.manufacturer) AS manufacturer,
                COALESCE(c.mpn, ec.mpn) AS mpn,
                'pcs' AS unit
         FROM bom_items bi
         JOIN bom_groups g ON bi.group_id = g.id
         JOIN bom_revisions br ON g.revision_id = br.id
         JOIN boms b ON br.bom_id = b.id AND br.revision_number = b.current_revision
         LEFT JOIN components c ON bi.component_id = c.id AND bi.component_source = 'bommer'
         LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
         WHERE b.id IN ($placeholders)
         ORDER BY b.sku, g.display_order, bi.display_order"
    );
    
    $stmt->execute($bomIds);
    return $stmt->fetchAll();
}

/**
 * Build unified component matrix with difference analysis
 */
function buildComponentMatrix($components, $bomIds) {
    $componentMap = [];
    
    // Group components by part number
    foreach ($components as $item) {
        $partNum = $item['part_number'];
        
        if (!isset($componentMap[$partNum])) {
            $componentMap[$partNum] = [
                'part_number' => $partNum,
                'name' => $item['component_name'],
                'description' => $item['description'],
                'unit' => $item['unit'],
                'group' => $item['group_name'],
                'group_order' => $item['group_order'],
                'manufacturer' => $item['manufacturer'],
                'mpn' => $item['mpn'],
                'occurrences' => []
            ];
        }
        
        // Calculate total cost
        $totalCost = round($item['quantity'] * $item['unit_cost'], 2);
        
        $componentMap[$partNum]['occurrences'][$item['bom_id']] = [
            'qty' => (float)$item['quantity'],
            'unit_cost' => (float)$item['unit_cost'],
            'total_cost' => $totalCost,
            'notes' => $item['notes']
        ];
    }
    
    // Analyze differences for each component
    foreach ($componentMap as $partNum => &$component) {
        foreach ($bomIds as $bomId) {
            if (!isset($component['occurrences'][$bomId])) {
                // Component not present in this BOM
                $component['occurrences'][$bomId] = null;
            } else {
                // Determine difference type
                $occurrence = $component['occurrences'][$bomId];
                $occurrenceCount = count(array_filter($component['occurrences']));
                
                if ($occurrenceCount === 1) {
                    // Unique to this BOM
                    $component['occurrences'][$bomId]['difference_type'] = 'unique';
                } else {
                    // Check for quantity and cost differences
                    $hasQtyDiff = false;
                    $hasCostDiff = false;
                    
                    foreach ($component['occurrences'] as $occ) {
                        if ($occ !== null) {
                            if ($occ['qty'] !== $occurrence['qty']) {
                                $hasQtyDiff = true;
                            }
                            if ($occ['unit_cost'] !== $occurrence['unit_cost']) {
                                $hasCostDiff = true;
                            }
                        }
                    }
                    
                    if ($hasQtyDiff && $hasCostDiff) {
                        $component['occurrences'][$bomId]['difference_type'] = 'qty-cost-diff';
                    } elseif ($hasQtyDiff) {
                        $component['occurrences'][$bomId]['difference_type'] = 'qty-diff';
                    } elseif ($hasCostDiff) {
                        $component['occurrences'][$bomId]['difference_type'] = 'cost-diff';
                    } else {
                        $component['occurrences'][$bomId]['difference_type'] = 'common';
                    }
                }
            }
        }
    }
    
    // Convert to array and sort by group then part number
    $result = array_values($componentMap);
    usort($result, function($a, $b) {
        if ($a['group_order'] !== $b['group_order']) {
            return $a['group_order'] - $b['group_order'];
        }
        return strcmp($a['part_number'], $b['part_number']);
    });
    
    return $result;
}

/**
 * Calculate summary statistics for each BOM
 */
function calculateMatrixSummary($components, $boms) {
    $summary = [];
    
    foreach ($boms as $bom) {
        $bomId = $bom['id'];
        $stats = [
            'total_parts' => 0,
            'total_qty' => 0,
            'total_cost' => 0,
            'unique_parts' => 0
        ];
        
        // Count unique part numbers in this BOM
        $partNumbersInBOM = [];
        
        foreach ($components as $item) {
            if ($item['bom_id'] == $bomId) {
                $partNumbersInBOM[$item['part_number']] = true;
                $stats['total_parts']++;
                $stats['total_qty'] += $item['quantity'];
                $stats['total_cost'] = round($stats['total_cost'] + ($item['quantity'] * $item['unit_cost']), 2);
            }
        }
        
        // Count unique parts (components that appear in only this BOM)
        foreach ($partNumbersInBOM as $partNum => $exists) {
            $appearsInOtherBOMs = false;
            foreach ($components as $item) {
                if ($item['part_number'] === $partNum && $item['bom_id'] != $bomId) {
                    $appearsInOtherBOMs = true;
                    break;
                }
            }
            if (!$appearsInOtherBOMs) {
                $stats['unique_parts']++;
            }
        }
        
        $summary[$bomId] = $stats;
    }
    
    return $summary;
}

/**
 * DELETE - Soft delete BOM (change status to invalidated)
 */
function handleDelete($pdo) {
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            sendError('BOM ID required', 400);
        }
        
        // Check if BOM exists
        $stmt = $pdo->prepare("SELECT * FROM boms WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $bom = $stmt->fetch();
        
        if (!$bom) {
            sendError('BOM not found', 404);
        }
        
        // Soft delete by marking all revisions as invalidated
        $stmt = $pdo->prepare(
            "UPDATE bom_revisions SET status = 'invalidated' WHERE bom_id = :bom_id"
        );
        $stmt->execute([':bom_id' => $id]);
        
        logAudit($pdo, 'delete_bom', 'bom', $id, ['sku' => $bom['sku']]);
        
        sendSuccess(null, 'BOM deleted successfully');
    } catch (Exception $e) {
        error_log('Delete BOM Error: ' . $e->getMessage());
        sendError('Failed to delete BOM', 500);
    }
}
