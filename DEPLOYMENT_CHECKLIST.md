# Deployment Checklist

## 📋 Pre-Deployment Checklist

### Database Setup
- [ ] MySQL server is running
- [ ] Run `setup-database.bat` to create database and tables
- [ ] Verify all tables created: `users`, `remember_tokens`, `csrf_tokens`
- [ ] Default admin account created (username: admin, password: Admin@123)
- [ ] Database credentials updated in `config/database.php`

### File Permissions & Security
- [ ] `.htaccess` files in place for `/config`, `/includes`, `/database`
- [ ] Test that direct access to sensitive files is blocked
  - Try: `http://bommer.local/config/database.php` → should get 403 Forbidden
  - Try: `http://bommer.local/includes/functions.php` → should get 403 Forbidden
  - Try: `http://bommer.local/database/schema.sql` → should get 403 Forbidden

### PHP Configuration
- [ ] PHP version 7.4 or higher
- [ ] Required PHP extensions enabled:
  - [ ] PDO
  - [ ] pdo_mysql
  - [ ] session
  - [ ] hash
  - [ ] random (for random_bytes)
- [ ] Session settings configured:
  - [ ] `session.cookie_httponly = 1`
  - [ ] `session.use_strict_mode = 1`
  - [ ] `session.use_only_cookies = 1`
  - [ ] `session.cookie_samesite = Strict`
  - [ ] `session.cookie_secure = 1` (if using HTTPS)

### Assets & Dependencies
- [ ] Clarity UI installed at `/public/node_modules/@clr/ui/`
- [ ] Clarity Icons installed at `/public/node_modules/@clr/icons/`
- [ ] Noto Sans SC fonts present in `/public/fonts/noto-sans-sc/`:
  - [ ] NotoSansSC-Regular.otf
  - [ ] NotoSansSC-Medium.otf
  - [ ] NotoSansSC-Bold.otf
- [ ] Custom CSS loaded at `/public/css/auth.css`
- [ ] Custom JS loaded at `/public/js/login.js`

### Testing - Basic Functionality
- [ ] Login page loads: `http://bommer.local/auth/login.php`
- [ ] Login with default credentials (admin / Admin@123)
- [ ] Redirects to admin panel after successful login
- [ ] Admin panel loads: `http://bommer.local/admin/admin-users.php`
- [ ] Can view user list in admin panel
- [ ] Logout works: `http://bommer.local/auth/logout.php`
- [ ] Redirects to login after logout

### Testing - Security Features
- [ ] **CSRF Protection:**
  - [ ] Login form has hidden CSRF token field
  - [ ] Form submission without CSRF token is rejected
  
- [ ] **Brute-Force Protection:**
  - [ ] Attempt 5 failed logins with wrong password
  - [ ] Account gets locked for 15 minutes
  - [ ] Error message shows unlock time
  - [ ] After 15 minutes, can login again
  
- [ ] **Remember Me:**
  - [ ] Check "Remember me" during login
  - [ ] Close browser completely
  - [ ] Reopen and visit login page
  - [ ] Should auto-login and redirect
  - [ ] Check browser cookies: `remember_me` cookie exists
  
- [ ] **Session Security:**
  - [ ] Login successful
  - [ ] Clear session cookies manually
  - [ ] Refresh page → should redirect to login
  - [ ] Verify session regenerates on login (check session ID before/after)
  
- [ ] **Password Validation:**
  - [ ] Try creating user with weak password (e.g., "12345678")
  - [ ] Should reject with error message
  - [ ] Create user with strong password (e.g., "Test@123456")
  - [ ] Should succeed

### Testing - Admin Functions
- [ ] **Create User:**
  - [ ] Click "Create New User"
  - [ ] Fill form with valid data
  - [ ] User appears in user list
  
- [ ] **Edit User:**
  - [ ] Click pencil icon on a user
  - [ ] Change full name or role
  - [ ] Changes saved successfully
  
- [ ] **Reset Password:**
  - [ ] Click key icon on a user
  - [ ] Enter new password or generate random
  - [ ] Login as that user with new password
  
