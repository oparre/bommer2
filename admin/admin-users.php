<?php
/**
 * Admin User Management
 * 
 * Admin-only page for user CRUD operations:
 * - Create new users
 * - Edit existing users
 * - Enable/disable users
 * - Assign roles
 * - Reset passwords
 */

// Define secure access
define('SECURE_ACCESS', true);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Initialize secure session
initSecureSession();

// Require admin access
requireAdmin('/auth/login.php');

$pdo = getDb();

// Handle form submissions
$action_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $action_message = ['type' => 'error', 'text' => 'Invalid security token.'];
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'create':
                    $username = sanitizeString($_POST['username'] ?? '');
                    $full_name = sanitizeString($_POST['full_name'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $role = $_POST['role'] ?? 'user';
                    
                    // Validate inputs
                    if (empty($username) || empty($full_name) || empty($password)) {
                        $action_message = ['type' => 'error', 'text' => 'All fields are required.'];
                        break;
                    }
                    
                    if (!validateUsername($username)) {
                        $action_message = ['type' => 'error', 'text' => 'Invalid username format. Use 3-50 alphanumeric characters or underscore.'];
                        break;
                    }
                    
                    $password_check = validatePassword($password);
                    if (!$password_check['valid']) {
                        $action_message = ['type' => 'error', 'text' => $password_check['message']];
                        break;
                    }
                    
                    if (!in_array($role, ['admin', 'user'])) {
                        $action_message = ['type' => 'error', 'text' => 'Invalid role.'];
                        break;
                    }
                    
                    // Check if username already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
                    $stmt->execute([':username' => $username]);
                    if ($stmt->fetch()) {
                        $action_message = ['type' => 'error', 'text' => 'Username already exists.'];
                        break;
                    }
                    
                    // Create user
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (username, password_hash, full_name, role, is_active) 
                         VALUES (:username, :password_hash, :full_name, :role, 1)"
                    );
                    
                    $stmt->execute([
                        ':username' => $username,
                        ':password_hash' => $password_hash,
                        ':full_name' => $full_name,
                        ':role' => $role
                    ]);
                    
                    $action_message = ['type' => 'success', 'text' => "User '{$username}' created successfully."];
                    break;
                    
                case 'edit':
                    $user_id = (int)($_POST['user_id'] ?? 0);
                    $full_name = sanitizeString($_POST['full_name'] ?? '');
                    $role = $_POST['role'] ?? 'user';
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if ($user_id <= 0 || empty($full_name)) {
                        $action_message = ['type' => 'error', 'text' => 'Invalid user data.'];
                        break;
                    }
                    
                    if (!in_array($role, ['admin', 'user'])) {
                        $action_message = ['type' => 'error', 'text' => 'Invalid role.'];
                        break;
                    }
                    
                    // Prevent disabling own account
                    if ($user_id == getCurrentUserId() && $is_active == 0) {
                        $action_message = ['type' => 'error', 'text' => 'You cannot disable your own account.'];
                        break;
                    }
                    
                    // Update user
                    $stmt = $pdo->prepare(
                        "UPDATE users 
                         SET full_name = :full_name, role = :role, is_active = :is_active 
                         WHERE id = :id"
                    );
                    
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':role' => $role,
                        ':is_active' => $is_active,
                        ':id' => $user_id
                    ]);
                    
                    $action_message = ['type' => 'success', 'text' => 'User updated successfully.'];
                    break;
                    
                case 'reset_password':
                    $user_id = (int)($_POST['user_id'] ?? 0);
                    $new_password = $_POST['new_password'] ?? '';
                    
                    if ($user_id <= 0 || empty($new_password)) {
                        $action_message = ['type' => 'error', 'text' => 'Invalid data.'];
                        break;
                    }
                    
                    $password_check = validatePassword($new_password);
                    if (!$password_check['valid']) {
                        $action_message = ['type' => 'error', 'text' => $password_check['message']];
                        break;
                    }
                    
                    // Reset password
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        "UPDATE users SET password_hash = :password_hash WHERE id = :id"
                    );
                    
                    $stmt->execute([
                        ':password_hash' => $password_hash,
                        ':id' => $user_id
                    ]);
                    
                    $action_message = ['type' => 'success', 'text' => 'Password reset successfully.'];
                    break;
                    
                case 'toggle_status':
                    $user_id = (int)($_POST['user_id'] ?? 0);
                    
                    if ($user_id <= 0) {
                        $action_message = ['type' => 'error', 'text' => 'Invalid user ID.'];
                        break;
                    }
                    
                    // Prevent disabling own account
                    if ($user_id == getCurrentUserId()) {
                        $action_message = ['type' => 'error', 'text' => 'You cannot disable your own account.'];
                        break;
                    }
                    
                    // Toggle active status
                    $stmt = $pdo->prepare(
                        "UPDATE users SET is_active = NOT is_active WHERE id = :id"
                    );
                    
                    $stmt->execute([':id' => $user_id]);
                    
                    $action_message = ['type' => 'success', 'text' => 'User status updated.'];
                    break;
            }
        } catch (Exception $e) {
            error_log('Admin User Management Error: ' . $e->getMessage());
            $action_message = ['type' => 'error', 'text' => 'An error occurred. Please try again.'];
        }
    }
}

