<?php
/**
 * Stats API — BOM Search and Context
 * Used by the interactive mindmap to drive live entity data.
 */

require_once __DIR__ . '/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

requireApiAuth();

$pdo    = getDb();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'bom_search':
        handleBomSearch($pdo);
        break;
    case 'bom_context':
        handleBomContext($pdo);
        break;
    default:
        sendError('Unknown action', 400);
}

// ─── BOM SEARCH ────────────────────────────────────────────────────────────
function handleBomSearch($pdo) {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        sendJson([]);
    }

    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        "SELECT id, sku, name
         FROM boms
         WHERE sku LIKE :q1 OR name LIKE :q2
         ORDER BY sku ASC
         LIMIT 10"
    );
    $stmt->execute([':q1' => $like, ':q2' => $like]);
    sendJson($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ─── BOM CONTEXT ───────────────────────────────────────────────────────────
function handleBomContext($pdo) {
    $bomId = (int)($_GET['bom_id'] ?? 0);
    if ($bomId <= 0) {
        sendError('bom_id required', 400);
    }

    // 1. BOM + creator
    $stmt = $pdo->prepare(
        "SELECT b.id, b.sku, b.name, b.variant_group, b.current_revision,
                u.id AS user_id, u.full_name
         FROM boms b
         JOIN users u ON u.id = b.created_by
         WHERE b.id = :id"
    );
    $stmt->execute([':id' => $bomId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendError('BOM not found', 404);
    }

    $bom = [
        'id'               => (int)$row['id'],
        'sku'              => $row['sku'],
        'name'             => $row['name'],
        'variant_group'    => $row['variant_group'],
        'current_revision' => (int)$row['current_revision'],
    ];
    $createdBy = [
        'id'        => (int)$row['user_id'],
        'full_name' => $row['full_name'],
    ];

    // 2. Project
    $stmt = $pdo->prepare(
        "SELECT p.id, p.code, p.name, p.status
         FROM projects p
         JOIN boms b ON b.project_id = p.id
         WHERE b.id = :id"
    );
    $stmt->execute([':id' => $bomId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($project) {
        $project['id'] = (int)$project['id'];
    }

    // 3. Products — try product_boms first, fall back to product_projects
    $stmt = $pdo->prepare(
        "SELECT p.id, p.code, p.name
         FROM products p
         JOIN product_boms pb ON pb.product_id = p.id
         WHERE pb.bom_id = :bom_id
         ORDER BY p.code"
    );
    $stmt->execute([':bom_id' => $bomId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products) && $project) {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.code, p.name
             FROM products p
             JOIN product_projects pp ON pp.product_id = p.id
             WHERE pp.project_id = :pid
             ORDER BY p.code"
        );
        $stmt->execute([':pid' => $project['id']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($products as &$p) { $p['id'] = (int)$p['id']; }
    unset($p);

    // 4. Revisions (newest first)
    $stmt = $pdo->prepare(
        "SELECT id, revision_number, status
         FROM bom_revisions
         WHERE bom_id = :id
         ORDER BY revision_number DESC"
    );
    $stmt->execute([':id' => $bomId]);
    $revisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($revisions as &$r) {
        $r['id']              = (int)$r['id'];
        $r['revision_number'] = (int)$r['revision_number'];
    }
    unset($r);

    // 5. Current revision id
    $currentRevId = null;
    foreach ($revisions as $r) {
        if ($r['revision_number'] === $bom['current_revision']) {
            $currentRevId = $r['id'];
            break;
        }
    }
    // Fallback: latest revision
    if (!$currentRevId && !empty($revisions)) {
        $currentRevId = $revisions[0]['id'];
    }

    // 6. Groups + item count per group (from current revision)
    $groups = [];
    $items  = ['count' => 0, 'total_cost' => 0.0];

    if ($currentRevId) {
        $stmt = $pdo->prepare(
            "SELECT bg.id, bg.name, COUNT(bi.id) AS item_count
             FROM bom_groups bg
             LEFT JOIN bom_items bi ON bi.group_id = bg.id
             WHERE bg.revision_id = :rid
             GROUP BY bg.id, bg.name
             ORDER BY bg.display_order"
        );
        $stmt->execute([':rid' => $currentRevId]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($groups as &$g) {
            $g['id']         = (int)$g['id'];
            $g['item_count'] = (int)$g['item_count'];
        }
        unset($g);

        // Total item count + weighted cost
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt,
                    SUM(CASE
                        WHEN bi.component_source = 'bommer' THEN bi.quantity * COALESCE(c.unit_cost,  0)
                        WHEN bi.component_source = 'erp'    THEN bi.quantity * COALESCE(ec.unit_cost, 0)
                        ELSE 0 END) AS total_cost
             FROM bom_items bi
             JOIN bom_groups bg ON bi.group_id = bg.id
             LEFT JOIN components    c  ON bi.component_id = c.id  AND bi.component_source = 'bommer'
             LEFT JOIN erp_components ec ON bi.component_id = ec.id AND bi.component_source = 'erp'
             WHERE bg.revision_id = :rid"
        );
        $stmt->execute([':rid' => $currentRevId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $items = [
            'count'      => (int)$row['cnt'],
            'total_cost' => round((float)$row['total_cost'], 2),
        ];
    }

    // 7. Distinct Bommer components
    $components = [];
    if ($currentRevId) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT c.id, c.part_number, c.name
             FROM bom_items bi
             JOIN bom_groups bg ON bi.group_id = bg.id
             JOIN components c  ON bi.component_id = c.id
             WHERE bg.revision_id = :rid AND bi.component_source = 'bommer'
             ORDER BY c.part_number
             LIMIT 20"
        );
        $stmt->execute([':rid' => $currentRevId]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($components as &$c) { $c['id'] = (int)$c['id']; }
        unset($c);
    }

    // 8. Distinct ERP components
    $erpComponents = [];
    if ($currentRevId) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT ec.id, ec.part_number, ec.name
             FROM bom_items bi
             JOIN bom_groups bg  ON bi.group_id = bg.id
             JOIN erp_components ec ON bi.component_id = ec.id
             WHERE bg.revision_id = :rid AND bi.component_source = 'erp'
             ORDER BY ec.part_number
             LIMIT 20"
        );
        $stmt->execute([':rid' => $currentRevId]);
        $erpComponents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($erpComponents as &$e) { $e['id'] = (int)$e['id']; }
        unset($e);
    }

    // 9. Recent audit events for this BOM
    $stmt = $pdo->prepare(
        "SELECT action, DATE_FORMAT(created_at, '%d/%m/%y %H:%i') AS created_at
         FROM audit_logs
         WHERE entity_type = 'bom' AND entity_id = :id
         ORDER BY created_at DESC
         LIMIT 5"
    );
    $stmt->execute([':id' => $bomId]);
    $recentAudit = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJson([
        'bom'            => $bom,
        'project'        => $project,
        'products'       => $products,
        'revisions'      => $revisions,
        'groups'         => $groups,
        'items'          => $items,
        'components'     => $components,
        'erp_components' => $erpComponents,
        'created_by'     => $createdBy,
        'recent_audit'   => $recentAudit,
    ]);
}
