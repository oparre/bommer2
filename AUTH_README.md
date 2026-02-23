# Bommer Authentication System

Production-ready username/password authentication system for Apache + PHP + MySQL + JavaScript stack.

## Features

✅ **Security First**
- CSRF protection on all forms
- Password hashing with `password_hash()` and `password_verify()`
- PDO prepared statements for SQL injection prevention
- Secure session management with `session_regenerate_id(true)`
- Session fingerprinting to prevent hijacking
- Brute-force protection with account lockout (5 attempts = 15 minute lock)
- Secure "Remember Me" using selector/validator pattern with hashed tokens

✅ **User Management**
- Admin-only user creation, editing, disabling, and role assignment
- Password strength validation (min 8 chars, uppercase, lowercase, number, special char)
- Username validation (3-50 alphanumeric + underscore)
- Role-based access control (admin/user)
- Account enable/disable functionality

✅ **Design**
- Clarity Design System UI components
- Noto Sans SC font (loaded from local assets)
- Dark theme optimized
- Responsive design
- Client-side validation with real-time feedback

## File Structure

```
bommer/
├── admin/
│   └── admin-users.php          # Admin user management page (CRUD operations)
├── auth/
│   ├── login.php                # Login page with Clarity Design form
│   ├── validate-login.php       # Login validation with brute-force protection
│   └── logout.php               # Logout with session/token cleanup
├── config/
│   └── database.php             # PDO database connection configuration
├── database/
│   └── schema.sql               # Database schema (users, remember_tokens, csrf_tokens)
├── includes/
│   ├── functions.php            # Security functions (CSRF, validation, remember-me)
│   └── session.php              # Secure session management
├── public/
│   ├── css/
│   │   └── auth.css             # Custom authentication styles
│   ├── js/
│   │   └── login.js             # Client-side login validation
│   ├── fonts/
│   │   └── noto-sans-sc/        # Local Noto Sans SC font files
│   └── node_modules/
│       ├── @clr/ui/             # Clarity UI CSS
│       └── @clr/icons/          # Clarity Icons JS
├── comparison.html
├── index.html
└── matrix.html
```

## Installation

### 1. Database Setup

Create a MySQL database and run the schema:

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE bommer_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p bommer_auth < database/schema.sql
```

This creates:
- **users table**: User accounts with authentication data
- **remember_tokens table**: Secure "Remember Me" tokens
- **csrf_tokens table**: CSRF token storage (optional)
- **Default admin account**: username `admin`, password `Admin@123` (⚠️ CHANGE IMMEDIATELY)

### 2. Configure Database Connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bommer_auth');  // Your database name
define('DB_USER', 'root');          // Your database user
define('DB_PASS', '');              // Your database password
```

### 3. Configure PHP Session Settings

Ensure your `php.ini` has secure session settings:

```ini
session.cookie_httponly = 1
session.use_strict_mode = 1
session.use_only_cookies = 1
session.cookie_samesite = Strict
session.cookie_secure = 1  ; Enable if using HTTPS
```

### 4. Set Proper File Permissions

```bash
# Make config directory readable only by web server
chmod 750 config/
chmod 640 config/database.php

# Ensure includes are not directly accessible
chmod 640 includes/*.php
```

### 5. Configure Apache

Add `.htaccess` to protect sensitive directories:

**`/config/.htaccess`:**
```apache
Deny from all
```

**`/includes/.htaccess`:**
```apache
Deny from all
```

**`/database/.htaccess`:**
```apache
Deny from all
```

### 6. Install Clarity Design System

If not already installed:

```bash
cd public
npm install @clr/ui @clr/icons
```

### 7. Font Assets

Ensure Noto Sans SC fonts are in `/public/fonts/noto-sans-sc/`:
- NotoSansSC-Regular.otf
- NotoSansSC-Medium.otf
- NotoSansSC-Bold.otf

## Usage

### Login

Navigate to: `http://yourdomain.com/auth/login.php`

**Default credentials:**
- Username: `admin`
- Password: `Admin@123`

⚠️ **IMPORTANT:** Change the default admin password immediately after first login!

### Admin User Management

After logging in as admin, access: `http://yourdomain.com/admin/admin-users.php`

**Available operations:**
- Create new users with username, password, full name, and role
- Edit user details (full name, role, active status)
- Reset user passwords
- Enable/disable user accounts
- View login history and failed attempts

### Logout

Click "Logout" in the header or navigate to: `http://yourdomain.com/auth/logout.php`

## Security Features Explained

### 1. CSRF Protection

Every form includes a CSRF token validated server-side:

```php
// Generate token
<?php echo csrfField(); ?>

// Validate token
if (!validateCsrfToken($_POST['csrf_token'])) {
    // Reject request
}
```

### 2. Brute-Force Protection

- Failed login attempts are tracked per username
- After 5 failed attempts, account is locked for 15 minutes
- Lock status displayed with unlock time

### 3. Remember Me (Secure Selector/Validator Pattern)

- Generates cryptographically random selector and validator
- Stores hashed validator in database (like password hashing)
- Cookie contains `selector:validator` (unhashed)
- On validation, selector finds record, validator is verified against hash
- Token rotation on each use for enhanced security

