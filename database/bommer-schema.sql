-- ============================================================================
-- Bommer BOM Management System - Complete Database Schema
-- ============================================================================
-- Run after authentication schema (schema.sql)
-- This creates the full BOM management system tables

USE bommer_auth;

-- ============================================================================
-- PROJECTS
-- ============================================================================
CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    status ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled') NOT NULL DEFAULT 'planning',
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    is_optional TINYINT(1) NOT NULL DEFAULT 0,
    optional_category VARCHAR(100),
    owner_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_owner (owner_id),
    INDEX idx_is_optional (is_optional),
    INDEX idx_optional_category (optional_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ASSEMBLIES
-- ============================================================================
CREATE TABLE IF NOT EXISTS assemblies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_code (code),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ASSEMBLY_PROJECTS (Many-to-Many)
-- ============================================================================
CREATE TABLE IF NOT EXISTS assembly_projects (
    assembly_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    added_by INT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assembly_id, project_id),
    FOREIGN KEY (assembly_id) REFERENCES assemblies(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id),
    INDEX idx_assembly (assembly_id),
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PROJECT_OPTIONALS (Project Optional Links)
-- ============================================================================
CREATE TABLE IF NOT EXISTS project_optionals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    base_project_id INT UNSIGNED NOT NULL,
    optional_project_id INT UNSIGNED NOT NULL,
    display_name VARCHAR(200),
    description TEXT,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (base_project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (optional_project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY unique_project_optional (base_project_id, optional_project_id),
    INDEX idx_base_project (base_project_id),
    INDEX idx_optional_project (optional_project_id),
    INDEX idx_is_default (is_default),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COMPONENTS
-- ============================================================================
CREATE TABLE IF NOT EXISTS components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    manufacturer VARCHAR(100),
    mpn VARCHAR(100),
    supplier VARCHAR(100),
    unit_cost DECIMAL(10, 4) DEFAULT 0.0000,
    stock_level INT DEFAULT 0,
    min_stock INT DEFAULT 0,
    lead_time_days INT DEFAULT 0,
    status ENUM('active', 'obsolete', 'banned') NOT NULL DEFAULT 'active',
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_part_number (part_number),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_manufacturer (manufacturer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BOMS
-- ============================================================================
CREATE TABLE IF NOT EXISTS boms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    variant_group VARCHAR(100) NULL,
    description TEXT,
    current_revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_sku (sku),
    INDEX idx_project (project_id),
    INDEX idx_variant_group (variant_group),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BOM_REVISIONS
-- ============================================================================
CREATE TABLE IF NOT EXISTS bom_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bom_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    status ENUM('draft', 'approved', 'obsolete', 'invalidated') NOT NULL DEFAULT 'draft',
    notes TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_bom_revision (bom_id, revision_number),
    FOREIGN KEY (bom_id) REFERENCES boms(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_bom_id (bom_id),
    INDEX idx_status (status),
    INDEX idx_revision (revision_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BOM_GROUPS
-- ============================================================================
CREATE TABLE IF NOT EXISTS bom_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revision_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (revision_id) REFERENCES bom_revisions(id) ON DELETE CASCADE,
    INDEX idx_revision (revision_id),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BOM_ITEMS
-- ============================================================================
CREATE TABLE IF NOT EXISTS bom_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(10, 4) NOT NULL DEFAULT 1.0000,
    reference_designator VARCHAR(200),
    notes TEXT,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES bom_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES components(id),
    INDEX idx_group (group_id),
    INDEX idx_component (component_id),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AUDIT_LOGS
-- ============================================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type ENUM('bom', 'project', 'assembly', 'component', 'user') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA FOR TESTING
-- ============================================================================

-- Insert sample projects
INSERT INTO projects (code, name, description, status, priority, owner_id, created_by) VALUES
('PRJ-001', 'Alpha Product Line', 'Main product line alpha series', 'active', 'high', 1, 1),
('PRJ-002', 'Beta Prototype', 'Beta testing prototype', 'active', 'medium', 1, 1),
('PRJ-003', 'Gamma Enhancement', 'Enhanced gamma features', 'planning', 'low', 1, 1)
ON DUPLICATE KEY UPDATE id=id;

-- Insert sample assemblies
INSERT INTO assemblies (code, name, description, category, created_by) VALUES
('ASM-001', 'Main Board Assembly', 'Primary circuit board assembly', 'Electronics', 1),
('ASM-002', 'Power Supply Assembly', 'Power delivery system', 'Power', 1),
('ASM-003', 'Enclosure Assembly', 'Mechanical housing', 'Mechanical', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Link projects to assemblies
INSERT INTO assembly_projects (assembly_id, project_id, added_by) VALUES
(1, 1, 1),
(1, 2, 1),
(2, 1, 1)
ON DUPLICATE KEY UPDATE assembly_id=assembly_id;

-- Insert sample components
INSERT INTO components (part_number, name, description, category, manufacturer, mpn, supplier, unit_cost, stock_level, min_stock, lead_time_days, status, created_by) VALUES
('RES-001', '10K Resistor', '10K Ohm 1/4W 1%', 'Passive', 'Yageo', 'RC0805FR-0710KL', 'Digikey', 0.0150, 1000, 100, 7, 'active', 1),
('CAP-001', '100nF Capacitor', '100nF 50V Ceramic', 'Passive', 'Murata', 'GRM21BR71H104KA01L', 'Digikey', 0.0250, 500, 50, 7, 'active', 1),
('IC-001', 'Microcontroller', 'ARM Cortex M4', 'IC', 'STM', 'STM32F407VGT6', 'Mouser', 8.5000, 50, 10, 14, 'active', 1),
('CONN-001', 'USB-C Connector', 'USB Type-C Receptacle', 'Connector', 'Amphenol', '12401610E4#2A', 'Digikey', 1.2500, 200, 25, 10, 'active', 1),
('LED-001', 'Status LED', 'Red LED 0805', 'LED', 'Kingbright', 'APT2012EC', 'Digikey', 0.0800, 300, 50, 7, 'active', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Insert sample BOMs
INSERT INTO boms (sku, name, project_id, variant_group, description, current_revision, created_by) VALUES
('BOM-ALPHA-001', 'Alpha V1 Main Board', 1, NULL, 'First version of alpha main board', 1, 1),
('BOM-ALPHA-002', 'Alpha V1 Power Supply', 1, NULL, 'Power supply for alpha V1', 1, 1),
('BOM-BETA-001', 'Beta Prototype Board', 2, NULL, 'Beta testing board', 1, 1)
ON DUPLICATE KEY UPDATE id=id;

-- Insert BOM revisions
INSERT INTO bom_revisions (bom_id, revision_number, status, notes, created_by) VALUES
(1, 1, 'approved', 'Initial approved revision', 1),
(2, 1, 'approved', 'Initial approved revision', 1),
(3, 1, 'draft', 'Work in progress', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Insert BOM groups
INSERT INTO bom_groups (revision_id, name, display_order) VALUES
(1, 'Passive Components', 1),
(1, 'Active Components', 2),
(1, 'Connectors', 3),
(2, 'Power Components', 1),
(3, 'Electronics', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Insert BOM items
INSERT INTO bom_items (group_id, component_id, quantity, reference_designator, display_order) VALUES
(1, 1, 10.0000, 'R1-R10', 1),
(1, 2, 5.0000, 'C1-C5', 2),
(2, 3, 1.0000, 'U1', 1),
(2, 5, 2.0000, 'D1,D2', 2),
(3, 4, 1.0000, 'J1', 1),
(4, 1, 2.0000, 'R1-R2', 1),
(4, 2, 3.0000, 'C1-C3', 2),
(5, 1, 5.0000, 'R1-R5', 1),
(5, 3, 1.0000, 'U1', 2)
ON DUPLICATE KEY UPDATE id=id;

-- Clean up expired tokens on startup
DELETE FROM remember_tokens WHERE expires_at < NOW();
DELETE FROM csrf_tokens WHERE expires_at < NOW();
