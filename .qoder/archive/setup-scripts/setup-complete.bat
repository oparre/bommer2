@echo off
REM ============================================================================
REM Bommer BOM Management System - Complete Setup Script
REM ============================================================================

echo ========================================
echo Bommer BOM Management System - Setup
echo ========================================
echo.

REM Step 1: Setup authentication database
echo [1/2] Setting up authentication database...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS bommer_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root bommer_auth < database\schema.sql
echo Authentication database setup complete!
echo.

REM Step 2: Setup BOM management schema
echo [2/2] Setting up BOM management schema...
mysql -u root bommer_auth < database\bommer-schema.sql
echo BOM management schema setup complete!
echo.

echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Access the application at: http://bommer.local/
echo 2. Login with default credentials:
echo    Username: admin
echo    Password: Admin@123
echo.
echo ⚠ IMPORTANT: Change the default admin password immediately!
echo.
pause
