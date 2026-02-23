# Quick Start Guide - Bommer Authentication System

## 🚀 Get Started in 5 Minutes

### Prerequisites
- WAMP Server installed and running
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Clarity UI installed in `/public/node_modules/`

### Step 1: Run Database Setup

Double-click `setup-database.bat` or run manually:

```bash
setup-database.bat
```

This will:
- Create the `bommer_auth` database
- Import all required tables
- Create default admin account

### Step 2: Configure Database

Edit `config/database.php` if needed (default works with WAMP):

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bommer_auth');
define('DB_USER', 'root');
define('DB_PASS', '');  // Change if you set a password
```

### Step 3: Access Login Page

Open your browser and navigate to:

```
http://bommer.local/auth/login.php
```

### Step 4: Login with Default Credentials

**Username:** `admin`  
**Password:** `Admin@123`

⚠️ **CRITICAL:** Change this password immediately after first login!

### Step 5: Manage Users

After login, access the admin panel:

```
http://bommer.local/admin/admin-users.php
```

Here you can:
- ✅ Create new users
- ✅ Edit user details
- ✅ Reset passwords
- ✅ Enable/disable accounts
- ✅ Assign roles (admin/user)

---

## 🔐 Security Features Overview

✅ **CSRF Protection** - All forms protected  
✅ **Brute-Force Protection** - 5 attempts = 15 min lock  
✅ **Secure Sessions** - Fingerprinting + regeneration  
✅ **Password Hashing** - bcrypt via password_hash()  
✅ **Remember Me** - Secure selector/validator pattern  
✅ **SQL Injection Prevention** - PDO prepared statements  

---

## 📁 Important Files

| File | Purpose |
|------|---------|
| `auth/login.php` | Login page |
| `auth/logout.php` | Logout handler |
| `auth/validate-login.php` | Login validation |
| `admin/admin-users.php` | User management (admin only) |
| `config/database.php` | Database configuration |
| `database/schema.sql` | Database schema |

---

## 🛠️ Common Tasks

### Create a New User
1. Login as admin
2. Go to Admin → User Management
3. Click "Create New User"
4. Fill in details and click "Create User"

### Reset a User's Password
1. Login as admin
2. Go to Admin → User Management
3. Click the key icon next to the user
4. Enter new password or click "Generate Random Password"
5. Click "Reset Password"

### Disable a User Account
1. Login as admin
2. Go to Admin → User Management
3. Click the ban icon next to the user
4. Confirm the action

### Check Failed Login Attempts
View the "Failed Attempts" column in the user table. Locked accounts will show a "Locked" badge.

---

## ⚠️ Troubleshooting

### "Database connection failed"
- Check WAMP is running (green icon)
- Verify database credentials in `config/database.php`
- Ensure `bommer_auth` database exists

### "Invalid security token"
- Clear browser cookies
- Refresh the page
- Check session permissions

### Can't login
- Verify username/password are correct
- Check if account is locked (wait 15 minutes or reset in database)
- Check Apache error logs

### Fonts not showing
- Verify files exist in `/public/fonts/noto-sans-sc/`
- Check browser console for 404 errors
- Clear browser cache

---

## 📚 Full Documentation

For complete documentation, see [AUTH_README.md](AUTH_README.md)

---

## 🎯 Next Steps

1. ✅ Change default admin password
2. ✅ Create additional admin/user accounts
3. ✅ Test login/logout functionality
4. ✅ Test "Remember Me" feature
5. ✅ Review security settings
6. ✅ Set up automated token cleanup (see AUTH_README.md)

---

## 📞 Need Help?

Check the full documentation in `AUTH_README.md` for:
- Detailed API reference
- Security features explained
- Customization options
- Production deployment checklist
- Maintenance tasks
