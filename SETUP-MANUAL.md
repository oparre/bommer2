# Manual Database Setup Instructions

Since MySQL is not in your system PATH, follow these steps to set up the database manually:

## Option 1: Using phpMyAdmin (Recommended)

1. **Start WAMP Server** and ensure it's running (green icon)

2. **Open phpMyAdmin**
   - Go to: http://localhost/phpmyadmin/

3. **Import Authentication Schema**
   - Click "SQL" tab at the top
   - Click "Import file" or paste the contents of `database/schema.sql`
   - Click "Go" to execute

4. **Import BOM Management Schema**
   - Still in the SQL tab
   - Click "Import file" or paste the contents of `database/bommer-schema.sql`
   - Click "Go" to execute

## Option 2: Using MySQL Command Line with Full Path

1. **Find your MySQL installation path** (usually one of these):
   - `C:\wamp64\bin\mysql\mysql8.0.X\bin\mysql.exe`
   - `C:\wamp\bin\mysql\mysql8.0.X\bin\mysql.exe`

2. **Open Command Prompt** and navigate to your Bommer directory:
   ```cmd
   cd C:\wamp64\www\bommer
   ```

3. **Run these commands** (replace the path with your actual MySQL path):
   ```cmd
   "C:\wamp64\bin\mysql\mysql8.0.36\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS bommer_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   
   "C:\wamp64\bin\mysql\mysql8.0.36\bin\mysql.exe" -u root bommer_auth < database\schema.sql
   
   "C:\wamp64\bin\mysql\mysql8.0.36\bin\mysql.exe" -u root bommer_auth < database\bommer-schema.sql
   ```

## Option 3: Using WAMP MySQL Console

1. **Click on WAMP icon** in system tray
2. **Click on MySQL** → **MySQL Console**
3. **Enter your root password** (if any, usually empty)
4. **Run these commands one by one**:
   ```sql
   CREATE DATABASE IF NOT EXISTS bommer_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE bommer_auth;
   SOURCE C:/wamp64/www/bommer/database/schema.sql;
   SOURCE C:/wamp64/www/bommer/database/bommer-schema.sql;
   EXIT;
   ```

## Verify Setup

After completing the setup, verify it worked:

1. **Open phpMyAdmin**: http://localhost/phpmyadmin/
2. **Click on "bommer_auth" database** on the left sidebar
3. **You should see these tables**:
   - users
   - remember_tokens
   - csrf_tokens
   - projects
   - assemblies
   - assembly_projects
   - components
   - boms
   - bom_revisions
   - bom_groups
   - bom_items
   - audit_logs

## Access the Application

Once the database is set up:

1. **Open your browser** and go to: http://bommer.local/

2. **Login with default credentials**:
   - Username: `admin`
   - Password: `Admin@123`

3. **⚠️ IMPORTANT**: Change the default admin password immediately after first login!

## Troubleshooting

### "Table already exists" error
- This is OK if you're re-running the setup
- The schema uses `CREATE TABLE IF NOT EXISTS`

### "Database connection failed"
- Check WAMP is running (green icon)
- Verify database name is `bommer_auth`
- Check `config/database.php` credentials match your setup

### No data showing
- Make sure both SQL files were imported
- Check if tables have data by running: `SELECT * FROM boms;`

### Can't login
- Verify the users table has the admin account
- Try resetting the password using `fix-admin-password.php`
