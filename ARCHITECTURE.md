# System Architecture Overview

## Authentication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                     BOMMER AUTHENTICATION SYSTEM                 │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐
│   Browser    │
└──────┬───────┘
       │
       │ GET /auth/login.php
       ▼
┌──────────────────────────────────────────────────────────────────┐
│  login.php                                                        │
│  ├─ Load session.php (initSecureSession)                         │
│  ├─ Check if already logged in → redirect                        │
│  ├─ Check remember_me cookie → auto-login                        │
│  └─ Display login form with CSRF token                           │
└──────────────────────────────────────────────────────────────────┘
       │
       │ POST username, password, remember_me, csrf_token
       ▼
┌──────────────────────────────────────────────────────────────────┐
│  validate-login.php                                               │
│  ├─ Validate CSRF token                                          │
│  ├─ Sanitize inputs                                              │
│  ├─ Check account lock status                                    │
│  │   └─ If locked → reject with message                          │
│  ├─ Query database for user                                      │
│  ├─ Verify password with password_verify()                       │
│  │   └─ If invalid → record failed attempt                       │
│  │       └─ If 5+ attempts → lock account for 15 min             │
│  ├─ Check if account is active                                   │
│  ├─ Reset failed login attempts                                  │
│  ├─ Update last_login timestamp                                  │
│  ├─ Set user session (session_regenerate_id)                     │
│  ├─ Create session fingerprint                                   │
│  ├─ If remember_me checked:                                      │
│  │   └─ Generate selector + validator                            │
│  │   └─ Hash validator and store in DB                           │
│  │   └─ Set cookie with selector:validator                       │
│  └─ Redirect to dashboard/admin panel                            │
└──────────────────────────────────────────────────────────────────┘
       │
       │ Redirect
       ▼
┌──────────────────────────────────────────────────────────────────┐
│  admin-users.php (if admin) OR index.html (if user)              │
│  ├─ requireAdmin() or requireLogin()                             │
│  ├─ Display user interface                                       │
│  └─ CRUD operations on users table                               │
└──────────────────────────────────────────────────────────────────┘
```

## Database Schema

```
┌─────────────────────────────────────────────────────────────┐
│  users                                                       │
├──────────────────────┬──────────────────────────────────────┤
│ id                   │ INT UNSIGNED AUTO_INCREMENT PK       │
│ username             │ VARCHAR(50) UNIQUE NOT NULL          │
│ password_hash        │ VARCHAR(255) NOT NULL                │
│ full_name            │ VARCHAR(100) NOT NULL                │
│ role                 │ ENUM('admin','user') DEFAULT 'user'  │
│ is_active            │ TINYINT(1) DEFAULT 1                 │
│ failed_login_attempts│ INT UNSIGNED DEFAULT 0               │
│ locked_until         │ DATETIME NULL                        │
│ created_at           │ DATETIME DEFAULT CURRENT_TIMESTAMP   │
│ updated_at           │ DATETIME DEFAULT CURRENT_TIMESTAMP   │
│ last_login           │ DATETIME NULL                        │
└──────────────────────┴──────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  remember_tokens                                             │
├──────────────────────┬──────────────────────────────────────┤
│ id                   │ INT UNSIGNED AUTO_INCREMENT PK       │
│ user_id              │ INT UNSIGNED FK → users(id)          │
│ selector             │ VARCHAR(64) UNIQUE NOT NULL          │
│ validator_hash       │ VARCHAR(255) NOT NULL                │
│ expires_at           │ DATETIME NOT NULL                    │
│ created_at           │ DATETIME DEFAULT CURRENT_TIMESTAMP   │
└──────────────────────┴──────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  csrf_tokens (optional)                                      │
├──────────────────────┬──────────────────────────────────────┤
│ id                   │ INT UNSIGNED AUTO_INCREMENT PK       │
│ token                │ VARCHAR(64) UNIQUE NOT NULL          │
│ expires_at           │ DATETIME NOT NULL                    │
│ created_at           │ DATETIME DEFAULT CURRENT_TIMESTAMP   │
└──────────────────────┴──────────────────────────────────────┘
```

## Security Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    SECURITY LAYERS                           │
└─────────────────────────────────────────────────────────────┘

Layer 1: Transport Security
├─ HTTPS (recommended)
├─ Secure cookie flag
└─ SameSite=Strict cookie attribute

Layer 2: Request Validation
├─ CSRF token validation
├─ Input sanitization
├─ Username/password format validation
└─ Method checking (POST only for sensitive operations)

Layer 3: Session Security
├─ session_regenerate_id(true) on login
├─ Periodic session regeneration (every 30 min)
├─ Session fingerprinting (User-Agent + IP prefix)
├─ HTTPOnly cookie flag
└─ Secure session storage

Layer 4: Authentication Security
├─ Password hashing with password_hash() (bcrypt)
├─ Constant-time password verification
├─ Automatic password rehashing if algorithm improves
└─ Generic error messages (prevent user enumeration)

Layer 5: Brute-Force Protection
├─ Failed attempt counter per username
├─ Account lockout after 5 failed attempts
├─ 15-minute lockout duration
├─ Lock status display with unlock time
└─ Failed attempt reset on successful login

Layer 6: Remember Me Security
├─ Secure selector/validator pattern
├─ Cryptographically random selector (32 bytes)
├─ Cryptographically random validator (64 bytes)
├─ Validator hashed before storage (like password)
├─ Token rotation on each validation
├─ 30-day expiration
└─ Automatic cleanup of expired tokens

Layer 7: Database Security
├─ PDO prepared statements (all queries)
├─ EMULATE_PREPARES = false
├─ Proper error handling (no info disclosure)
└─ Foreign key constraints
```

