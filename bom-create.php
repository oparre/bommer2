<?php
/**
 * BOM Creation Page
 * Interactive BOM creation interface
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/includes/session.php';
initSecureSession();
requireLogin();

$pageTitle = 'Create New BOM';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - BOM Manager</title>
    
    <!-- Clarity Design System -->
    <link rel="stylesheet" href="public/node_modules/@clr/ui/clr-ui.min.css">
    <link rel="stylesheet" href="public/node_modules/@clr/icons/clr-icons.min.css">
    
    <!-- Consolidated Application Styles -->
    <link rel="stylesheet" href="public/css/app.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="public/css/bom-create.css?v=<?php echo time(); ?>">
</head>
<body>
    <div id="bom-create-app">
        <!-- Loading state -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
        
        <!-- Main content will be rendered by JavaScript -->
    </div>
    
    <!-- Clarity Icons -->
    <script src="public/node_modules/@webcomponents/webcomponentsjs/custom-elements-es5-adapter.js"></script>
    <script src="public/node_modules/@webcomponents/webcomponentsjs/webcomponents-bundle.js"></script>
    <script src="public/node_modules/@clr/icons/clr-icons.min.js"></script>
    
    <!-- API Service (loaded first, globally) -->
    <script src="public/js/api.js"></script>
    
    <!-- BOM Creation App -->
    <script type="module" src="public/js/bom-create.js?v=<?php echo time(); ?>"></script>
</body>
</html>