// Fetch all users
try {
    $stmt = $pdo->prepare(
        "SELECT id, username, full_name, role, is_active, failed_login_attempts, 
                locked_until, last_login, created_at 
         FROM users 
         ORDER BY created_at DESC"
    );
    
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Fetch Users Error: ' . $e->getMessage());
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Bommer Admin</title>
    
    <!-- Clarity Design System CSS -->
    <link rel="stylesheet" href="/public/node_modules/@clr/ui/clr-ui.min.css">
    
    <!-- Clarity Icons CSS -->
    <link rel="stylesheet" href="/public/node_modules/@clr/icons/clr-icons.min.css">
    
    <!-- Consolidated Application Styles -->
    <link rel="stylesheet" href="/public/css/app.css">
</head>
<body class="admin-page" role="document">
    <div class="main-container">
        <!-- Header -->
        <header class="header header-6" role="banner">
            <div class="branding">
                <a href="/app.php" class="nav-link" aria-label="Bommer Admin Dashboard Home">
                    <span class="title">Bommer Admin</span>
                </a>
            </div>
            <nav class="header-nav" role="navigation" aria-label="Main navigation">
                <a href="/app.php" class="nav-link" aria-label="Go to Dashboard">
                    <span class="nav-text">Dashboard</span>
                </a>
            </nav>
            <div class="header-actions" role="group" aria-label="User actions">
                <span class="p6" aria-label="Current user: <?php echo htmlspecialchars(getCurrentUsername(), ENT_QUOTES, 'UTF-8'); ?>">
                    <clr-icon shape="user" aria-hidden="true"></clr-icon>
                    <?php echo htmlspecialchars(getCurrentUsername(), ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <a href="/auth/logout.php" class="btn btn-sm btn-link" aria-label="Logout from admin panel">
                    <clr-icon shape="logout" aria-hidden="true"></clr-icon>
                    Logout
                </a>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content-container">
            <main class="content-area" role="main" aria-labelledby="page-title">
                <h1 id="page-title">User Management</h1>
                
                <!-- Action Message -->
                <?php if ($action_message): ?>
                <div class="alert alert-<?php echo $action_message['type']; ?>" role="alert">
                    <div class="alert-items">
                        <div class="alert-item static">
                            <div class="alert-icon-wrapper">
                                <clr-icon class="alert-icon" shape="<?php echo $action_message['type'] === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></clr-icon>
                            </div>
                            <span class="alert-text"><?php echo htmlspecialchars($action_message['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Create User Button -->
                <button class="btn btn-primary" onclick="openCreateModal()" aria-label="Open dialog to create new user">
                    <clr-icon shape="plus" aria-hidden="true"></clr-icon>
                    Create New User
                </button>
                
                <!-- Users Table -->
                <table class="table table-compact" role="table" aria-label="User management table">
                    <thead>
                        <tr>
                            <th scope="col">Username</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Login</th>
                            <th scope="col">Failed Attempts</th>
                            <th scope="col" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'blue' : 'light-blue'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Disabled'; ?>
                                </span>
                                <?php if ($user['locked_until'] && strtotime($user['locked_until']) > time()): ?>
                                <span class="badge badge-warning">Locked</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td>
                                <?php if ($user['failed_login_attempts'] > 0): ?>
                                <span class="badge badge-warning"><?php echo $user['failed_login_attempts']; ?></span>
                                <?php else: ?>
                                0
                                <?php endif; ?>
                            </td>
                            <td class="text-center action-buttons">
                                <button class="btn btn-sm btn-primary" onclick='editUser(<?php echo json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' aria-label="Edit user <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <clr-icon shape="pencil" aria-hidden="true"></clr-icon>
                                    Edit
                                </button>
                                <button class="btn btn-sm btn-info" onclick="openResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')" aria-label="Reset password for user <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <clr-icon shape="key" aria-hidden="true"></clr-icon>
                                    Reset Password
                                </button>
                                <?php if ($user['id'] != getCurrentUserId()): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle user status?');" aria-label="Toggle status form for <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>" aria-label="<?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?> user <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <clr-icon shape="<?php echo $user['is_active'] ? 'ban' : 'check'; ?>" aria-hidden="true"></clr-icon>
                                        <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center" role="cell">No users found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div id="createModal" class="modal" role="dialog" aria-labelledby="createModalTitle" aria-modal="true" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" onclick="closeCreateModal()" aria-label="Close create user dialog">
                        <clr-icon shape="close" aria-hidden="true"></clr-icon>
                    </button>
                    <h3 class="modal-title" id="createModalTitle">Create New User</h3>
                </div>
                <div class="modal-body">
                    <form id="createForm" method="POST" class="clr-form clr-form-compact" aria-label="Create new user form">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create">
                        
                        <div class="clr-form-control">
                            <label for="create-username" class="clr-control-label required">Username</label>
                            <input type="text" id="create-username" name="username" class="clr-input" required maxlength="50" pattern="[a-zA-Z0-9_]{3,50}" aria-required="true" aria-describedby="username-help" autocomplete="username">
                            <span id="username-help" class="clr-subtext">3-50 alphanumeric characters or underscore</span>
                        </div>
                        
                        <div class="clr-form-control">
                            <label for="create-fullname" class="clr-control-label required">Full Name</label>
                            <input type="text" id="create-fullname" name="full_name" class="clr-input" required maxlength="100" aria-required="true">
                        </div>
                        
                        <div class="clr-form-control">
                            <label for="create-password" class="clr-control-label required">Password</label>
                            <input type="password" id="create-password" name="password" class="clr-input" required aria-required="true" aria-describedby="password-help" autocomplete="new-password">
                            <span id="password-help" class="clr-subtext">Min 8 chars, uppercase, lowercase, number, special char</span>
                        </div>
                        
                        <div class="clr-form-control">
                            <label for="create-role" class="clr-control-label required">Role</label>
                            <div class="clr-select-wrapper">
                                <select id="create-role" name="role" class="clr-select" required aria-required="true">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" form="createForm" class="btn btn-primary">Create User</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal" role="dialog" aria-labelledby="editModalTitle" aria-modal="true" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" onclick="closeEditModal()" aria-label="Close edit user dialog">
                        <clr-icon shape="close" aria-hidden="true"></clr-icon>
                    </button>
                    <h3 class="modal-title" id="editModalTitle">Edit User</h3>
                </div>
                <div class="modal-body">
                    <form id="editForm" method="POST" class="clr-form clr-form-compact" aria-label="Edit user form">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="clr-form-control">
                            <label for="edit_username" class="clr-control-label">Username</label>
                            <input type="text" id="edit_username" class="clr-input" disabled aria-disabled="true">
                        </div>
                        
                        <div class="clr-form-control">
                            <label for="edit_full_name" class="clr-control-label required">Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name" class="clr-input" required maxlength="100" aria-required="true">
                        </div>
                        
                        <div class="clr-form-control">
                            <label for="edit_role" class="clr-control-label required">Role</label>
                            <div class="clr-select-wrapper">
                                <select name="role" id="edit_role" class="clr-select" required aria-required="true">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="clr-form-control">
                            <div class="clr-checkbox-wrapper">
                                <input type="checkbox" name="is_active" id="edit_is_active" class="clr-checkbox" value="1" aria-labelledby="edit_is_active_label">
                                <label for="edit_is_active" id="edit_is_active_label" class="clr-control-label">Account Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" form="editForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal" role="dialog" aria-labelledby="resetPasswordModalTitle" aria-modal="true" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" onclick="closeResetPasswordModal()" aria-label="Close reset password dialog">
                        <clr-icon shape="close" aria-hidden="true"></clr-icon>
                    </button>
                    <h3 class="modal-title" id="resetPasswordModalTitle">Reset Password</h3>
                </div>
                <div class="modal-body">
                    <form id="resetPasswordForm" method="POST" class="clr-form clr-form-compact" aria-label="Reset password form">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <input type="text" id="reset_username_hidden" name="username_for_autocomplete" autocomplete="username" style="display:none;" aria-hidden="true" tabindex="-1">
                        
                        <p id="reset-user-info">Resetting password for: <strong id="reset_username"></strong></p>
                        
                        <div class="clr-form-control">
                            <label for="reset-new-password" class="clr-control-label required">New Password</label>
                            <input type="password" id="reset-new-password" name="new_password" class="clr-input" required aria-required="true" aria-describedby="reset-password-help" autocomplete="new-password">
                            <span id="reset-password-help" class="clr-subtext">Min 8 chars, uppercase, lowercase, number, special char</span>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-link" onclick="generatePassword()" aria-label="Generate random password">
                            <clr-icon shape="refresh" aria-hidden="true"></clr-icon>
                            Generate Random Password
                        </button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeResetPasswordModal()">Cancel</button>
                    <button type="submit" form="resetPasswordForm" class="btn btn-primary">Reset Password</button>
                </div>
            </div>
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
    
    <script>
        // Modal focus management for accessibility
        let lastFocusedElement = null;
        
        // Modal functions
        function openCreateModal() {
            lastFocusedElement = document.activeElement;
            const modal = document.getElementById('createModal');
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            // Focus first input field
            setTimeout(() => {
                const firstInput = modal.querySelector('input:not([type="hidden"])');
                if (firstInput) firstInput.focus();
            }, 100);
        }
        
        function closeCreateModal() {
            const modal = document.getElementById('createModal');
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.getElementById('createForm').reset();
            // Return focus to trigger element
            if (lastFocusedElement) lastFocusedElement.focus();
        }
        
        function editUser(user) {
            lastFocusedElement = document.activeElement;
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            const modal = document.getElementById('editModal');
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            // Focus first editable field
            setTimeout(() => {
                document.getElementById('edit_full_name').focus();
            }, 100);
        }
        
        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            // Return focus to trigger element
            if (lastFocusedElement) lastFocusedElement.focus();
        }
        
        function openResetPasswordModal(userId, username) {
            lastFocusedElement = document.activeElement;
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').textContent = username;
            const modal = document.getElementById('resetPasswordModal');
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            // Focus password field
            setTimeout(() => {
                document.getElementById('reset-new-password').focus();
            }, 100);
        }
        
        function closeResetPasswordModal() {
            const modal = document.getElementById('resetPasswordModal');
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.getElementById('resetPasswordForm').reset();
            // Return focus to trigger element
            if (lastFocusedElement) lastFocusedElement.focus();
        }
        
        function generatePassword() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            
            // Ensure at least one of each type
            password += String.fromCharCode(65 + Math.floor(Math.random() * 26)); // Uppercase
            password += String.fromCharCode(97 + Math.floor(Math.random() * 26)); // Lowercase
            password += String.fromCharCode(48 + Math.floor(Math.random() * 10)); // Number
            password += '!@#$%^&*'[Math.floor(Math.random() * 8)]; // Special
            
            // Fill to 12 characters
            for (let i = 4; i < 12; i++) {
                password += chars[Math.floor(Math.random() * chars.length)];
            }
            
            // Shuffle
            password = password.split('').sort(() => Math.random() - 0.5).join('');
            
            document.getElementById('reset-new-password').value = password;
            alert('Generated password (copy it): ' + password);
        }
        
        // Trap focus within modal when open (for keyboard accessibility)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.open');
                if (openModal) {
                    if (openModal.id === 'createModal') closeCreateModal();
                    else if (openModal.id === 'editModal') closeEditModal();
                    else if (openModal.id === 'resetPasswordModal') closeResetPasswordModal();
                }
            }
            
            // Tab trap
            if (e.key === 'Tab') {
                const openModal = document.querySelector('.modal.open');
                if (openModal) {
                    const focusableElements = openModal.querySelectorAll(
                        'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    );
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];
                    
                    if (e.shiftKey && document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    } else if (!e.shiftKey && document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });
        
        // Close modal on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    if (modal.id === 'createModal') closeCreateModal();
                    else if (modal.id === 'editModal') closeEditModal();
                    else if (modal.id === 'resetPasswordModal') closeResetPasswordModal();
                }
            });
        });
    </script>
</body>
</html>
