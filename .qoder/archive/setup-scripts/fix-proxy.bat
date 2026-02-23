@echo off
echo ========================================
echo Fix Proxy Settings for Bommer Access
echo ========================================
echo.
echo Adding 192.168.31.183 to proxy bypass list...
echo.

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings" /v ProxyOverride /t REG_SZ /d "192.168.31.183;bommer.local;localhost;127.0.0.1;<local>" /f

echo.
echo Proxy bypass configured!
echo.
echo Now test in your browser:
echo   http://192.168.31.183
echo.
echo If it still doesn't work:
echo   1. Close and reopen your browser
echo   2. Or press Ctrl+Shift+Delete to clear browser cache
echo   3. Try in Private/Incognito mode
echo.
pause
