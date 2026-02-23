# Test Intranet Access - Bypassing Proxy
# ========================================

Write-Host "Testing Bommer Intranet Access..." -ForegroundColor Cyan
Write-Host ""

# Temporarily disable proxy for this session
$env:NO_PROXY = "127.0.0.1,localhost,192.168.31.183,bommer.local"
[System.Net.WebRequest]::DefaultWebProxy = $null

Write-Host "[Test 1] Testing localhost (bommer.local)..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://bommer.local" -Method Head -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop
    Write-Host "  SUCCESS: $($response.StatusCode) $($response.StatusDescription)" -ForegroundColor Green
} catch {
    Write-Host "  FAILED: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "[Test 2] Testing IP address (192.168.31.183)..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://192.168.31.183" -Method Head -TimeoutSec 10 -UseBasicParsing -Proxy $null -ErrorAction Stop
    Write-Host "  SUCCESS: $($response.StatusCode) $($response.StatusDescription)" -ForegroundColor Green
} catch {
    Write-Host "  FAILED: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "[Test 3] Testing loopback (127.0.0.1)..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://127.0.0.1" -Method Head -TimeoutSec 10 -UseBasicParsing -Proxy $null -ErrorAction Stop
    Write-Host "  SUCCESS: $($response.StatusCode) $($response.StatusDescription)" -ForegroundColor Green
} catch {
    Write-Host "  FAILED: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "[Test 4] Checking if Apache is listening..." -ForegroundColor Yellow
$listening = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    $listening | ForEach-Object {
        Write-Host "  Apache is listening on: $($_.LocalAddress):$($_.LocalPort)" -ForegroundColor Green
    }
} else {
    Write-Host "  WARNING: Apache is NOT listening on port 80!" -ForegroundColor Red
}

Write-Host ""
Write-Host "[Test 5] Checking firewall rules..." -ForegroundColor Yellow
$rules = Get-NetFirewallRule -DisplayName "*Bommer*" -ErrorAction SilentlyContinue | Where-Object {$_.Enabled -eq $true}
if ($rules) {
    Write-Host "  Found $($rules.Count) enabled Bommer firewall rule(s)" -ForegroundColor Green
} else {
    Write-Host "  WARNING: No enabled Bommer firewall rules found!" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[Test 6] Checking proxy settings..." -ForegroundColor Yellow
$proxy = Get-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Internet Settings'
Write-Host "  Proxy Enabled: $($proxy.ProxyEnable)" -ForegroundColor Gray
Write-Host "  Proxy Server: $($proxy.ProxyServer)" -ForegroundColor Gray
if ($proxy.ProxyEnable -eq 1 -or $proxy.ProxyServer) {
    Write-Host "  WARNING: Proxy is configured and may interfere!" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test Complete" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "If IP address test FAILED but localhost test PASSED:" -ForegroundColor Yellow
Write-Host "  This is likely a PROXY or NETWORK ADAPTER issue" -ForegroundColor Yellow
Write-Host ""
Write-Host "Recommended actions:" -ForegroundColor White
Write-Host "  1. Make sure proxy is fully disabled or add 192.168.31.183 to bypass list" -ForegroundColor Gray
Write-Host "  2. Try from another computer on the network to isolate if it's local" -ForegroundColor Gray
Write-Host "  3. Check Windows network profile is set to 'Private'" -ForegroundColor Gray
Write-Host ""
pause
