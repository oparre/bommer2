<?php
require_once __DIR__ . '/config/database.php';

$pdo = getDb();

echo "Creating assembly_boms table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS assembly_boms (
    assembly_id INT UNSIGNED NOT NULL,
    bom_id INT UNSIGNED NOT NULL,
    added_by INT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assembly_id, bom_id),
    FOREIGN KEY (assembly_id) REFERENCES assemblies(id) ON DELETE CASCADE,
    FOREIGN KEY (bom_id) REFERENCES boms(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id),
    INDEX idx_assembly (assembly_id),
    INDEX idx_bom (bom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $pdo->exec($sql);
    echo "✓ Table assembly_boms created successfully!\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
