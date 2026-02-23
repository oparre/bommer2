@echo off
echo ========================================
echo Restarting Apache Service
echo ========================================
echo.

net stop wampapache64
timeout /t 2 /nobreak > nul
net start wampapache64

echo.
echo Apache has been restarted!
echo.
echo Test access: http://192.168.31.183
echo.
pause
