<?php
/**
 * Login Page
 * 
 * Username and password authentication form using Clarity Design System
 */

// Define secure access
define('SECURE_ACCESS', true);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize secure session
initSecureSession();

// If already logged in, redirect to appropriate page
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: /app.php');
    } else {
        header('Location: /app.php');
    }
    exit;
}

// Check for remember me cookie and auto-login
try {
    $pdo = getDb();
    $user = validateRememberToken($pdo);
    
    if ($user) {
        // Auto-login via remember me token
        setUserSession($user);
        updateLastLogin($pdo, $user['id']);
        
        // Redirect to intended page or dashboard
        $redirect = $_SESSION['redirect_after_login'] ?? '/app.php';
        unset($_SESSION['redirect_after_login']);
        
        header('Location: ' . $redirect);
        exit;
    }
} catch (Exception $e) {
    error_log('Remember Me Auto-login Error: ' . $e->getMessage());
}

// Get flash message if any
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en" cds-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bommer Authentication</title>
    
    <!-- Clarity Design System CSS -->
    <link rel="stylesheet" href="/public/node_modules/@cds/core/global.min.css">
    <link rel="stylesheet" href="/public/node_modules/@cds/core/styles/theme.dark.min.css">
    <link rel="stylesheet" href="/public/node_modules/@clr/ui/clr-ui.min.css">
    
    <!-- Clarity Icons CSS -->
    <link rel="stylesheet" href="/public/node_modules/@clr/icons/clr-icons.min.css">
    
    <!-- Consolidated Application Styles -->
    <link rel="stylesheet" href="/public/css/app.css">
</head>
<body class="login-page" role="document">
    <div class="main-container">
        <!-- Header -->
        <header class="header header-6" role="banner">
            <div class="branding">
                <span class="title">Bommer</span>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content-container">
            <main class="content-area" role="main" aria-labelledby="page-title">
                <div class="login-card">
                    <h1 id="page-title" class="login-card-title">Sign In</h1>
                    <p class="login-card-subtitle">Please enter your credentials</p>
                    
                    <!-- Flash Message -->
                    <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                        <div class="alert-items">
                            <div class="alert-item static">
                                <div class="alert-icon-wrapper">
                                    <clr-icon class="alert-icon" shape="<?php echo $flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle'); ?>" aria-hidden="true"></clr-icon>
                                </div>
                                <span class="alert-text">
                                    <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form id="loginForm" action="/auth/validate-login.php" method="POST" class="clr-form clr-form-compact" aria-label="Login form">
                        <!-- CSRF Token -->
                        <?php echo csrfField(); ?>
                        
                        <!-- Username Field -->
                        <div class="clr-form-control">
                            <label for="username" class="clr-control-label required">Username</label>
                            <div class="clr-control-container">
                                <div class="clr-input-wrapper">
                                    <input 
                                        type="text" 
                                        id="username" 
                                        name="username" 
                                        class="clr-input" 
                                        placeholder="Enter your username"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        maxlength="50"
                                        aria-required="true"
                                        aria-describedby="username-help"
                                    >
                                    <clr-icon class="clr-validate-icon" shape="exclamation-circle" aria-hidden="true"></clr-icon>
                                </div>
                                <span id="username-help" class="clr-subtext"></span>
                            </div>
                        </div>
                        
                        <!-- Password Field -->
                        <div class="clr-form-control">
                            <label for="password" class="clr-control-label required">Password</label>
                            <div class="clr-control-container">
                                <div class="clr-input-wrapper">
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        class="clr-input" 
                                        placeholder="Enter your password"
                                        required
                                        autocomplete="current-password"
                                        aria-required="true"
                                        aria-describedby="password-help"
                                    >
                                    <clr-icon class="clr-validate-icon" shape="exclamation-circle" aria-hidden="true"></clr-icon>
                                </div>
                                <span id="password-help" class="clr-subtext"></span>
                            </div>
                        </div>
                        
                        <!-- Remember Me Checkbox -->
                        <div class="clr-form-control">
                            <div class="clr-checkbox-wrapper">
                                <input 
                                    type="checkbox" 
                                    id="remember_me" 
                                    name="remember_me" 
                                    class="clr-checkbox"
                                    value="1"
                                    aria-labelledby="remember_me_label"
                                >
                                <label for="remember_me" id="remember_me_label" class="clr-control-label">
                                    Remember me for 30 days
                                </label>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-block" aria-label="Sign in to your account">
                            <clr-icon shape="login" aria-hidden="true"></clr-icon>
                            Sign In
                        </button>
                    </form>
                    
                    <div class="login-card-footer">
                        <p class="login-footer-text">
                            <clr-icon shape="shield-check" aria-hidden="true"></clr-icon>
                            Secure login with CSRF protection
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Web Components Polyfill (Required for clr-icon) -->
    <script src="/public/node_modules/@webcomponents/webcomponentsjs/webcomponents-loader.js"></script>
    
    <!-- Clarity Icons (@clr/icons UMD bundle) -->
    <script src="/public/node_modules/@clr/icons/clr-icons.min.js"></script>
    
    <!-- Load icon shapes -->
    <script>
        // Icons should be automatically registered by clr-icons.min.js
        window.addEventListener('load', function() {
            console.log('Clarity Icons loaded:', typeof ClarityIcons !== 'undefined');
        });
    </script>
    
    <!-- Login Validation Script -->
    <script src="/public/js/login.js"></script>
</body>
</html>
