-- ============================================================================
-- Rename Assembly entity to Product
-- Migration: rename-assembly-to-product.sql
-- ============================================================================
-- Note: MySQL automatically renames foreign key constraints when tables are
-- renamed, so we don't need to manually DROP/ADD foreign keys.
-- ============================================================================

START TRANSACTION;

-- 1. Rename tables (MySQL auto-updates FK constraint names during RENAME)
RENAME TABLE assemblies TO products;
RENAME TABLE assembly_projects TO product_projects;
RENAME TABLE assembly_boms TO product_boms;

-- 2. Rename columns (assembly_id -> product_id)
ALTER TABLE product_projects CHANGE assembly_id product_id INT UNSIGNED NOT NULL;
ALTER TABLE product_boms CHANGE assembly_id product_id INT UNSIGNED NOT NULL;

-- 3. Update indexes - drop old, create new
-- For product_projects:
ALTER TABLE product_projects DROP INDEX idx_assembly;
ALTER TABLE product_projects ADD INDEX idx_product (product_id);

-- For product_boms:
ALTER TABLE product_boms DROP INDEX idx_assembly;
ALTER TABLE product_boms ADD INDEX idx_product (product_id);

-- 4. Update audit_logs entity_type ENUM to replace 'assembly' with 'product'
ALTER TABLE audit_logs MODIFY COLUMN entity_type ENUM('bom', 'project', 'product', 'component', 'user') NOT NULL;

-- 5. Update existing audit log data
UPDATE audit_logs SET entity_type = 'product' WHERE entity_type = 'assembly';
UPDATE audit_logs SET action = REPLACE(action, 'assembly', 'product') WHERE action LIKE '%assembly%';

COMMIT;