## File Dependency Map

```
login.php
├── config/database.php
│   └── PDO connection
├── includes/session.php
│   ├── initSecureSession()
│   ├── isLoggedIn()
│   └── validateRememberToken()
└── includes/functions.php
    ├── csrfField()
    └── getFlashMessage()

validate-login.php
├── config/database.php
├── includes/session.php
│   └── setUserSession()
└── includes/functions.php
    ├── validateCsrfToken()
    ├── sanitizeString()
    ├── validateUsername()
    ├── checkAccountLock()
    ├── recordFailedAttempt()
    ├── resetFailedAttempts()
    ├── updateLastLogin()
    ├── createRememberToken()
    └── redirectWithMessage()

admin-users.php
├── config/database.php
├── includes/session.php
│   ├── requireAdmin()
│   └── getCurrentUserId()
└── includes/functions.php
    ├── validateCsrfToken()
    ├── sanitizeString()
    ├── validateUsername()
    ├── validatePassword()
    ├── csrfField()
    └── getFlashMessage()

logout.php
├── config/database.php
├── includes/session.php
│   └── destroySession()
└── includes/functions.php
    ├── deleteRememberToken()
    └── redirectWithMessage()
```

## User Flows

### New User Login (First Time)
```
1. User visits login.php
2. Enters username + password
3. Optionally checks "Remember Me"
4. Submits form
5. validate-login.php processes:
   ├─ Validates credentials
   ├─ Creates session
   └─ Creates remember_me cookie (if checked)
6. Redirects to dashboard
```

### Returning User (With Remember Me)
```
1. User visits login.php
2. System detects remember_me cookie
3. Validates selector + validator
4. Auto-creates session
5. Rotates remember_me token
6. Auto-redirects to dashboard
```

### Admin Creating New User
```
1. Admin visits admin-users.php
2. Clicks "Create New User"
3. Fills form (username, full name, password, role)
4. Submits with CSRF token
5. System validates:
   ├─ Username format (3-50 alphanumeric + underscore)
   ├─ Password strength (8+ chars, mixed case, number, special)
   └─ Username uniqueness
6. Creates user with hashed password
7. Shows success message
```

### Account Lockout Scenario
```
1. User fails login 5 times
2. System locks account for 15 minutes
3. Sets locked_until = NOW() + 15 minutes
4. Shows error: "Account locked until HH:MM"
5. After 15 minutes:
   ├─ Lock automatically expires
   └─ Failed attempts reset to 0
6. User can login again
```

## API Endpoints

```
GET  /auth/login.php          → Display login form
POST /auth/validate-login.php → Process login
GET  /auth/logout.php          → Logout and cleanup

GET  /admin/admin-users.php    → User management (admin only)
POST /admin/admin-users.php    → CRUD operations
  ├─ action=create             → Create new user
  ├─ action=edit               → Edit user details
  ├─ action=reset_password     → Reset user password
  └─ action=toggle_status      → Enable/disable user
```

## Tech Stack

```
Frontend:
├─ Clarity Design System (CSS + Icons)
├─ Noto Sans SC (local fonts)
└─ Vanilla JavaScript (login validation)

Backend:
├─ PHP 7.4+ (with PDO, password_hash, random_bytes)
├─ MySQL 5.7+ (utf8mb4 charset)
└─ Apache (with mod_rewrite)

Security:
├─ CSRF tokens (session-based)
├─ Password hashing (bcrypt)
├─ PDO prepared statements
├─ Session fingerprinting
└─ Brute-force protection
```
