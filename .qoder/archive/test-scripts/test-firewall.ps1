# Test if Windows Firewall is blocking intranet access
# Must run as Administrator
# ======================================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test Windows Firewall Blocking" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host "Then run: cd C:\wamp64\www\bommer; .\test-firewall.ps1" -ForegroundColor Yellow
    Write-Host ""
    pause
    exit 1
}

Write-Host "STEP 1: Checking current firewall status..." -ForegroundColor Yellow
$fwProfile = Get-NetFirewallProfile -Name Private
Write-Host "  Private Firewall: $($fwProfile.Enabled)" -ForegroundColor Gray
Write-Host ""

Write-Host "STEP 2: Testing with firewall ENABLED..." -ForegroundColor Yellow
Write-Host "  Try accessing from another device: http://192.168.1.17" -ForegroundColor Cyan
Write-Host "  Press ENTER when you've tested (or if it's not working)..." -ForegroundColor Yellow
Read-Host

Write-Host ""
Write-Host "STEP 3: Temporarily disabling Private firewall for testing..." -ForegroundColor Yellow
try {
    Set-NetFirewallProfile -Name Private -Enabled False
    Write-Host "  Firewall DISABLED" -ForegroundColor Red
    Write-Host ""
    Write-Host "  NOW try accessing from another device: http://192.168.1.17" -ForegroundColor Cyan
    Write-Host "  Press ENTER when you've tested..." -ForegroundColor Yellow
    Read-Host
    
    Write-Host ""
    Write-Host "Did it work with firewall disabled? (Y/N): " -ForegroundColor Yellow -NoNewline
    $response = Read-Host
    
    if ($response -eq "Y" -or $response -eq "y") {
        Write-Host ""
        Write-Host "RESULT: Firewall IS the problem!" -ForegroundColor Red
        Write-Host "The firewall rules are not working correctly." -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Solution: Creating a more permissive rule..." -ForegroundColor Yellow
        
        # Remove old rules
        Get-NetFirewallRule -DisplayName "Bommer*" | Remove-NetFirewallRule -Confirm:$false
        
        # Create a simple port-based rule (more permissive)
        New-NetFirewallRule `
            -DisplayName "Bommer Apache HTTP" `
            -Direction Inbound `
            -Protocol TCP `
            -LocalPort 80 `
            -Action Allow `
            -Profile Any `
            -Enabled True | Out-Null
        
        Write-Host "  New firewall rule created!" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "RESULT: Firewall is NOT the problem!" -ForegroundColor Yellow
        Write-Host "The issue is elsewhere (router, network, or client device)." -ForegroundColor Yellow
    }
} catch {
    Write-Host "  ERROR: $_" -ForegroundColor Red
} finally {
    Write-Host ""
    Write-Host "STEP 4: Re-enabling firewall..." -ForegroundColor Yellow
    Set-NetFirewallProfile -Name Private -Enabled True
    Write-Host "  Firewall ENABLED" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test Complete" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "If it worked with firewall disabled:" -ForegroundColor White
Write-Host "  The new rule should now allow access" -ForegroundColor Gray
Write-Host "  Test again: http://192.168.1.17" -ForegroundColor Cyan
Write-Host ""
Write-Host "If it STILL doesn't work:" -ForegroundColor White
Write-Host "  Check router settings or client device" -ForegroundColor Gray
Write-Host ""
pause