- [ ] **Toggle User Status:**
  - [ ] Click ban icon to disable user
  - [ ] User shows "Disabled" badge
  - [ ] Try logging in as disabled user → should fail
  - [ ] Enable user again
  
- [ ] **Prevent Self-Disable:**
  - [ ] Try to disable your own admin account
  - [ ] Should show error message

### Testing - UI/UX
- [ ] Fonts render correctly (Noto Sans SC)
- [ ] Clarity Design System styles applied
- [ ] Dark theme active
- [ ] Alerts display correctly (error, success, warning)
- [ ] Forms validate client-side (JavaScript)
- [ ] Modal windows open/close properly
- [ ] Buttons have hover states
- [ ] Mobile responsive (test on smaller screen)

### Production Security
- [ ] **Change default admin password** immediately
- [ ] Create additional admin accounts (don't rely on single admin)
- [ ] Review and update database credentials
- [ ] If using HTTPS:
  - [ ] Enable `session.cookie_secure = 1` in PHP
  - [ ] Update cookie settings in session.php
- [ ] Set up error logging (don't display errors to users)
- [ ] Configure automated token cleanup:
  - [ ] Set up cron job or scheduled task
  - [ ] Clean expired remember_tokens daily
  - [ ] Clean expired csrf_tokens daily

### Production Monitoring
- [ ] Set up database backups
- [ ] Monitor failed login attempts
- [ ] Monitor locked accounts
- [ ] Log security events
- [ ] Set up alerts for suspicious activity

## 🚀 Post-Deployment Tasks

### Day 1
- [ ] Login as admin and change password
- [ ] Create 2-3 test user accounts
- [ ] Test all features end-to-end
- [ ] Verify email/alerts if configured
- [ ] Document any custom configurations

### Week 1
- [ ] Monitor user feedback
- [ ] Check error logs for issues
- [ ] Verify automated cleanup is running
- [ ] Review security logs
- [ ] Test backup restoration

### Monthly
- [ ] Review user accounts and remove inactive users
- [ ] Check for failed login patterns
- [ ] Update dependencies if needed
- [ ] Review and test disaster recovery plan
- [ ] Audit admin actions

## ⚠️ Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| "Database connection failed" | Check WAMP is running, verify credentials in config/database.php |
| "Invalid security token" | Clear browser cookies, ensure session directory is writable |
| Fonts not showing | Verify font files exist in /public/fonts/noto-sans-sc/ |
| Can't create users | Check MySQL user has INSERT permission |
| Remember me not working | Check cookie settings, verify table exists |
| Account locked permanently | Run: `UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE username='user'` |

## 📊 Success Criteria

✅ All tests pass  
✅ Default admin password changed  
✅ At least one backup admin account created  
✅ Can login, create users, manage accounts  
✅ Security features working (CSRF, brute-force, remember-me)  
✅ UI renders correctly with proper fonts and styling  
✅ No PHP errors in logs  
✅ Sensitive directories protected  
✅ Documentation reviewed by team  

## 📞 Support Contacts

**Developer:** [Your Name]  
**Database Admin:** [DBA Name]  
**Security Team:** [Security Contact]  

---

## Quick Commands Reference

```bash
# Create database (Windows WAMP)
setup-database.bat

# Manual database creation
mysql -uroot -p -e "CREATE DATABASE bommer_auth"
mysql -uroot -p bommer_auth < database/schema.sql

# Reset admin password (SQL)
UPDATE users SET password_hash='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username='admin';
# New password: Admin@123

# Unlock user account (SQL)
UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE username='username';

# Clean expired tokens (SQL)
DELETE FROM remember_tokens WHERE expires_at < NOW();
DELETE FROM csrf_tokens WHERE expires_at < NOW();

# Check failed logins (SQL)
SELECT username, failed_login_attempts, locked_until FROM users WHERE failed_login_attempts > 0;
```

---

**Deployment Date:** _______________  
**Deployed By:** _______________  
**Signed Off By:** _______________  
