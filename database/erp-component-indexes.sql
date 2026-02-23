-- ERP Component Performance Indexes
-- Run after bommer-schema.sql and erp-components-schema.sql
-- These indexes optimize component queries with source filtering and search

USE bommer_auth;

-- Index for status filtering (both tables)
CREATE INDEX IF NOT EXISTS idx_components_status ON components(status);
CREATE INDEX IF NOT EXISTS idx_erp_components_status ON erp_components(status);

-- Index for category filtering and sorting (both tables)
CREATE INDEX IF NOT EXISTS idx_components_category ON components(category);
CREATE INDEX IF NOT EXISTS idx_erp_components_category ON erp_components(category);

-- Composite index for search operations (part_number, name)
CREATE INDEX IF NOT EXISTS idx_components_search ON components(part_number, name);
CREATE INDEX IF NOT EXISTS idx_erp_components_search ON erp_components(part_number, name);

-- Index for full-text search on component names
CREATE FULLTEXT INDEX IF NOT EXISTS idx_components_name_fulltext ON components(name, description);
CREATE FULLTEXT INDEX IF NOT EXISTS idx_erp_components_name_fulltext ON erp_components(name, description);

-- Index for MPN searches (common in ERP scenarios)
CREATE INDEX IF NOT EXISTS idx_components_mpn ON components(mpn);
CREATE INDEX IF NOT EXISTS idx_erp_components_mpn ON erp_components(mpn);

-- Index for last_sync_at on ERP components (for sync monitoring)
CREATE INDEX IF NOT EXISTS idx_erp_components_last_sync ON erp_components(last_sync_at);

-- Composite index for active components by category (common query pattern)
CREATE INDEX IF NOT EXISTS idx_components_status_category ON components(status, category);
CREATE INDEX IF NOT EXISTS idx_erp_components_status_category ON erp_components(status, category);

-- Show created indexes
SHOW INDEX FROM components WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM erp_components WHERE Key_name LIKE 'idx_%';

-- Success message
SELECT 'ERP component performance indexes created successfully' AS Status;
