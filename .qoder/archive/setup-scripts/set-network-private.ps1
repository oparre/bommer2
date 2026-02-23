# Set Network to Private (Must run as Administrator)
# ===================================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Set Network Profile to Private" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host "Then run: cd C:\wamp64\www\bommer; .\set-network-private.ps1" -ForegroundColor Yellow
    Write-Host ""
    pause
    exit 1
}

Write-Host "Current network status:" -ForegroundColor Yellow
$profile = Get-NetConnectionProfile
Write-Host "  Network: $($profile.Name)" -ForegroundColor Gray
Write-Host "  Category: $($profile.NetworkCategory)" -ForegroundColor $(if($profile.NetworkCategory -eq "Public"){"Red"}else{"Green"})
Write-Host "  Interface: $($profile.InterfaceAlias)" -ForegroundColor Gray
Write-Host ""

if ($profile.NetworkCategory -eq "Public") {
    Write-Host "Changing network to Private..." -ForegroundColor Yellow
    try {
        Set-NetConnectionProfile -InterfaceAlias $profile.InterfaceAlias -NetworkCategory Private
        Write-Host "  SUCCESS: Network changed to Private" -ForegroundColor Green
        Write-Host ""
        Write-Host "Verifying change..." -ForegroundColor Yellow
        $newProfile = Get-NetConnectionProfile
        Write-Host "  New category: $($newProfile.NetworkCategory)" -ForegroundColor Green
    } catch {
        Write-Host "  ERROR: Failed to change network category" -ForegroundColor Red
        Write-Host "  $_" -ForegroundColor Red
        Write-Host ""
        Write-Host "Manual method:" -ForegroundColor Yellow
        Write-Host "  1. Open Settings > Network & Internet" -ForegroundColor Gray
        Write-Host "  2. Click on Wi-Fi" -ForegroundColor Gray
        Write-Host "  3. Click on your network name (asusnuovo)" -ForegroundColor Gray
        Write-Host "  4. Under Network profile, select 'Private'" -ForegroundColor Gray
    }
} else {
    Write-Host "Network is already Private - no change needed" -ForegroundColor Green
}

Write-Host ""
Write-Host "After changing to Private, test access:" -ForegroundColor White
Write-Host "  From this machine: http://192.168.31.183" -ForegroundColor Cyan
Write-Host "  From other machines: http://192.168.31.183" -ForegroundColor Cyan
Write-Host ""
pause