### 4. Session Security

- Session ID regeneration on login and periodically
- Session fingerprinting (user agent + IP prefix)
- HTTPOnly and Secure cookies (when HTTPS)
- SameSite=Strict to prevent CSRF

### 5. Password Security

- Minimum requirements enforced (8+ chars, uppercase, lowercase, number, special char)
- bcrypt hashing via `password_hash()` with automatic salt
- Automatic rehashing if algorithm improves

## API Reference

### Session Functions (`includes/session.php`)

```php
initSecureSession()           // Initialize secure session
isLoggedIn()                  // Check if user is logged in
isAdmin()                     // Check if user is admin
requireLogin($redirect_url)   // Require login (redirect if not)
requireAdmin($redirect_url)   // Require admin access
setUserSession($user)         // Set user session after login
destroySession()              // Destroy session completely
getCurrentUserId()            // Get current user ID
getCurrentUsername()          // Get current username
```

### Security Functions (`includes/functions.php`)

```php
// CSRF Protection
generateCsrfToken()           // Generate CSRF token
validateCsrfToken($token)     // Validate CSRF token
csrfField()                   // Generate CSRF hidden input

// Validation
sanitizeString($input)        // Sanitize string input
validateUsername($username)   // Validate username format
validatePassword($password)   // Validate password strength

// Remember Me
createRememberToken($pdo, $user_id)
validateRememberToken($pdo)
deleteRememberToken($pdo, $selector)
deleteAllUserTokens($pdo, $user_id)
cleanupExpiredTokens($pdo)

// Brute Force Protection
checkAccountLock($pdo, $username)
recordFailedAttempt($pdo, $username)
resetFailedAttempts($pdo, $username)

// Utilities
redirectWithMessage($url, $message, $type)
getFlashMessage()
generateRandomPassword($length)
updateLastLogin($pdo, $user_id)
```

## Customization

### Modify Lock Duration

Edit `includes/functions.php`, function `recordFailedAttempt()`:

```php
// Change from 15 minutes to 30 minutes
$lock_until = date('Y-m-d H:i:s', time() + (30 * 60));
```

### Modify Failed Attempt Threshold

Edit `includes/functions.php`, function `recordFailedAttempt()`:

```php
// Change from 5 attempts to 3 attempts
if ($user && $user['failed_login_attempts'] >= 3) {
```

### Modify Remember Me Duration

Edit `includes/functions.php`, function `createRememberToken()`:

```php
// Change from 30 days to 90 days
$expires_at = date('Y-m-d H:i:s', time() + (90 * 24 * 60 * 60));
```

### Modify Password Requirements

Edit `includes/functions.php`, function `validatePassword()` to adjust:
- Minimum length
- Character requirements
- Add custom rules

## Maintenance

### Clean Up Expired Tokens

Run periodically via cron job:

```bash
# Add to crontab (runs daily at 3 AM)
0 3 * * * mysql -u root -p'password' bommer_auth -e "DELETE FROM remember_tokens WHERE expires_at < NOW(); DELETE FROM csrf_tokens WHERE expires_at < NOW();"
```

Or use PHP script:

```php
<?php
require_once 'config/database.php';
$pdo = getDb();
$pdo->exec("DELETE FROM remember_tokens WHERE expires_at < NOW()");
$pdo->exec("DELETE FROM csrf_tokens WHERE expires_at < NOW()");
```

### Monitor Failed Login Attempts

```sql
SELECT username, failed_login_attempts, locked_until, last_login
FROM users
WHERE failed_login_attempts > 0
ORDER BY failed_login_attempts DESC;
```

### View Active Remember Me Tokens

```sql
SELECT u.username, rt.created_at, rt.expires_at
FROM remember_tokens rt
JOIN users u ON rt.user_id = u.id
WHERE rt.expires_at > NOW()
ORDER BY rt.created_at DESC;
```

## Troubleshooting

### Login fails with "Database connection failed"

Check `config/database.php` credentials and ensure MySQL is running.

### "Invalid security token" error

- Clear browser cookies
- Ensure session is started properly
- Check session storage permissions

### Remember Me not working

- Check cookie settings (HTTPOnly, Secure flags)
- Ensure `remember_tokens` table exists
- Verify token expiration dates

### Account locked unexpectedly

Reset manually in database:

```sql
UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE username = 'username';
```

### Fonts not loading

- Verify font files exist in `/public/fonts/noto-sans-sc/`
- Check browser console for 404 errors
- Ensure correct file paths in `auth.css`

## Production Checklist

- [ ] Change default admin password
- [ ] Update database credentials in `config/database.php`
- [ ] Enable HTTPS and set `session.cookie_secure = 1`
- [ ] Protect `/config`, `/includes`, `/database` with `.htaccess`
- [ ] Set up automated token cleanup cron job
- [ ] Configure PHP error logging (don't display errors to users)
- [ ] Review and adjust lockout thresholds
- [ ] Test all functionality thoroughly
- [ ] Set up database backups
- [ ] Monitor failed login attempts

## License

This authentication system is provided as-is for production use in the Bommer project.

## Support

For issues or questions, contact the development team.
