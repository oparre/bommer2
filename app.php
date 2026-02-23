<?php
/**
 * Bommer - Main Application
 * BOM Management System
 */

// Define secure access
define('SECURE_ACCESS', true);

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

// Initialize secure session
initSecureSession();

// Require login (not admin, just authenticated user)
requireLogin('/auth/login.php');
?>
<!DOCTYPE html>
<html lang="en" cds-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bommer - BOM Management System</title>

    <!-- Clarity Design System CSS (Local) -->
    <link rel="stylesheet" href="public/node_modules/@cds/core/global.min.css">
    <link rel="stylesheet" href="public/node_modules/@cds/core/styles/theme.dark.min.css">
    <link rel="stylesheet" href="public/node_modules/@clr/ui/clr-ui.min.css">
    <link rel="stylesheet" href="public/node_modules/@clr/icons/clr-icons.min.css">
    
    <!-- Consolidated Application Styles -->
    <link rel="stylesheet" href="public/css/app.css?v=2026010606">

</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="app-logo">
                <div class="logo-icon">B</div>
                <div>
                    <div class="app-title">Bommer</div>
                    <div class="app-subtitle">BOM Management System</div>
                </div>
            </div>

            <nav class="header-nav" role="navigation" aria-label="Main navigation">
                <a class="header-nav-item" onclick="navigateTo('dashboard')" data-route="dashboard" title="Dashboard" role="button" aria-label="Dashboard" tabindex="0">
                    <clr-icon shape="home" aria-hidden="true"></clr-icon>
                </a>
                <a class="header-nav-item" onclick="navigateTo('boms')" data-route="boms" title="BOMs" role="button" aria-label="Bill of Materials" tabindex="0">
                    <clr-icon shape="clipboard" aria-hidden="true"></clr-icon>
                </a>
                <a class="header-nav-item" onclick="navigateTo('projects')" data-route="projects" title="Projects" role="button" aria-label="Projects" tabindex="0">
                    <clr-icon shape="folder" aria-hidden="true"></clr-icon>
                </a>
                <a class="header-nav-item" onclick="navigateTo('optionals')" data-route="optionals" title="Optionals" role="button" aria-label="Project Optionals" tabindex="0">
                    <clr-icon shape="bundle" aria-hidden="true"></clr-icon>
                </a>
                <a class="header-nav-item" onclick="navigateTo('assemblies')" data-route="assemblies" title="Assemblies" role="button" aria-label="Assemblies" tabindex="0">
                    <clr-icon shape="wrench" aria-hidden="true"></clr-icon>
                </a>
                <a class="header-nav-item" onclick="navigateTo('components')" data-route="components" title="Components" role="button" aria-label="Components" tabindex="0">
                    <clr-icon shape="cog" aria-hidden="true"></clr-icon>
                </a>
                
                <div class="header-nav-divider" role="separator" aria-hidden="true"></div>
                
                <a class="header-nav-item" onclick="navigateTo('users')" data-route="users" title="Users" role="button" aria-label="User Management" tabindex="0">
                    <clr-icon shape="users" aria-hidden="true"></clr-icon>
                </a>
                <a class="header-nav-item" onclick="navigateTo('audit')" data-route="audit" title="Audit Log" role="button" aria-label="Audit Log" tabindex="0">
                    <clr-icon shape="file" aria-hidden="true"></clr-icon>
                </a>
            </nav>

            <div class="header-search" role="search">
                <input type="text" class="search-input" placeholder="Search BOMs, Projects, Assemblies, Components, Optionals..." id="globalSearch" aria-label="Search all entities">
            </div>

            <div class="header-actions" role="group" aria-label="User actions">
                <div aria-label="Current user: <?php echo htmlspecialchars(getCurrentUsername(), ENT_QUOTES, 'UTF-8'); ?>">
                    <clr-icon shape="user" aria-hidden="true"></clr-icon>
                    <?php echo htmlspecialchars(getCurrentUsername(), ENT_QUOTES, 'UTF-8'); ?>
</div>
                <a href="/auth/logout.php" class="btn btn-sm btn-link" aria-label="Logout from application">
                    <clr-icon shape="logout" aria-hidden="true"></clr-icon>
                    Logout
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="app-main">
            <!-- Content -->
            <main class="app-content" id="appContent" role="main" aria-label="Main content area">
                <!-- Content will be dynamically loaded here -->
            </main>
        </div>
    </div>

    <script src="public/node_modules/@webcomponents/webcomponentsjs/custom-elements-es5-adapter.js"></script>
    <script src="public/node_modules/@webcomponents/webcomponentsjs/webcomponents-bundle.js"></script>
    <script src="public/node_modules/@clr/icons/clr-icons.min.js"></script>
    
    <script src="public/js/api.js"></script>
    <script src="app-router.js"></script>
</body>
</html>
