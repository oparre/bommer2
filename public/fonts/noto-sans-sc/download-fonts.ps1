# Noto Sans SC Font Download Script
# PowerShell script to download Noto Sans SC fonts for self-hosting
# Run this script from the fonts/noto-sans-sc directory

Write-Host "Downloading Noto Sans SC fonts..." -ForegroundColor Green

# Create the target directory if it doesn't exist
$targetDir = "."
if (!(Test-Path $targetDir)) {
    New-Item -ItemType Directory -Path $targetDir
}

# Font files to download from Google Fonts
$fonts = @(
    @{
        name = "NotoSansSC-Light"
        weight = "300"
        woff2 = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlPevWjMY.woff2"
        woff = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlPevW.woff"
    },
    @{
        name = "NotoSansSC-Regular"
        weight = "400"
        woff2 = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlOOvWjMY.woff2"
        woff = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlOOvW.woff"
    },
    @{
        name = "NotoSansSC-Medium"
        weight = "500"
        woff2 = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlMevWjMY.woff2"
        woff = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlMevW.woff"
    },
    @{
        name = "NotoSansSC-SemiBold"
        weight = "600"
        woff2 = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlBevWjMY.woff2"
        woff = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlBevW.woff"
    },
    @{
        name = "NotoSansSC-Bold"
        weight = "700"
        woff2 = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlLevWjMY.woff2"
        woff = "https://fonts.gstatic.com/s/notosanssc/v36/HI_SiYsKILxRpg3hIP6sJ7fM7PqlLevW.woff"
    }
)

$totalFiles = $fonts.Count * 2  # woff2 and woff for each weight
$currentFile = 0

foreach ($font in $fonts) {
    Write-Host "Downloading $($font.name)..." -ForegroundColor Yellow
    
    # Download WOFF2 file
    $currentFile++
    $woff2File = "$($font.name).woff2"
    try {
        Write-Progress -Activity "Downloading Fonts" -Status "WOFF2: $woff2File" -PercentComplete (($currentFile / $totalFiles) * 100)
        Invoke-WebRequest -Uri $font.woff2 -OutFile $woff2File -ErrorAction Stop
        Write-Host "  Downloaded $woff2File" -ForegroundColor Green
    }
    catch {
        Write-Host "  Failed to download $woff2File" -ForegroundColor Red
        Write-Host "    Error: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    # Download WOFF file
    $currentFile++
    $woffFile = "$($font.name).woff"
    try {
        Write-Progress -Activity "Downloading Fonts" -Status "WOFF: $woffFile" -PercentComplete (($currentFile / $totalFiles) * 100)
        Invoke-WebRequest -Uri $font.woff -OutFile $woffFile -ErrorAction Stop
        Write-Host "  Downloaded $woffFile" -ForegroundColor Green
    }
    catch {
        Write-Host "  Failed to download $woffFile" -ForegroundColor Red
        Write-Host "    Error: $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Progress -Activity "Downloading Fonts" -Completed

# Verify downloads
Write-Host "`nVerifying downloads..." -ForegroundColor Cyan
$expectedFiles = @()
foreach ($font in $fonts) {
    $expectedFiles += "$($font.name).woff2"
    $expectedFiles += "$($font.name).woff"
}

$missingFiles = @()
$totalSize = 0

foreach ($file in $expectedFiles) {
    if (Test-Path $file) {
        $size = (Get-Item $file).Length
        $totalSize += $size
        $sizeKB = [math]::Round($size / 1KB, 1)
        Write-Host "  $file ($sizeKB KB)" -ForegroundColor Green
    }
    else {
        $missingFiles += $file
        Write-Host "  Missing: $file" -ForegroundColor Red
    }
}

Write-Host "`nDownload Summary:" -ForegroundColor Cyan
Write-Host "  Expected files: $($expectedFiles.Count)" -ForegroundColor White
Write-Host "  Downloaded: $($expectedFiles.Count - $missingFiles.Count)" -ForegroundColor Green
Write-Host "  Missing: $($missingFiles.Count)" -ForegroundColor Red
Write-Host "  Total size: $([math]::Round($totalSize / 1MB, 1)) MB" -ForegroundColor White

if ($missingFiles.Count -eq 0) {
    Write-Host "`nAll Noto Sans SC fonts downloaded successfully!" -ForegroundColor Green
    Write-Host "The fonts are now ready for use in your Clarity Design System." -ForegroundColor Green
}
else {
    Write-Host "`nSome files failed to download. You may need to:" -ForegroundColor Yellow
    Write-Host "   1. Check your internet connection" -ForegroundColor Yellow
    Write-Host "   2. Run the script as administrator" -ForegroundColor Yellow
    Write-Host "   3. Download missing files manually" -ForegroundColor Yellow
}

Write-Host "`nNext steps:" -ForegroundColor Cyan
Write-Host "  1. Include clarity-integration.css in your HTML" -ForegroundColor White
Write-Host "  2. Add class 'clarity-theme-dark' to your body tag" -ForegroundColor White
Write-Host "  3. Test the fonts in your browser" -ForegroundColor White

Write-Host "`nPress any key to continue..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")