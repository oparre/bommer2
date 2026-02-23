<?php
/**
 * Components API Endpoints
 */

require_once __DIR__ . '/index.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDb();

requireApiAuth();

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

function handleGet($pdo) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        getComponentById($pdo, $id);
    } else {
        listComponents($pdo);
    }
}

function listComponents($pdo) {
    try {
        // Get filter parameters
        $source = $_GET['source'] ?? 'bommer'; // Default to bommer only
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $allComponents = [];
        
        // Fetch Bommer components if requested
        if ($source === 'bommer' || $source === 'all') {
            $bommerFilters = [];
            $bommerParams = [];
            
            if (isset($_GET['category'])) {
                $bommerFilters[] = 'c.category = :category';
                $bommerParams[':category'] = $_GET['category'];
            }
            
            if (isset($_GET['status'])) {
                $bommerFilters[] = 'c.status = :status';
                $bommerParams[':status'] = $_GET['status'];
            }
            
            if (isset($_GET['search'])) {
                $searchTerm = '%' . $_GET['search'] . '%';
                $bommerFilters[] = '(c.part_number LIKE :search1 OR c.name LIKE :search2 OR c.description LIKE :search3 OR c.mpn LIKE :search4)';
                $bommerParams[':search1'] = $searchTerm;
                $bommerParams[':search2'] = $searchTerm;
                $bommerParams[':search3'] = $searchTerm;
                $bommerParams[':search4'] = $searchTerm;
            }
            
            $bommerWhere = !empty($bommerFilters) ? 'WHERE ' . implode(' AND ', $bommerFilters) : '';
            $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : '';
            
            $sql = "SELECT c.*, u.full_name as created_by_name,
                           COALESCE((
                               SELECT COUNT(DISTINCT br.bom_id)
                               FROM bom_items bi
                               JOIN bom_groups bg ON bi.group_id = bg.id
                               JOIN bom_revisions br ON bg.revision_id = br.id
                               WHERE bi.component_id = c.id AND bi.component_source = 'bommer'
                           ), 0) as where_used_count
                    FROM components c
                    LEFT JOIN users u ON c.created_by = u.id
                    $bommerWhere
                    ORDER BY c.part_number
                    $limitClause";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bommerParams);
            $components = $stmt->fetchAll();
            
            // Tag Bommer components with source metadata
            foreach ($components as &$component) {
                $component['source'] = 'bommer';
                $component['last_sync_at'] = null;
                $component['erp_sync_status'] = null;
            }
            unset($component);
            
            $allComponents = array_merge($allComponents, $components);
        }
        
        // Fetch ERP components if requested
        if ($source === 'erp' || $source === 'all') {
            try {
                $erpFilters = [];
                $erpParams = [];
                
                if (isset($_GET['category'])) {
                    $erpFilters[] = 'ec.category = :category';
                    $erpParams[':category'] = $_GET['category'];
                }
                
                if (isset($_GET['status'])) {
                    $erpFilters[] = 'ec.status = :status';
                    $erpParams[':status'] = $_GET['status'];
                }
                
                if (isset($_GET['search'])) {
                    $searchTerm = '%' . $_GET['search'] . '%';
                    $erpFilters[] = '(ec.part_number LIKE :search1 OR ec.name LIKE :search2 OR ec.description LIKE :search3 OR ec.mpn LIKE :search4)';
                    $erpParams[':search1'] = $searchTerm;
                    $erpParams[':search2'] = $searchTerm;
                    $erpParams[':search3'] = $searchTerm;
                    $erpParams[':search4'] = $searchTerm;
                }
                
                $erpWhere = !empty($erpFilters) ? 'WHERE ' . implode(' AND ', $erpFilters) : '';
                $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : '';
                
                $sqlErp = "SELECT ec.*, NULL as created_by_name, 'erp' as source,
                                  COALESCE((
                                      SELECT COUNT(DISTINCT br.bom_id)
                                      FROM bom_items bi
                                      JOIN bom_groups bg ON bi.group_id = bg.id
                                      JOIN bom_revisions br ON bg.revision_id = br.id
                                      WHERE bi.component_id = ec.id AND bi.component_source = 'erp'
                                  ), 0) as where_used_count
                           FROM erp_components ec
                           $erpWhere
                           ORDER BY ec.part_number
                           $limitClause";
                $stmtErp = $pdo->prepare($sqlErp);
                $stmtErp->execute($erpParams);
                $erpComponents = $stmtErp->fetchAll();
                
                $allComponents = array_merge($allComponents, $erpComponents);
            } catch (Exception $e) {
                // If ERP table is missing or unavailable, degrade gracefully
                error_log('List ERP Components Warning: ' . $e->getMessage());
            }
        }
        
        // Sort by category then name for a stable merged list (only when fetching all)
        if ($source === 'all') {
            usort($allComponents, function($a, $b) {
                $catA = strtolower($a['category'] ?? '');
                $catB = strtolower($b['category'] ?? '');
                if ($catA === $catB) {
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                }
                return $catA <=> $catB;
            });
        }
        
        sendSuccess($allComponents);
    } catch (Exception $e) {
        error_log('List Components Error: ' . $e->getMessage());
        sendError('Failed to retrieve components', 500);
    }
}

