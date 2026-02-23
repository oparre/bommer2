<?php
/**
 * Projects API Endpoints
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
    $action = $_GET['action'] ?? null;
    
    // Handle special actions
    if ($action === 'list_devapp_projects') {
        listDevappProjects();
        return;
    }
    
    $id = $_GET['id'] ?? null;
    $includeOptionals = isset($_GET['include_optionals']) && $_GET['include_optionals'] === '1';
    
    if ($id) {
        getProjectById($pdo, $id, $includeOptionals);
    } else {
        listProjects($pdo, $includeOptionals);
    }
}

function listProjects($pdo, $includeOptionals = false) {
    try {
        $filters = [];
        $params = [];
        
        if (isset($_GET['status'])) {
            $filters[] = 'status = :status';
            $params[':status'] = $_GET['status'];
        }
        
        if (isset($_GET['search'])) {
            $searchTerm = '%' . $_GET['search'] . '%';
            $filters[] = '(p.code LIKE :search1 OR p.name LIKE :search2 OR p.description LIKE :search3)';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }
        
        $where = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
        
        $sql = "SELECT p.*, 
                       u1.full_name as owner_name,
                       u2.full_name as created_by_name,
                       COUNT(DISTINCT b.id) as bom_count
                FROM projects p
                LEFT JOIN users u1 ON p.owner_id = u1.id
                LEFT JOIN users u2 ON p.created_by = u2.id
                LEFT JOIN boms b ON p.id = b.project_id
                $where
                GROUP BY p.id
                ORDER BY p.updated_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll();
        
        if ($includeOptionals && !empty($projects)) {
            $projectIds = array_column($projects, 'id');
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            
            // Get all links for these projects
            $sql = "SELECT po.*, p.code as optional_project_code, p.name as optional_project_name
                    FROM project_optionals po
                    JOIN projects p ON po.optional_project_id = p.id
                    WHERE po.base_project_id IN ($placeholders)
                    ORDER BY po.base_project_id, po.display_order, p.name";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($projectIds);
            $links = $stmt->fetchAll();
            
            $linksByProject = [];
            foreach ($links as $link) {
                $baseId = $link['base_project_id'];
                if (!isset($linksByProject[$baseId])) {
                    $linksByProject[$baseId] = [];
                }
                $linksByProject[$baseId][] = $link;
            }
            
            foreach ($projects as &$project) {
                $project['optionals'] = $linksByProject[$project['id']] ?? [];
            }
            unset($project);
        }
        
        sendSuccess($projects);
    } catch (Exception $e) {
        error_log('List Projects Error: ' . $e->getMessage());
        sendError('Failed to retrieve projects', 500);
    }
}

function getProjectById($pdo, $id, $includeOptionals = false) {
    try {
        $stmt = $pdo->prepare(
            "SELECT p.*, 
                    u1.full_name as owner_name,
                    u2.full_name as created_by_name
             FROM projects p
             LEFT JOIN users u1 ON p.owner_id = u1.id
             LEFT JOIN users u2 ON p.created_by = u2.id
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $project = $stmt->fetch();
        
        if (!$project) {
            sendError('Project not found', 404);
        }
        
        // Get BOMs for this project
        $stmt = $pdo->prepare(
            "SELECT b.*, br.status as current_status
             FROM boms b
             LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
             WHERE b.project_id = :project_id
             ORDER BY b.updated_at DESC"
        );
        $stmt->execute([':project_id' => $id]);
        $project['boms'] = $stmt->fetchAll();
        
        // Load optionals if requested
        if ($includeOptionals) {
            $stmt = $pdo->prepare(
                "SELECT p.*,
                        po.display_name,
                        po.description AS optional_description,
                        po.is_default,
                        po.display_order
                 FROM project_optionals po
                 JOIN projects p ON po.optional_project_id = p.id
                 WHERE po.base_project_id = :base_project_id
                 ORDER BY po.display_order, p.name"
            );
            $stmt->execute([':base_project_id' => $id]);
            $optionals = $stmt->fetchAll();

            if ($optionals) {
                $optionalProjectIds = array_column($optionals, 'id');

                if (!empty($optionalProjectIds)) {
                    $placeholders = implode(',', array_fill(0, count($optionalProjectIds), '?'));
                    $stmt = $pdo->prepare(
                        "SELECT b.*, br.status as current_status
                         FROM boms b
                         LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
                         WHERE b.project_id IN ($placeholders)
                         ORDER BY b.updated_at DESC"
                    );
                    $stmt->execute($optionalProjectIds);

                    $bomsByProject = [];
                    foreach ($stmt->fetchAll() as $bom) {
                        $projectId = $bom['project_id'];
                        if (!isset($bomsByProject[$projectId])) {
                            $bomsByProject[$projectId] = [];
                        }
                        $bomsByProject[$projectId][] = $bom;
                    }

                    foreach ($optionals as &$optionalProject) {
                        $projectId = $optionalProject['id'];
                        $optionalProject['boms'] = $bomsByProject[$projectId] ?? [];
                    }
                    unset($optionalProject);
                }
            } else {
                $optionals = [];
            }

            $project['optionals'] = $optionals;
        } else {
            $project['optionals'] = [];
        }
        
        sendSuccess($project);
    } catch (Exception $e) {
        error_log('Get Project Error: ' . $e->getMessage());
        sendError('Failed to retrieve project', 500);
    }
}

function handlePost($pdo) {
    $action = $_GET['action'] ?? null;

    if ($action === 'link_optional') {
        linkOptionalProject($pdo);
        return;
    }

    if ($action === 'unlink_optional') {
        unlinkOptionalProject($pdo);
        return;
    }

    if ($action === 'list_all_optionals') {
        listAllOptionals($pdo);
        return;
    }

    if ($action === 'list_devapp_projects') {
        listDevappProjects();
        return;
    }

    if ($action === 'import_from_devapp') {
        importFromDevapp($pdo);
        return;
    }

    try {
        $data = getJsonInput();
        validateRequired($data, ['code', 'name']);
        
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE code = :code");
        $stmt->execute([':code' => $data['code']]);
        if ($stmt->fetch()) {
            sendError('Project code already exists', 200);
        }
        
        $stmt = $pdo->prepare(
            "INSERT INTO projects (code, name, description, status, priority, is_optional, optional_category, owner_id, created_by)
             VALUES (:code, :name, :description, :status, :priority, :is_optional, :optional_category, :owner_id, :created_by)"
        );
        
        $stmt->execute([
            ':code' => sanitizeString($data['code']),
            ':name' => sanitizeString($data['name']),
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'] ?? 'planning',
            ':priority' => $data['priority'] ?? 'medium',
            ':is_optional' => isset($data['is_optional']) ? (int)$data['is_optional'] : 0,
            ':optional_category' => $data['optional_category'] ?? null,
            ':owner_id' => $data['owner_id'] ?? getCurrentUserId(),
            ':created_by' => getCurrentUserId()
        ]);
        
        $projectId = $pdo->lastInsertId();
        logAudit($pdo, 'create_project', 'project', $projectId, ['code' => $data['code'], 'name' => $data['name']]);
        
        sendSuccess(['id' => $projectId], 'Project created successfully');
    } catch (Exception $e) {
        error_log('Create Project Error: ' . $e->getMessage());
        sendError('Failed to create project', 500);
    }
}

function linkOptionalProject($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['base_project_id', 'optional_project_id']);

        $baseId = (int)$data['base_project_id'];
        $optionalId = (int)$data['optional_project_id'];

        if ($baseId === $optionalId) {
            sendError('Base project and optional project must be different', 200);
        }

        // Check if link already exists
        $stmt = $pdo->prepare(
            "SELECT id FROM project_optionals 
             WHERE base_project_id = :base_id AND optional_project_id = :optional_id"
        );
        $stmt->execute([
            ':base_id' => $baseId,
            ':optional_id' => $optionalId
        ]);
        if ($stmt->fetch()) {
            sendError('Optional project is already linked to this project', 200);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO project_optionals (
                base_project_id,
                optional_project_id,
                display_name,
                description,
                is_default,
                display_order,
                created_by
            ) VALUES (
                :base_id,
                :optional_id,
                :display_name,
                :description,
                :is_default,
                :display_order,
                :created_by
            )"
        );

        $stmt->execute([
            ':base_id' => $baseId,
            ':optional_id' => $optionalId,
            ':display_name' => $data['display_name'] ?? null,
            ':description' => $data['description'] ?? null,
            ':is_default' => isset($data['is_default']) ? (int)$data['is_default'] : 0,
            ':display_order' => isset($data['display_order']) ? (int)$data['display_order'] : 0,
            ':created_by' => getCurrentUserId()
        ]);

        logAudit($pdo, 'link_project_optional', 'project', $baseId, [
            'optional_project_id' => $optionalId,
            'display_name' => $data['display_name'] ?? null,
            'is_default' => isset($data['is_default']) ? (int)$data['is_default'] : 0,
            'display_order' => isset($data['display_order']) ? (int)$data['display_order'] : 0
        ]);

        sendSuccess(null, 'Optional project linked successfully');
    } catch (Exception $e) {
        error_log('Link Optional Project Error: ' . $e->getMessage());
        sendError('Failed to link optional project', 500);
    }
}

function unlinkOptionalProject($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['base_project_id', 'optional_project_id']);

        $baseId = (int)$data['base_project_id'];
        $optionalId = (int)$data['optional_project_id'];

        $stmt = $pdo->prepare(
            "DELETE FROM project_optionals
             WHERE base_project_id = :base_id AND optional_project_id = :optional_id"
        );
        $stmt->execute([
            ':base_id' => $baseId,
            ':optional_id' => $optionalId
        ]);

        logAudit($pdo, 'unlink_project_optional', 'project', $baseId, [
            'optional_project_id' => $optionalId
        ]);

        sendSuccess(null, 'Optional project unlinked successfully');
    } catch (Exception $e) {
        error_log('Unlink Optional Project Error: ' . $e->getMessage());
        sendError('Failed to unlink optional project', 500);
    }
}

function listAllOptionals($pdo) {
    try {
        $filters = [];
        $params = [];
        
        if (isset($_GET['search'])) {
            $searchTerm = '%' . $_GET['search'] . '%';
            $filters[] = '(p.code LIKE :search1 OR p.name LIKE :search2 OR p.description LIKE :search3)';
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
        }
        
        $where = !empty($filters) ? 'AND ' . implode(' AND ', $filters) : '';
        
        // Get all projects marked as optional
        $sql = "SELECT p.*, 
                       u1.full_name as owner_name,
                       COUNT(DISTINCT b.id) as bom_count
                FROM projects p
                LEFT JOIN users u1 ON p.owner_id = u1.id
                LEFT JOIN boms b ON p.id = b.project_id
                WHERE p.is_optional = 1 $where
                GROUP BY p.id
                ORDER BY p.name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $optionals = $stmt->fetchAll();
        
        if (!empty($optionals)) {
            $optionalIds = array_column($optionals, 'id');
            $placeholders = implode(',', array_fill(0, count($optionalIds), '?'));
            
            // Get links for these optionals
            $sql = "SELECT po.*, p.code as base_project_code, p.name as base_project_name
                    FROM project_optionals po
                    JOIN projects p ON po.base_project_id = p.id
                    WHERE po.optional_project_id IN ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($optionalIds);
            $links = $stmt->fetchAll();
            
            $linksByOptional = [];
            foreach ($links as $link) {
                $optId = $link['optional_project_id'];
                if (!isset($linksByOptional[$optId])) {
                    $linksByOptional[$optId] = [];
                }
                $linksByOptional[$optId][] = $link;
            }
            
            foreach ($optionals as &$opt) {
                $opt['links'] = $linksByOptional[$opt['id']] ?? [];
            }
            unset($opt);
        }
        
        sendSuccess($optionals);
    } catch (Exception $e) {
        error_log('List All Optionals Error: ' . $e->getMessage());
        sendError('Failed to retrieve optionals', 500);
    }
}

function handlePut($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['id']);
        
        $updates = [];
        $params = [':id' => $data['id']];
        
        $allowed = ['name', 'description', 'status', 'priority', 'owner_id', 'is_optional', 'optional_category'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                if ($field === 'name') {
                    $params[":$field"] = sanitizeString($data[$field]);
                } elseif ($field === 'is_optional') {
                    $params[":$field"] = (int)$data[$field];
                } else {
                    $params[":$field"] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $sql = "UPDATE projects SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        logAudit($pdo, 'update_project', 'project', $data['id'], $data);
        
        sendSuccess(null, 'Project updated successfully');
    } catch (Exception $e) {
        error_log('Update Project Error: ' . $e->getMessage());
        sendError('Failed to update project', 500);
    }
}

function handleDelete($pdo) {
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendError('Project ID required', 400);
        }
        
        // Check if project has BOMs
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM boms WHERE project_id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            sendError('Cannot delete project with existing BOMs', 400);
        }
        
        $stmt = $pdo->prepare("UPDATE projects SET status = 'cancelled' WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        logAudit($pdo, 'delete_project', 'project', $id);
        
        sendSuccess(null, 'Project deleted successfully');
    } catch (Exception $e) {
        error_log('Delete Project Error: ' . $e->getMessage());
        sendError('Failed to delete project', 500);
    }
}

// ============================================================================
// devapp Integration Functions
// ============================================================================

/**
 * List projects from devapp database
 */
