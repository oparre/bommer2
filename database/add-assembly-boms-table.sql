-- ============================================================================
-- Add Product-BOM Direct Relationship
-- This allows products to include specific BOMs (SKUs) instead of all BOMs from a project
-- ============================================================================

CREATE TABLE IF NOT EXISTS product_boms (
    product_id INT UNSIGNED NOT NULL,
    bom_id INT UNSIGNED NOT NULL,
    added_by INT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, bom_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (bom_id) REFERENCES boms(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id),
    INDEX idx_product (product_id),
    INDEX idx_bom (bom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: This table coexists with product_projects
-- If product_boms has entries for a product, use those specific BOMs
-- If product_boms is empty for a product, fall back to all BOMs from product_projects
