<?php
/**
 * Products API Endpoints
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
        getProductById($pdo, $id);
    } else {
        listProducts($pdo);
    }
}

function listProducts($pdo) {
    try {
        $filters = [];
        $params = [];
        
        if (isset($_GET['category'])) {
            $filters[] = 'category = :category';
            $params[':category'] = $_GET['category'];
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
                       u.full_name as created_by_name,
                       COUNT(DISTINCT pp.project_id) as project_count
                FROM products p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN product_projects pp ON p.id = pp.product_id
                $where
                GROUP BY p.id
                ORDER BY p.updated_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        sendSuccess($products);
    } catch (Exception $e) {
        error_log('List Products Error: ' . $e->getMessage());
        sendError('Failed to retrieve products', 500);
    }
}

function getProductById($pdo, $id) {
    try {
        $stmt = $pdo->prepare(
            "SELECT p.*, u.full_name as created_by_name
             FROM products p
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            sendError('Product not found', 404);
        }
        
        // Get projects
        $stmt = $pdo->prepare(
            "SELECT p.*, pp.added_at
             FROM projects p
             JOIN product_projects pp ON p.id = pp.project_id
             WHERE pp.product_id = :product_id
             ORDER BY pp.added_at DESC"
        );
        $stmt->execute([':product_id' => $id]);
        $product['projects'] = $stmt->fetchAll();

        // Attach project optionals (if any)
        if (!empty($product['projects'])) {
            $projectIds = array_column($product['projects'], 'id');
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

                foreach ($product['projects'] as &$project) {
                    $projectId = $project['id'];
                    $project['optionals'] = $optionalsByProject[$projectId] ?? [];
                }
                unset($project);
            }
        }
        
        // Get BOMs - check if there are specific BOMs selected, otherwise get all from projects
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM product_boms WHERE product_id = :product_id");
        $stmt->execute([':product_id' => $id]);
        $hasSpecificBOMs = $stmt->fetch()['count'] > 0;
        
        if ($hasSpecificBOMs) {
            // Get specific BOMs selected for this product
            $stmt = $pdo->prepare(
                "SELECT b.*, p.name as project_name, br.status as current_status
                 FROM boms b
                 JOIN product_boms pb ON b.id = pb.bom_id
                 JOIN projects p ON b.project_id = p.id
                 LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
                 WHERE pb.product_id = :product_id
                 ORDER BY p.name, b.name"
            );
        } else {
            // Fall back to all BOMs from projects in this product
            $stmt = $pdo->prepare(
                "SELECT b.*, p.name as project_name, br.status as current_status
                 FROM boms b
                 JOIN projects p ON b.project_id = p.id
                 JOIN product_projects pp ON p.id = pp.project_id
                 LEFT JOIN bom_revisions br ON b.id = br.bom_id AND br.revision_number = b.current_revision
                 WHERE pp.product_id = :product_id
                 ORDER BY p.name, b.name"
            );
        }
        $stmt->execute([':product_id' => $id]);
        $product['boms'] = $stmt->fetchAll();
        $product['has_specific_boms'] = $hasSpecificBOMs;
        
        // Get selected BOM IDs (for editing)
        if ($hasSpecificBOMs) {
            $stmt = $pdo->prepare("SELECT bom_id FROM product_boms WHERE product_id = :product_id");
            $stmt->execute([':product_id' => $id]);
            $product['selected_bom_ids'] = array_column($stmt->fetchAll(), 'bom_id');
        }
        
        sendSuccess($product);
    } catch (Exception $e) {
        error_log('Get Product Error: ' . $e->getMessage());
        sendError('Failed to retrieve product', 500);
    }
}

function handlePost($pdo) {
    try {
        $data = getJsonInput();
        validateRequired($data, ['code', 'name']);
        
        $stmt = $pdo->prepare("SELECT id FROM products WHERE code = :code");
        $stmt->execute([':code' => $data['code']]);
        if ($stmt->fetch()) {
            sendError('Product code already exists', 400);
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare(
            "INSERT INTO products (code, name, description, category, created_by)
             VALUES (:code, :name, :description, :category, :created_by)"
        );
        
        $stmt->execute([
            ':code' => sanitizeString($data['code']),
            ':name' => sanitizeString($data['name']),
            ':description' => $data['description'] ?? null,
            ':category' => $data['category'] ?? null,
            ':created_by' => getCurrentUserId()
        ]);
        
        $productId = $pdo->lastInsertId();
        
        // Add projects if provided
        if (isset($data['project_ids']) && is_array($data['project_ids'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO product_projects (product_id, project_id, added_by)
                 VALUES (:product_id, :project_id, :added_by)"
            );
            
            foreach ($data['project_ids'] as $projectId) {
                $stmt->execute([
                    ':product_id' => $productId,
                    ':project_id' => $projectId,
                    ':added_by' => getCurrentUserId()
                ]);
            }
        }
        
        // Add specific BOMs if provided
        if (isset($data['bom_ids']) && is_array($data['bom_ids']) && !empty($data['bom_ids'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO product_boms (product_id, bom_id, added_by)
                 VALUES (:product_id, :bom_id, :added_by)"
            );
            
            foreach ($data['bom_ids'] as $bomId) {
                $stmt->execute([
                    ':product_id' => $productId,
                    ':bom_id' => $bomId,
                    ':added_by' => getCurrentUserId()
                ]);
            }
        }
        
        logAudit($pdo, 'create_product', 'product', $productId, ['code' => $data['code'], 'name' => $data['name']]);
        
        $pdo->commit();
        
        sendSuccess(['id' => $productId], 'Product created successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Create Product Error: ' . $e->getMessage());
        sendError('Failed to create product', 500);
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
            $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Update projects if provided
        if (isset($data['project_ids'])) {
            // Remove existing
            $stmt = $pdo->prepare("DELETE FROM product_projects WHERE product_id = :id");
            $stmt->execute([':id' => $data['id']]);
            
            // Add new
            if (is_array($data['project_ids']) && !empty($data['project_ids'])) {
                $stmt = $pdo->prepare(
                    "INSERT INTO product_projects (product_id, project_id, added_by)
                     VALUES (:product_id, :project_id, :added_by)"
                );
                
                foreach ($data['project_ids'] as $projectId) {
                    $stmt->execute([
                        ':product_id' => $data['id'],
                        ':project_id' => $projectId,
                        ':added_by' => getCurrentUserId()
                    ]);
                }
            }
        }
        
        // Update specific BOMs if provided
        if (isset($data['bom_ids'])) {
            // Remove existing
            $stmt = $pdo->prepare("DELETE FROM product_boms WHERE product_id = :id");
            $stmt->execute([':id' => $data['id']]);
            
            // Add new
            if (is_array($data['bom_ids']) && !empty($data['bom_ids'])) {
                $stmt = $pdo->prepare(
                    "INSERT INTO product_boms (product_id, bom_id, added_by)
                     VALUES (:product_id, :bom_id, :added_by)"
                );
                
                foreach ($data['bom_ids'] as $bomId) {
                    $stmt->execute([
                        ':product_id' => $data['id'],
                        ':bom_id' => $bomId,
                        ':added_by' => getCurrentUserId()
                    ]);
                }
            }
        }
        
        logAudit($pdo, 'update_product', 'product', $data['id'], $data);
        
        $pdo->commit();
        
        sendSuccess(null, 'Product updated successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Update Product Error: ' . $e->getMessage());
        sendError('Failed to update product', 500);
    }
}

function handleDelete($pdo) {
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendError('Product ID required', 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        logAudit($pdo, 'delete_product', 'product', $id);
        
        sendSuccess(null, 'Product deleted successfully');
    } catch (Exception $e) {
        error_log('Delete Product Error: ' . $e->getMessage());
        sendError('Failed to delete product', 500);
    }
}