function getComponentById($pdo, $id) {
    try {
        $source = $_GET['source'] ?? null;
        $component = null;
        
        // Try Bommer components unless source explicitly set to ERP
        if ($source === null || $source === 'bommer') {
            $stmt = $pdo->prepare(
                "SELECT c.*, u.full_name as created_by_name, 'bommer' as source, NULL as last_sync_at, NULL as erp_sync_status
                 FROM components c
                 LEFT JOIN users u ON c.created_by = u.id
                 WHERE c.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $component = $stmt->fetch();
        }
        
        // If not found or explicitly requesting ERP, try ERP components
        if ((!$component && ($source === null || $source === 'erp')) || $source === 'erp') {
            try {
                $stmt = $pdo->prepare(
                    "SELECT ec.*, NULL as created_by_name, 'erp' as source
                     FROM erp_components ec
                     WHERE ec.id = :id"
                );
                $stmt->execute([':id' => $id]);
                $component = $stmt->fetch();
            } catch (Exception $e) {
                // ERP table might not exist yet; ignore and fall through
                error_log('Get ERP Component Warning: ' . $e->getMessage());
            }
        }
        
        if (!$component) {
            sendError('Component not found', 404);
        }
        
        // Get where-used information from BOMs
        try {
            $params = [
                ':component_id' => $id,
            ];
            $whereSource = '';
            if (!empty($component['source'])) {
                $whereSource = ' AND bi.component_source = :component_source';
                $params[':component_source'] = $component['source'];
            }
            
            $stmt = $pdo->prepare(
                "SELECT DISTINCT b.id, b.sku, b.name, 
                        p.name as project_name, p.code as project_code,
                        br.status as bom_status
                 FROM bom_items bi
                 JOIN bom_groups bg ON bi.group_id = bg.id
                 JOIN bom_revisions br ON bg.revision_id = br.id
                 JOIN boms b ON br.bom_id = b.id
                 JOIN projects p ON b.project_id = p.id
                 WHERE bi.component_id = :component_id" . $whereSource . "
                 ORDER BY b.name"
            );
            $stmt->execute($params);
            $component['used_in_boms'] = $stmt->fetchAll();
        } catch (Exception $e) {
            // If BOM tables are unavailable for any reason, log and continue
            error_log('Get Component Where-Used Warning: ' . $e->getMessage());
            $component['used_in_boms'] = [];
        }
        
        sendSuccess($component);
    } catch (Exception $e) {
        error_log('Get Component Error: ' . $e->getMessage());
        sendError('Failed to retrieve component', 500);
    }
}

function handlePost($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['part_number', 'name']);
        
        $stmt = $pdo->prepare("SELECT id FROM components WHERE part_number = :part_number");
        $stmt->execute([':part_number' => $data['part_number']]);
        if ($stmt->fetch()) {
            sendError('Part number already exists', 400);
        }
        
        $stmt = $pdo->prepare(
            "INSERT INTO components (part_number, name, description, category, manufacturer, mpn, supplier, 
                                    unit_cost, stock_level, min_stock, lead_time_days, status, notes, created_by)
             VALUES (:part_number, :name, :description, :category, :manufacturer, :mpn, :supplier,
                     :unit_cost, :stock_level, :min_stock, :lead_time_days, :status, :notes, :created_by)"
        );
        
        $stmt->execute([
            ':part_number' => sanitizeString($data['part_number']),
            ':name' => sanitizeString($data['name']),
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'] ?? null,
            ':manufacturer' => $data['manufacturer'] ?? null,
            ':mpn' => $data['mpn'] ?? null,
            ':supplier' => $data['supplier'] ?? null,
            ':unit_cost' => $data['unit_cost'] ?? 0,
            ':stock_level' => $data['stock_level'] ?? 0,
            ':min_stock' => $data['min_stock'] ?? 0,
            ':lead_time_days' => $data['lead_time_days'] ?? 0,
            ':status' => $data['status'] ?? 'active',
            ':notes' => $data['notes'] ?? null,
            ':created_by' => getCurrentUserId()
        ]);
        
        $componentId = $pdo->lastInsertId();
        logAudit($pdo, 'create_component', 'component', $componentId, ['part_number' => $data['part_number'], 'name' => $data['name']]);
        
        sendSuccess(['id' => $componentId], 'Component created successfully');
    } catch (Exception $e) {
        error_log('Create Component Error: ' . $e->getMessage());
        sendError('Failed to create component', 500);
    }
}

function handlePut($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['id']);
        
        $updates = [];
        $params = [':id' => $data['id']];
        
        $allowed = ['name', 'description', 'category', 'manufacturer', 'mpn', 'supplier', 
                   'unit_cost', 'stock_level', 'min_stock', 'lead_time_days', 'status', 'notes'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = in_array($field, ['name']) ? sanitizeString($data[$field]) : $data[$field];
            }
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $sql = "UPDATE components SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        logAudit($pdo, 'update_component', 'component', $data['id'], $data);
        
        sendSuccess(null, 'Component updated successfully');
    } catch (Exception $e) {
        error_log('Update Component Error: ' . $e->getMessage());
        sendError('Failed to update component', 500);
    }
}

function handleDelete($pdo) {
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendError('Component ID required', 400);
        }
        
        // Check if component is used in any BOMs
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bom_items WHERE component_id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            sendError('Cannot delete component that is used in BOMs', 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM components WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        logAudit($pdo, 'delete_component', 'component', $id);
        
        sendSuccess(null, 'Component deleted successfully');
    } catch (Exception $e) {
        error_log('Delete Component Error: ' . $e->getMessage());
        sendError('Failed to delete component', 500);
    }
}
