-- ============================================================================
-- Add Assembly-BOM Direct Relationship
-- This allows assemblies to include specific BOMs (SKUs) instead of all BOMs from a project
-- ============================================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: This table coexists with assembly_projects
-- If assembly_boms has entries for an assembly, use those specific BOMs
-- If assembly_boms is empty for an assembly, fall back to all BOMs from assembly_projects
