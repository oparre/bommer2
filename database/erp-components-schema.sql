-- ERP Components Schema
-- Run after bommer-schema.sql to add simulated ERP components table

USE bommer_auth;

CREATE TABLE IF NOT EXISTS erp_components (
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
    lead_time_days INT DEFAULT 0,
    status ENUM('active', 'obsolete', 'discontinued') NOT NULL DEFAULT 'active',
    last_sync_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erp_sync_status ENUM('synced', 'pending', 'error') NOT NULL DEFAULT 'synced',
    erp_id VARCHAR(100),
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_part_number (part_number),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_erp_id (erp_id),
    INDEX idx_sync_status (erp_sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
