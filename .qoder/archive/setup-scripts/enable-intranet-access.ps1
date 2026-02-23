# ================================================================================
# Enable Intranet Access for Bommer App
# This script must be run as Administrator
# ================================================================================

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Bommer Intranet Access Setup" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host ""
    Write-Host "To run as Administrator:" -ForegroundColor Yellow
    Write-Host "1. Right-click on PowerShell icon" -ForegroundColor Yellow
    Write-Host "2. Select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host "3. Run: cd C:\wamp64\www\bommer" -ForegroundColor Yellow
    Write-Host "4. Run: .\enable-intranet-access.ps1" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Or run this command to restart as admin:" -ForegroundColor Cyan
    Write-Host "Start-Process powershell -Verb RunAs -ArgumentList '-NoExit', '-Command', 'cd C:\wamp64\www\bommer; .\enable-intranet-access.ps1'" -ForegroundColor Gray
    Write-Host ""
    pause
    exit 1
}

Write-Host "[Step 1] Checking current network configuration..." -ForegroundColor Yellow
$ipAddress = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -like "192.168.*"} | Select-Object -First 1
if ($ipAddress) {
    Write-Host "  Found IP: $($ipAddress.IPAddress) on $($ipAddress.InterfaceAlias)" -ForegroundColor Green
} else {
    Write-Host "  WARNING: No 192.168.x.x IP found. You may be on a different network." -ForegroundColor Yellow
}
Write-Host ""

Write-Host "[Step 2] Creating/updating Windows Firewall rule for Apache on port 80..." -ForegroundColor Yellow
try {
    # Remove any existing rule with the same name
    $existingRule = Get-NetFirewallRule -DisplayName "Bommer Apache Intranet Access" -ErrorAction SilentlyContinue
    if ($existingRule) {
        Remove-NetFirewallRule -DisplayName "Bommer Apache Intranet Access" -Confirm:$false
        Write-Host "  Removed existing firewall rule" -ForegroundColor Gray
    }
    
    # Create new rule allowing port 80 for ALL profiles (to ensure it works)
    New-NetFirewallRule -DisplayName "Bommer Apache Intranet Access" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 80 `
        -Action Allow `
        -Profile Any `
        -Program "C:\wamp64\bin\apache\apache2.4.59\bin\httpd.exe" `
        -Description "Allow intranet access to Bommer app on Apache port 80" `
        -Enabled True | Out-Null
    
    Write-Host "  Firewall rule created successfully" -ForegroundColor Green
    
    # Verify the rule was created
    $newRule = Get-NetFirewallRule -DisplayName "Bommer Apache Intranet Access" -ErrorAction SilentlyContinue
    if ($newRule) {
        Write-Host "  Rule verified: $($newRule.DisplayName) - Enabled: $($newRule.Enabled)" -ForegroundColor Gray
    }
} catch {
    Write-Host "  ERROR: Could not create firewall rule: $_" -ForegroundColor Red
    Write-Host "  Try creating manually via Windows Firewall GUI" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "[Step 3] Restarting Apache service..." -ForegroundColor Yellow
try {
    $service = Get-Service -Name "wampapache64" -ErrorAction Stop
    
    if ($service.Status -eq "Running") {
        Restart-Service -Name "wampapache64" -Force
        Start-Sleep -Seconds 2
        $newStatus = (Get-Service -Name "wampapache64").Status
        if ($newStatus -eq "Running") {
            Write-Host "  Apache restarted successfully" -ForegroundColor Green
        } else {
            Write-Host "  WARNING: Apache status is $newStatus" -ForegroundColor Yellow
        }
    } else {
        Start-Service -Name "wampapache64"
        Write-Host "  Apache started successfully" -ForegroundColor Green
    }
} catch {
    Write-Host "  ERROR: Could not restart Apache: $_" -ForegroundColor Red
    Write-Host "  Please restart Apache manually via WAMP menu" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "[Step 4] Verifying Apache is listening on network interface..." -ForegroundColor Yellow
try {
    $connections = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
    if ($connections) {
        $connections | ForEach-Object {
            Write-Host "  Apache listening on: $($_.LocalAddress):$($_.LocalPort)" -ForegroundColor Green
        }
    } else {
        Write-Host "  WARNING: Apache not listening on port 80" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  Could not check port status" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "[Step 5] Testing local connectivity..." -ForegroundColor Yellow
try {
    $testResult = Test-NetConnection -ComputerName 192.168.31.183 -Port 80 -WarningAction SilentlyContinue -InformationLevel Quiet
    if ($testResult) {
        Write-Host "  Port 80 is accessible from localhost" -ForegroundColor Green
    } else {
        Write-Host "  WARNING: Port 80 test failed from localhost" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  Could not test port 80" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor White
Write-Host "==========" -ForegroundColor White
Write-Host ""
Write-Host "1. Test from THIS machine:" -ForegroundColor White
Write-Host "   Open browser and go to: http://192.168.31.183" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. Test from another machine on your network (192.168.31.x):" -ForegroundColor White
Write-Host "   Open browser and go to: http://192.168.31.183" -ForegroundColor Cyan
Write-Host ""
Write-Host "If other machines STILL can't connect:" -ForegroundColor Yellow
Write-Host ""
Write-Host "DEBUG STEP 1 - Test if firewall is blocking:" -ForegroundColor Yellow
Write-Host "  a) Temporarily disable Windows Firewall" -ForegroundColor Gray
Write-Host "  b) Try accessing from another machine" -ForegroundColor Gray
Write-Host "  c) If it works, the firewall is the issue" -ForegroundColor Gray
Write-Host ""
Write-Host "DEBUG STEP 2 - Check network discovery:" -ForegroundColor Yellow
Write-Host "  a) Open Settings > Network & Internet > Ethernet (or Wi-Fi)" -ForegroundColor Gray
Write-Host "  b) Click on your connection" -ForegroundColor Gray
Write-Host "  c) Set Network profile to 'Private'" -ForegroundColor Gray
Write-Host ""
Write-Host "DEBUG STEP 3 - Manual firewall rule via GUI:" -ForegroundColor Yellow
Write-Host "  a) Open Windows Defender Firewall > Advanced settings" -ForegroundColor Gray
Write-Host "  b) Inbound Rules > New Rule" -ForegroundColor Gray
Write-Host "  c) Port > TCP > Specific local ports: 80" -ForegroundColor Gray
Write-Host "  d) Allow the connection > All profiles" -ForegroundColor Gray
Write-Host "  e) Name: Bommer Apache Port 80" -ForegroundColor Gray
Write-Host ""
Write-Host "Current IP Address: 192.168.31.183" -ForegroundColor Cyan
Write-Host ""
pause