function listDevappProjects() {
    try {
        // Connect to devapp database
        $devappPdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=project_management;charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // Fetch all projects from devapp
        $stmt = $devappPdo->query(
            "SELECT id, name, description, start_date, end_date, status, 
                    created_by_user_id, created_at, updated_at 
             FROM projects 
             ORDER BY name ASC"
        );
        $devappProjects = $stmt->fetchAll();
        
        // Generate codes and map statuses for each project
        foreach ($devappProjects as &$project) {
            // Generate code from name or ID
            $project['generated_code'] = generateProjectCode($project['name'], $project['id']);
            
            // Map devapp status to bommer status
            $project['mapped_status'] = mapDevappStatus($project['status']);
        }
        unset($project);
        
        sendSuccess($devappProjects);
    } catch (PDOException $e) {
        error_log('devapp Connection Error: ' . $e->getMessage());
        sendError('Failed to connect to devapp database', 500);
    } catch (Exception $e) {
        error_log('List devapp Projects Error: ' . $e->getMessage());
        sendError('Failed to retrieve devapp projects', 500);
    }
}

/**
 * Import selected projects from devapp to bommer
 */
function importFromDevapp($pdo) {
    // Disable XDebug HTML errors temporarily
    if (function_exists('ini_set')) {
        ini_set('html_errors', '0');
    }
    
    try {
        $data = getJsonInput();
        
        // Validate project_ids manually (it's an array, can't use validateRequired)
        if (!isset($data['project_ids'])) {
            sendError('Missing required field: project_ids', 400);
        }
        
        if (!is_array($data['project_ids']) || empty($data['project_ids'])) {
            sendError('No projects selected for import', 400);
        }
        
        // Connect to devapp database
        $devappPdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=project_management;charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        $projectIds = array_map('intval', $data['project_ids']);
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        
        // Fetch selected projects from devapp
        $stmt = $devappPdo->prepare(
            "SELECT id, name, description, start_date, end_date, status, 
                    created_by_user_id, created_at 
             FROM projects 
             WHERE id IN ($placeholders)"
        );
        $stmt->execute($projectIds);
        $devappProjects = $stmt->fetchAll();
        
        if (empty($devappProjects)) {
            sendError('No projects found with the selected IDs', 404);
        }
        
        $pdo->beginTransaction();
        
        $importedCount = 0;
        $skippedCount = 0;
        $currentUserId = getCurrentUserId();
        
        foreach ($devappProjects as $devappProject) {
            $code = generateProjectCode($devappProject['name'], $devappProject['id']);
            
            // Check if code already exists
            $stmt = $pdo->prepare("SELECT id FROM projects WHERE code = :code");
            $stmt->execute([':code' => $code]);
            if ($stmt->fetch()) {
                $skippedCount++;
                continue;
            }
            
            // Truncate name to 200 chars if needed
            $name = substr($devappProject['name'], 0, 200);
            
            // Map status
            $status = mapDevappStatus($devappProject['status']);
            
            // Insert into bommer
            $stmt = $pdo->prepare(
                "INSERT INTO projects (
                    code, name, description, status, priority, 
                    is_optional, owner_id, created_by
                ) VALUES (
                    :code, :name, :description, :status, :priority,
                    :is_optional, :owner_id, :created_by
                )"
            );
            
            $stmt->execute([
                ':code' => $code,
                ':name' => sanitizeString($name),
                ':description' => $devappProject['description'] ?? null,
                ':status' => $status,
                ':priority' => 'medium',
                ':is_optional' => 0,
                ':owner_id' => $currentUserId, // Fallback to current user
                ':created_by' => $currentUserId
            ]);
            
            $projectId = $pdo->lastInsertId();
            
            // Log audit
            logAudit($pdo, 'import_project_from_devapp', 'project', $projectId, [
                'devapp_id' => $devappProject['id'],
                'code' => $code,
                'name' => $name
            ]);
            
            $importedCount++;
        }
        
        $pdo->commit();
        
        sendSuccess(
            [
                'imported' => $importedCount,
                'skipped' => $skippedCount
            ],
            "Successfully imported $importedCount project(s)" . 
            ($skippedCount > 0 ? " ($skippedCount skipped due to duplicate codes)" : '')
        );
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $rollbackError) {
                error_log('Rollback Error: ' . $rollbackError->getMessage());
            }
        }
        error_log('devapp Import PDO Error: ' . $e->getMessage());
        sendError('Failed to import projects from devapp: ' . $e->getMessage(), 500);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Exception $rollbackError) {
                error_log('Rollback Error: ' . $rollbackError->getMessage());
            }
        }
        error_log('Import from devapp Error: ' . $e->getMessage());
        sendError('Failed to import projects: ' . $e->getMessage(), 500);
    }
}

/**
 * Generate a project code from name or ID
 */
function generateProjectCode($name, $id) {
    // Strategy: Use first significant word(s) from name + ID
    // Remove common words and special chars
    $cleanName = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
    $words = array_filter(explode(' ', $cleanName));
    
    // Take first word (or first 2 words if first is short)
    $codeBase = '';
    if (!empty($words)) {
        $firstWord = strtoupper($words[0]);
        if (strlen($firstWord) <= 3 && isset($words[1])) {
            $codeBase = substr($firstWord, 0, 3) . substr(strtoupper($words[1]), 0, 3);
        } else {
            $codeBase = substr($firstWord, 0, 6);
        }
    } else {
        $codeBase = 'PRJ';
    }
    
    // Format: CODEBASE-ID (e.g., GAMING-16, ARCADE-13)
    return $codeBase . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
}

/**
 * Map devapp status to bommer status enum
 */
function mapDevappStatus($devappStatus) {
    $statusMap = [
        'Pending' => 'planning',
        'In Progress' => 'active',
        'Completed' => 'completed',
        'On Hold' => 'on-hold',
        'Cancelled' => 'cancelled'
    ];
    
    return $statusMap[$devappStatus] ?? 'planning';
}
