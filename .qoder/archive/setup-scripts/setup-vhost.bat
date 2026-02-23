@echo off
echo ============================================
echo Bommer Apache Virtual Host Setup
echo ============================================
echo.

echo Step 1: Checking hosts file...
findstr /C:"bommer.local" C:\Windows\System32\drivers\etc\hosts >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] bommer.local entry found in hosts file
) else (
    echo [!] bommer.local entry NOT found in hosts file
    echo.
    echo You need to add this line to your hosts file:
    echo 127.0.0.1  bommer.local
    echo.
    echo Run this command in PowerShell AS ADMINISTRATOR:
    echo Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value "127.0.0.1  bommer.local"
)

echo.
echo Step 2: Checking Apache virtual hosts configuration...
echo.
echo Your Apache httpd-vhosts.conf should be located at:
echo C:\wamp64\bin\apache\apache2.4.xx\conf\extra\httpd-vhosts.conf
echo.
echo The configuration needed is in: httpd-vhosts-CORRECTED.conf
echo.

echo ============================================
echo MANUAL SETUP INSTRUCTIONS:
echo ============================================
echo.
echo 1. ADD HOSTS FILE ENTRY (Run PowerShell as Administrator):
echo    Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value "127.0.0.1  bommer.local"
echo.
echo 2. CONFIGURE APACHE VIRTUAL HOST:
echo    a. Find your Apache version in C:\wamp64\bin\apache\
echo    b. Open: C:\wamp64\bin\apache\apache2.4.xx\conf\extra\httpd-vhosts.conf
echo    c. Copy the content from: httpd-vhosts-CORRECTED.conf
echo       (lines 31-45, the bommer.local VirtualHost block)
echo    d. Paste it into httpd-vhosts.conf
echo.
echo 3. RESTART APACHE:
echo    - Left-click on WAMP icon in system tray
echo    - Apache -^> Service administration -^> Restart Service
echo.
echo 4. FLUSH DNS CACHE (Run in Command Prompt):
echo    ipconfig /flushdns
echo.
echo 5. TEST:
echo    Open browser and go to: http://bommer.local/auth/login.php
echo.
echo ============================================
pause
