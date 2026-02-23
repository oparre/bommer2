# Bommer Apache Virtual Host Setup Script
# Run this script AS ADMINISTRATOR

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Bommer Apache Virtual Host Setup" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "[ERROR] This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host ""
    pause
    exit
}

Write-Host "[OK] Running as Administrator" -ForegroundColor Green
Write-Host ""

# Step 1: Check and add hosts file entry
Write-Host "Step 1: Checking hosts file..." -ForegroundColor Yellow
$hostsPath = "C:\Windows\System32\drivers\etc\hosts"
$hostsContent = Get-Content $hostsPath -Raw

if ($hostsContent -match "bommer\.local") {
    Write-Host "[OK] bommer.local entry already exists in hosts file" -ForegroundColor Green
} else {
    Write-Host "[!] Adding bommer.local to hosts file..." -ForegroundColor Yellow
    try {
        Add-Content -Path $hostsPath -Value "`n127.0.0.1  bommer.local"
        Write-Host "[OK] Successfully added bommer.local to hosts file" -ForegroundColor Green
    } catch {
        Write-Host "[ERROR] Failed to add entry to hosts file: $_" -ForegroundColor Red
    }
}

Write-Host ""

# Step 2: Find Apache version
Write-Host "Step 2: Locating Apache installation..." -ForegroundColor Yellow
$apachePath = "C:\wamp64\bin\apache"

if (Test-Path $apachePath) {
    $apacheVersions = Get-ChildItem -Path $apachePath -Directory | Where-Object { $_.Name -like "apache*" }
    
    if ($apacheVersions.Count -gt 0) {
        $latestApache = $apacheVersions | Sort-Object Name -Descending | Select-Object -First 1
        $vhostsFile = Join-Path $latestApache.FullName "conf\extra\httpd-vhosts.conf"
        
        Write-Host "[OK] Found Apache: $($latestApache.Name)" -ForegroundColor Green
        Write-Host "Virtual hosts file: $vhostsFile" -ForegroundColor Cyan
        
        if (Test-Path $vhostsFile) {
            # Check if bommer.local vhost already exists
            $vhostsContent = Get-Content $vhostsFile -Raw
            
            if ($vhostsContent -match "ServerName bommer\.local") {
                Write-Host "[OK] bommer.local VirtualHost already configured" -ForegroundColor Green
            } else {
                Write-Host "[!] bommer.local VirtualHost NOT found" -ForegroundColor Yellow
                Write-Host ""
                Write-Host "Please manually add the VirtualHost configuration:" -ForegroundColor Yellow
                Write-Host "1. Open: $vhostsFile" -ForegroundColor White
                Write-Host "2. Copy the VirtualHost block from: httpd-vhosts-CORRECTED.conf (lines 31-45)" -ForegroundColor White
                Write-Host "3. Paste it at the end of httpd-vhosts.conf" -ForegroundColor White
            }
        } else {
            Write-Host "[ERROR] httpd-vhosts.conf not found at: $vhostsFile" -ForegroundColor Red
        }
    } else {
        Write-Host "[ERROR] No Apache installation found in: $apachePath" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] WAMP Apache directory not found: $apachePath" -ForegroundColor Red
}

Write-Host ""

# Step 3: Flush DNS cache
Write-Host "Step 3: Flushing DNS cache..." -ForegroundColor Yellow
try {
    ipconfig /flushdns | Out-Null
    Write-Host "[OK] DNS cache flushed successfully" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Failed to flush DNS cache: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "NEXT STEPS:" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. If VirtualHost was not found, manually add it to httpd-vhosts.conf" -ForegroundColor Yellow
Write-Host "   (See instructions above)" -ForegroundColor Yellow
Write-Host ""
Write-Host "2. RESTART APACHE:" -ForegroundColor Yellow
Write-Host "   - Click WAMP icon in system tray" -ForegroundColor White
Write-Host "   - Apache -> Service administration -> Restart Service" -ForegroundColor White
Write-Host ""
Write-Host "3. TEST in browser:" -ForegroundColor Yellow
Write-Host "   http://bommer.local/auth/login.php" -ForegroundColor White
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
pause
