-- ERP Components Sample Data
-- Run after erp-components-schema.sql to populate simulated ERP components

USE bommer_auth;

INSERT INTO erp_components (part_number, name, description, category, manufacturer, mpn, supplier, unit_cost, stock_level, lead_time_days, status, erp_id, erp_sync_status, last_sync_at)
VALUES
    ('ERP-RES-500', '22K Resistor', '22K Ohm 1/4W 1%', 'Passive', 'Vishay', 'CRCW080522K0FKEA', 'Arrow', 0.0180, 800, 5, 'active', 'ERP-PART-00451', 'synced', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
    ('ERP-CAP-200', '10uF Capacitor', '10uF 25V Tantalum', 'Passive', 'Kemet', 'T491A106K025AT', 'Mouser', 0.3500, 250, 10, 'active', 'ERP-PART-00892', 'synced', DATE_SUB(NOW(), INTERVAL 1 DAY)),
    ('ERP-IC-300', 'Op-Amp Dual', 'Dual op-amp, rail-to-rail', 'IC', 'Texas Instruments', 'OPA2340UA', 'Digikey', 2.4500, 120, 14, 'active', 'ERP-PART-01234', 'pending', DATE_SUB(NOW(), INTERVAL 5 DAY)),
    ('ERP-CONN-100', 'RJ45 Connector', 'Ethernet RJ45 MagJack', 'Connector', 'Bel Fuse', '0826-1X1T-36-F', 'Mouser', 1.7500, 300, 12, 'active', 'ERP-PART-02001', 'synced', DATE_SUB(NOW(), INTERVAL 3 DAY)),
    ('ERP-LED-010', 'Green Status LED', 'Green LED 0805', 'LED', 'Kingbright', 'APT2012SGC', 'Arrow', 0.0600, 1000, 7, 'active', 'ERP-PART-03010', 'synced', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
    ('ERP-RES-510', '4.7K Resistor', '4.7K Ohm 1/4W 1%', 'Passive', 'Yageo', 'RC0805FR-074K7L', 'Farnell', 0.0120, 1500, 5, 'active', 'ERP-PART-00452', 'synced', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
    ('ERP-CAP-220', '1uF Capacitor', '1uF 50V MLCC', 'Passive', 'Murata', 'GRM21BR71H105KA12L', 'Digikey', 0.0800, 900, 9, 'active', 'ERP-PART-00893', 'synced', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    ('ERP-IC-320', 'Logic Level Translator', '4-bit bidirectional level shifter', 'IC', 'NXP', 'TXB0104PW', 'Mouser', 0.6500, 400, 10, 'active', 'ERP-PART-01235', 'synced', DATE_SUB(NOW(), INTERVAL 7 DAY)),
    ('ERP-MECH-050', 'M3 Standoff', 'M3 x 10mm hex standoff, nylon', 'Mechanical', 'Generic', 'STANDOFF-M3-10-NYL', 'Local', 0.0500, 500, 3, 'active', 'ERP-PART-04001', 'error', DATE_SUB(NOW(), INTERVAL 15 DAY)),
    ('ERP-PSU-001', 'DC-DC Converter', '5V to 3.3V DC-DC module', 'Power', 'Recom', 'R-78E3.3-0.5', 'Digikey', 2.1500, 80, 21, 'active', 'ERP-PART-05001', 'synced', DATE_SUB(NOW(), INTERVAL 8 DAY));
