-- BOM Items Source Tracking
-- Run after bommer-schema.sql to add component_source column to bom_items

USE bommer_auth;

ALTER TABLE bom_items 
    ADD COLUMN component_source ENUM('bommer', 'erp') NOT NULL DEFAULT 'bommer' AFTER component_id,
    ADD INDEX idx_source (component_source);
