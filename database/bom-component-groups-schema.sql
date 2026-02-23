-- ============================================================================
-- BOM Component Groups Schema
-- ============================================================================
-- Run after bommer-schema.sql
-- This creates the centralized BOM component groups management table

USE bommer_auth;

-- ============================================================================
-- BOM_COMPONENT_GROUPS - Centralized group templates for BOMs
-- ============================================================================
CREATE TABLE IF NOT EXISTS bom_component_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'badge',
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_name (name),
    INDEX idx_is_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE BOM COMPONENT GROUPS
-- ============================================================================
INSERT INTO bom_component_groups (name, description, icon, display_order, is_active, created_by) VALUES
('Electronics - PCB Main', 'Main circuit board electronic components', 'microchip', 1, 1, 1),
('Mechanical - Housing', 'Mechanical parts and enclosures', 'wrench', 2, 1, 1),
('Power Components', 'Power supply and electrical components', 'bolt', 3, 1, 1),
('Connectors & Interface', 'Connectors, cables, and interface components', 'connect', 4, 1, 1),
('Passive Components', 'Resistors, capacitors, inductors', 'resistor', 5, 1, 1),
('Hardware & Fasteners', 'Screws, nuts, bolts, standoffs', 'tools', 6, 1, 1),
('Custom Components', 'Custom or miscellaneous components', 'blocks-group', 7, 1, 1)
ON DUPLICATE KEY UPDATE id=id;
