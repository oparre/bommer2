# Bommer.local Diagnostic and Fix Script
# Run as Administrator

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Bommer.local Diagnostic Tool" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check admin rights
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[WARNING] Not running as Administrator - some checks may fail" -ForegroundColor Yellow
    Write-Host ""
}

# 1. Check hosts file
Write-Host "1. Checking hosts file entry..." -ForegroundColor Yellow
$hostsPath = "C:\Windows\System32\drivers\etc\hosts"
$hostsContent = Get-Content $hostsPath -Raw
if ($hostsContent -match "127\.0\.0\.1\s+bommer\.local") {
    Write-Host "   [OK] bommer.local found in hosts file" -ForegroundColor Green
} else {
    Write-Host "   [FAIL] bommer.local NOT found in hosts file" -ForegroundColor Red
    Write-Host "   Fix: Add '127.0.0.1  bommer.local' to hosts file" -ForegroundColor Yellow
}

# 2. Check Apache virtual host config
Write-Host ""
Write-Host "2. Checking Apache virtual host configuration..." -ForegroundColor Yellow
$vhostPath = "C:\wamp64\bin\apache\apache2.4.59\conf\extra\httpd-vhosts.conf"
if (Test-Path $vhostPath) {
    $vhostContent = Get-Content $vhostPath -Raw
    if ($vhostContent -match "ServerName bommer\.local") {
        Write-Host "   [OK] bommer.local VirtualHost configured" -ForegroundColor Green
    } else {
        Write-Host "   [FAIL] bommer.local VirtualHost NOT configured" -ForegroundColor Red
        Write-Host "   Fix: Add VirtualHost block to httpd-vhosts.conf" -ForegroundColor Yellow
    }
} else {
    Write-Host "   [FAIL] httpd-vhosts.conf not found" -ForegroundColor Red
}

# 3. Check if Apache is running
Write-Host ""
Write-Host "3. Checking Apache service..." -ForegroundColor Yellow
$apacheService = Get-Service -Name "wampapache64" -ErrorAction SilentlyContinue
if ($apacheService) {
    if ($apacheService.Status -eq "Running") {
        Write-Host "   [OK] Apache service is running" -ForegroundColor Green
    } else {
        Write-Host "   [FAIL] Apache service is NOT running (Status: $($apacheService.Status))" -ForegroundColor Red
        Write-Host "   Fix: Start Apache from WAMP menu" -ForegroundColor Yellow
    }
} else {
    Write-Host "   [FAIL] Apache service not found" -ForegroundColor Red
}

# 4. Check if port 80 is listening
Write-Host ""
Write-Host "4. Checking if port 80 is listening..." -ForegroundColor Yellow
try {
    $port80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($port80) {
        $processId = $port80.OwningProcess
        $process = Get-Process -Id $processId -ErrorAction SilentlyContinue
        Write-Host "   [OK] Port 80 is listening (Process: $($process.ProcessName), PID: $processId)" -ForegroundColor Green
    } else {
        Write-Host "   [FAIL] Port 80 is NOT listening" -ForegroundColor Red
        Write-Host "   Fix: Check if Apache is running or if another service is blocking port 80" -ForegroundColor Yellow
    }
} catch {
    Write-Host "   [ERROR] Could not check port 80: $_" -ForegroundColor Red
}

# 5. Test HTTP connection to bommer.local
Write-Host ""
Write-Host "5. Testing HTTP connection to bommer.local..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://bommer.local" -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Host "   [OK] Successfully connected! (HTTP $($response.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "   [FAIL] Cannot connect to bommer.local" -ForegroundColor Red
    Write-Host "   Error: $($_.Exception.Message)" -ForegroundColor Red
}

# 6. Check DocumentRoot
Write-Host ""
Write-Host "6. Checking DocumentRoot..." -ForegroundColor Yellow
$docRoot = "C:\wamp64\www\bommer"
if (Test-Path $docRoot) {
    Write-Host "   [OK] DocumentRoot exists: $docRoot" -ForegroundColor Green
    $indexFiles = @("index.html", "index.php", "auth\login.php")
    foreach ($file in $indexFiles) {
        $fullPath = Join-Path $docRoot $file
        if (Test-Path $fullPath) {
            Write-Host "   [OK] Found: $file" -ForegroundColor Green
        }
    }
} else {
    Write-Host "   [FAIL] DocumentRoot not found: $docRoot" -ForegroundColor Red
}

# Summary
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "RECOMMENDED ACTIONS:" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "If you see any [FAIL] messages above:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Make sure WAMP is running (green icon)" -ForegroundColor White
Write-Host "2. Restart Apache:" -ForegroundColor White
Write-Host "   - Click WAMP icon > Apache > Service administration > Restart Service" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Clear DNS cache (run as admin):" -ForegroundColor White
Write-Host "   Clear-DnsClientCache" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Try accessing these URLs in your browser:" -ForegroundColor White
Write-Host "   http://bommer.local" -ForegroundColor Gray
Write-Host "   http://bommer.local/auth/login.php" -ForegroundColor Gray
Write-Host "   http://localhost/bommer/auth/login.php" -ForegroundColor Gray
Write-Host ""
Write-Host "5. Check browser settings:" -ForegroundColor White
Write-Host "   - Clear browser cache" -ForegroundColor Gray
Write-Host "   - Disable proxy settings" -ForegroundColor Gray
Write-Host "   - Try incognito/private mode" -ForegroundColor Gray
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan

pause
