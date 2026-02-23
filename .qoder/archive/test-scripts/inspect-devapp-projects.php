<?php
/**
 * Diagnostic Script: Inspect devapp projects table
 * This helps us understand the schema to plan integration
 */

require_once __DIR__ . '/config/database.php';

try {
    // Connect to devapp database
    $devappPdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=project_management;charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<h1>devapp Database Inspection</h1>";
    echo "<hr>";
    
    // 1. Get table structure
    echo "<h2>1. Projects Table Structure</h2>";
    $stmt = $devappPdo->query("DESCRIBE projects");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Count total projects
    echo "<h2>2. Total Projects</h2>";
    $stmt = $devappPdo->query("SELECT COUNT(*) as total FROM projects");
    $count = $stmt->fetch();
    echo "<p><strong>Total projects in devapp:</strong> {$count['total']}</p>";
    
    // 3. Sample data (first 5 rows)
    echo "<h2>3. Sample Data (First 5 Projects)</h2>";
    $stmt = $devappPdo->query("SELECT * FROM projects LIMIT 5");
    $samples = $stmt->fetchAll();
    
    if (!empty($samples)) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr>";
        foreach (array_keys($samples[0]) as $header) {
            echo "<th>{$header}</th>";
        }
        echo "</tr>";
        
        foreach ($samples as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No projects found in devapp.</p>";
    }
    
    // 4. Get unique values for enum/categorical fields
    echo "<h2>4. Data Distribution</h2>";
    
    // Check if status column exists
    $hasStatus = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'status') {
            $hasStatus = true;
            break;
        }
    }
    
    if ($hasStatus) {
        echo "<h3>Status Distribution:</h3>";
        $stmt = $devappPdo->query("SELECT status, COUNT(*) as count FROM projects GROUP BY status ORDER BY count DESC");
        $statuses = $stmt->fetchAll();
        echo "<ul>";
        foreach ($statuses as $status) {
            echo "<li>{$status['status']}: {$status['count']}</li>";
        }
        echo "</ul>";
    }
    
    // 5. Field mapping comparison
    echo "<h2>5. Field Mapping to Bommer</h2>";
    echo "<p>Bommer projects table expects these fields:</p>";
    echo "<ul>";
    echo "<li><strong>Required:</strong> code (VARCHAR 50), name (VARCHAR 200)</li>";
    echo "<li><strong>Optional:</strong> description (TEXT), status (ENUM), priority (ENUM), is_optional (TINYINT), optional_category (VARCHAR 100), owner_id (INT), created_by (INT)</li>";
    echo "</ul>";
    
    echo "<h3>Detected Fields in devapp:</h3>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li><strong>{$col['Field']}</strong> ({$col['Type']})</li>";
    }
    echo "</ul>";
    
    // 6. Check for potential conflicts
    echo "<h2>6. Potential Integration Issues</h2>";
    
    // Check for code uniqueness
    $bommerPdo = getDb();
    $stmt = $bommerPdo->query("SELECT code FROM projects");
    $bommerCodes = array_column($stmt->fetchAll(), 'code');
    
    if (!empty($bommerCodes)) {
        // Check if devapp has 'code' field
        $hasCode = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'code') {
                $hasCode = true;
                break;
            }
        }
        
        if ($hasCode) {
            $placeholders = implode(',', array_fill(0, count($bommerCodes), '?'));
            $stmt = $devappPdo->prepare("SELECT COUNT(*) as conflicts FROM projects WHERE code IN ($placeholders)");
            $stmt->execute($bommerCodes);
            $conflicts = $stmt->fetch();
            
            if ($conflicts['conflicts'] > 0) {
                echo "<p style='color: orange;'><strong>Warning:</strong> {$conflicts['conflicts']} project codes in devapp already exist in bommer. These would need to be handled during import.</p>";
            } else {
                echo "<p style='color: green;'>✓ No code conflicts detected.</p>";
            }
        } else {
            echo "<p style='color: red;'><strong>Issue:</strong> devapp projects table does not have a 'code' field. We'll need to generate codes or use another field.</p>";
        }
    }
    
    echo "<hr>";
    echo "<p><em>Inspection complete. Use this information to plan the integration strategy.</em></p>";
    
} catch (PDOException $e) {
    echo "<h1 style='color: red;'>Database Connection Error</h1>";
    echo "<p>Could not connect to devapp database 'project_management'.</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please verify:</p>";
    echo "<ul>";
    echo "<li>Database 'project_management' exists</li>";
    echo "<li>User '" . DB_USER . "' has access to the database</li>";
    echo "<li>Table 'projects' exists in the database</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<h1 style='color: red;'>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
