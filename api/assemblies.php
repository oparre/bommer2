<?php
/**
 * Assemblies API Endpoints
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
        getAssemblyById($pdo, $id);
    } else {
        listAssemblies($pdo);
    }
}

function listAssemblies($pdo) {
    try {
        $filters = [];
        $params = [];
        
        if (isset($_GET['category'])) {
            $filters[] = 'category = :category';
            $params[':category'] = $_GET['category'];
        }
        
        if (isset($_GET['search'])) {
            $searchTerm = '%' . $_GET['search'] . '%';
            $filters[] = '(a.code LIKE :search1 OR a.name LIKE :search2 OR a.description LIKE :search3)';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }
        
        $where = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
        
        $sql = "SELECT a.*, 
                       u.full_name as created_by_name,
                       COUNT(DISTINCT ap.project_id) as project_count
                FROM assemblies a
                LEFT JOIN users u ON a.created_by = u.id
                LEFT JOIN assembly_projects ap ON a.id = ap.assembly_id
                $where
                GROUP BY a.id
                ORDER BY a.updated_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assemblies = $stmt->fetchAll();
        
        sendSuccess($assemblies);
    } catch (Exception $e) {
        error_log('List Assemblies Error: ' . $e->getMessage());
        sendError('Failed to retrieve assemblies', 500);
    }
}

function getAssemblyById($pdo, $id) {
    try {
        $stmt = $pdo->prepare(
            "SELECT a.*, u.full_name as created_by_name
             FROM assemblies a
             LEFT JOIN users u ON a.created_by = u.id
             WHERE a.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $assembly = $stmt->fetch();
        
        if (!$assembly) {
            sendError('Assembly not found', 404);
        }
        
        // Get projects
        $stmt = $pdo->prepare(
            "SELECT p.*, ap.added_at
             FROM projects p
             JOIN assembly_projects ap ON p.id = ap.project_id
             WHERE ap.assembly_id = :assembly_id
             ORDER BY ap.added_at DESC"
        );
        $stmt->execute([':assembly_id' => $id]);
        $assembly['projects'] = $stmt->fetchAll();

        // Attach project optionals (if any)
        if (!empty($assembly['projects'])) {
            $projectIds = array_column($assembly['projects'], 'id');
            if (!empty($projectIds)) {
                $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
                $stmt = $pdo->prepare(
                    "SELECT po.base_project_id,
                            p.*,
                            po.display_name,
                            po.description AS optional_description,
                            po.is_default,
                            po.display_order
                     FROM project_optionals po
                     JOIN projects p ON po.optional_project_id = p.id
                     WHERE po.base_project_id IN ($placeholders)
                     ORDER BY po.base_project_id, po.display_order, p.name"
                );
                $stmt->execute($projectIds);
                $rows = $stmt->fetchAll();

                $optionalsByProject = [];
                foreach ($rows as $row) {
                    $baseId = $row['base_project_id'];
                    if (!isset($optionalsByProject[$baseId])) {
                        $optionalsByProject[$baseId] = [];
                    }
                    $optionalsByProject[$baseId][] = $row;
                }

                foreach ($assembly['projects'] as &$project) {
                    $projectId = $project['id'];
                    $project['optionals'] = $optionalsByProject[$projectId] ?? [];
                }
                unset($project);
            }
        }
        
        // Get BOMs - check if there are specific BOMs selected, otherwise get all from projects
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assembly_boms WHERE assembly_id = :assembly_id");
        $stmt->execute([':assembly_id' => $id]);
        $hasSpecificBOMs = $stmt->fetch()['count'] > 0;
        
        if ($hasSpecificBOMs) {
            // Get specific BOMs selected for this assembly
            $stmt = $pdo->prepare(
                "SELECT b.*, p.name as project_name, br.status as current_status
                 FROM boms b
                 JOIN assembly_boms ab ON b.id = ab.bom_id
                 JOIN projects p ON b.project_id = p.id
                 LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
                 WHERE ab.assembly_id = :assembly_id
                 ORDER BY p.name, b.name"
            );
        } else {
            // Fall back to all BOMs from projects in this assembly
            $stmt = $pdo->prepare(
                "SELECT b.*, p.name as project_name, br.status as current_status
                 FROM boms b
                 JOIN projects p ON b.project_id = p.id
                 JOIN assembly_projects ap ON p.id = ap.project_id
                 LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
                 WHERE ap.assembly_id = :assembly_id
                 ORDER BY p.name, b.name"
            );
        }
        $stmt->execute([':assembly_id' => $id]);
        $assembly['boms'] = $stmt->fetchAll();
        $assembly['has_specific_boms'] = $hasSpecificBOMs;
        
        // Get selected BOM IDs (for editing)
        if ($hasSpecificBOMs) {
            $stmt = $pdo->prepare("SELECT bom_id FROM assembly_boms WHERE assembly_id = :assembly_id");
            $stmt->execute([':assembly_id' => $id]);
            $assembly['selected_bom_ids'] = array_column($stmt->fetchAll(), 'bom_id');
        }
        
        sendSuccess($assembly);
    } catch (Exception $e) {
        error_log('Get Assembly Error: ' . $e->getMessage());
        sendError('Failed to retrieve assembly', 500);
    }
}

function handlePost($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['code', 'name']);
        
        $stmt = $pdo->prepare("SELECT id FROM assemblies WHERE code = :code");
        $stmt->execute([':code' => $data['code']]);
        if ($stmt->fetch()) {
            sendError('Assembly code already exists', 400);
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare(
            "INSERT INTO assemblies (code, name, description, category, created_by)
             VALUES (:code, :name, :description, :category, :created_by)"
        );
        
        $stmt->execute([
            ':code' => sanitizeString($data['code']),
            ':name' => sanitizeString($data['name']),
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'] ?? null,
            ':created_by' => getCurrentUserId()
        ]);
        
        $assemblyId = $pdo->lastInsertId();
        
        // Add projects if provided
        if (isset($data['project_ids']) && is_array($data['project_ids'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO assembly_projects (assembly_id, project_id, added_by)
                 VALUES (:assembly_id, :project_id, :added_by)"
            );
            
            foreach ($data['project_ids'] as $projectId) {
                $stmt->execute([
                    ':assembly_id' => $assemblyId,
                    ':project_id' => $projectId,
                    ':added_by' => getCurrentUserId()
                ]);
            }
        }
        
        // Add specific BOMs if provided
        if (isset($data['bom_ids']) && is_array($data['bom_ids']) && !empty($data['bom_ids'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO assembly_boms (assembly_id, bom_id, added_by)
                 VALUES (:assembly_id, :bom_id, :added_by)"
            );
            
            foreach ($data['bom_ids'] as $bomId) {
                $stmt->execute([
                    ':assembly_id' => $assemblyId,
                    ':bom_id' => $bomId,
                    ':added_by' => getCurrentUserId()
                ]);
            }
        }
        
        logAudit($pdo, 'create_assembly', 'assembly', $assemblyId, ['code' => $data['code'], 'name' => $data['name']]);
        
        $pdo->commit();
        
        sendSuccess(['id' => $assemblyId], 'Assembly created successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Create Assembly Error: ' . $e->getMessage());
        sendError('Failed to create assembly', 500);
    }
}

function handlePut($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['id']);
        
        $pdo->beginTransaction();
        
        // Update basic info
        $updates = [];
        $params = [':id' => $data['id']];
        
        $allowed = ['name', 'description', 'category'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $field === 'name' ? sanitizeString($data[$field]) : $data[$field];
            }
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE assemblies SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Update projects if provided
        if (isset($data['project_ids'])) {
            // Remove existing
            $stmt = $pdo->prepare("DELETE FROM assembly_projects WHERE assembly_id = :id");
            $stmt->execute([':id' => $data['id']]);
            
            // Add new
            if (is_array($data['project_ids']) && !empty($data['project_ids'])) {
                $stmt = $pdo->prepare(
                    "INSERT INTO assembly_projects (assembly_id, project_id, added_by)
                     VALUES (:assembly_id, :project_id, :added_by)"
                );
                
                foreach ($data['project_ids'] as $projectId) {
                    $stmt->execute([
                        ':assembly_id' => $data['id'],
                        ':project_id' => $projectId,
                        ':added_by' => getCurrentUserId()
                    ]);
                }
            }
        }
        
        // Update specific BOMs if provided
        if (isset($data['bom_ids'])) {
            // Remove existing
            $stmt = $pdo->prepare("DELETE FROM assembly_boms WHERE assembly_id = :id");
            $stmt->execute([':id' => $data['id']]);
            
            // Add new
            if (is_array($data['bom_ids']) && !empty($data['bom_ids'])) {
                $stmt = $pdo->prepare(
                    "INSERT INTO assembly_boms (assembly_id, bom_id, added_by)
                     VALUES (:assembly_id, :bom_id, :added_by)"
                );
                
                foreach ($data['bom_ids'] as $bomId) {
                    $stmt->execute([
                        ':assembly_id' => $data['id'],
                        ':bom_id' => $bomId,
                        ':added_by' => getCurrentUserId()
                    ]);
                }
            }
        }
        
        logAudit($pdo, 'update_assembly', 'assembly', $data['id'], $data);
        
        $pdo->commit();
        
        sendSuccess(null, 'Assembly updated successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Update Assembly Error: ' . $e->getMessage());
        sendError('Failed to update assembly', 500);
    }
}

function handleDelete($pdo) {
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendError('Assembly ID required', 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM assemblies WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        logAudit($pdo, 'delete_assembly', 'assembly', $id);
        
        sendSuccess(null, 'Assembly deleted successfully');
    } catch (Exception $e) {
        error_log('Delete Assembly Error: ' . $e->getMessage());
        sendError('Failed to delete assembly', 500);
    }
}
