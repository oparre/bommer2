@echo off
REM Database Setup Script for Bommer Authentication System
REM This script helps set up the database using WAMP's MySQL

echo ========================================
echo Bommer Authentication - Database Setup
echo ========================================
echo.

REM Configuration
set DB_NAME=bommer_auth
set DB_USER=root
set DB_PASS=
set MYSQL_PATH=C:\wamp64\bin\mysql\mysql8.3.0\bin\mysql.exe

REM Check if MySQL executable exists
if not exist "%MYSQL_PATH%" (
    echo ERROR: MySQL not found at %MYSQL_PATH%
    echo Please update MYSQL_PATH in this script to match your WAMP installation.
    echo.
    echo Common locations:
    echo   C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe
    echo   C:\wamp64\bin\mysql\mysql5.7.36\bin\mysql.exe
    pause
    exit /b 1
)

echo MySQL found at: %MYSQL_PATH%
echo.

REM Step 1: Create database
echo Step 1: Creating database '%DB_NAME%'...
"%MYSQL_PATH%" -u%DB_USER% -p%DB_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Failed to create database. Please check your MySQL credentials.
    pause
    exit /b 1
)

echo Database created successfully!
echo.

REM Step 2: Import schema
echo Step 2: Importing database schema...
"%MYSQL_PATH%" -u%DB_USER% -p%DB_PASS% %DB_NAME% < "database\schema.sql"

if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Failed to import schema.
    pause
    exit /b 1
)

echo Schema imported successfully!
echo.

REM Step 3: Verify tables
echo Step 3: Verifying tables...
"%MYSQL_PATH%" -u%DB_USER% -p%DB_PASS% -D %DB_NAME% -e "SHOW TABLES;"

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Database Name: %DB_NAME%
echo Tables Created:
echo   - users
echo   - remember_tokens
echo   - csrf_tokens
echo.
echo Default Admin Account:
echo   Username: admin
echo   Password: Admin@123
echo.
echo IMPORTANT: Change the admin password immediately after first login!
echo.
echo Next Steps:
echo 1. Update config\database.php with your database credentials
echo 2. Navigate to http://bommer.local/auth/login.php
echo 3. Login with the default credentials
echo 4. Change the admin password immediately
echo.
pause
