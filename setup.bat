@echo off
echo ==========================================
echo Bommer Project Setup
echo ==========================================
echo.

REM Check if running from project root
if not exist "public\package.json" (
    echo ERROR: Please run this script from the project root directory.
    pause
    exit /b 1
)

echo [1/3] Installing Node.js dependencies...
cd public
call npm install
if errorlevel 1 (
    echo ERROR: npm install failed. Make sure Node.js is installed.
    pause
    exit /b 1
)
cd ..
echo Dependencies installed successfully.
echo.

echo [2/3] Checking font files...
if not exist "public\fonts\noto-sans-sc\NotoSansSC-Regular.woff2" (
    echo Font files not found. Downloading fonts...
    cd public\fonts\noto-sans-sc
    call download-fonts.ps1
    cd ..\..\..
) else (
    echo Font files already exist. Skipping download.
)
echo.

echo [3/3] Setup complete!
echo.
echo ==========================================
echo Next steps:
echo   1. Configure your database in config/database.php
echo   2. Import database schema from database/schema.sql
echo   3. Start your Apache server
echo ==========================================
pause
