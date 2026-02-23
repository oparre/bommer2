# Noto Sans SC Font Files Setup

## Required Font Files
Please download the following Noto Sans SC font files and place them in this directory:

### Font Files Needed:
1. **NotoSansSC-Light.woff2** (Light 300)
2. **NotoSansSC-Light.woff** (Light 300)
3. **NotoSansSC-Regular.woff2** (Regular 400)
4. **NotoSansSC-Regular.woff** (Regular 400)
5. **NotoSansSC-Medium.woff2** (Medium 500)
6. **NotoSansSC-Medium.woff** (Medium 500)
7. **NotoSansSC-SemiBold.woff2** (SemiBold 600)
8. **NotoSansSC-SemiBold.woff** (SemiBold 600)
9. **NotoSansSC-Bold.woff2** (Bold 700)
10. **NotoSansSC-Bold.woff** (Bold 700)

## Download Sources:
1. **Google Fonts** (for self-hosting): https://fonts.google.com/noto/specimen/Noto+Sans+SC
2. **Google Fonts Helper**: https://google-webfonts-helper.herokuapp.com/fonts/noto-sans-sc

## Google Fonts Helper Instructions:
1. Go to: https://google-webfonts-helper.herokuapp.com/fonts/noto-sans-sc
2. Select the font weights: 300, 400, 500, 600, 700
3. Choose "Modern Browsers" for woff2 and woff formats
4. Download the ZIP file
5. Extract the font files to this directory

## Manual Download Alternative:
```bash
# Download script (PowerShell - Windows)
# You can run this from the fonts directory

$baseUrl = "https://fonts.gstatic.com/s/notosanssc/v36"
$weights = @(
    @{weight="300"; name="Light"; unicode="KVAv_0LafQftjJJkTgEZSQ%3D%3D"},
    @{weight="400"; name="Regular"; unicode="KVAv_0LafQftjJJkTgEZSQ%3D%3D"},
    @{weight="500"; name="Medium"; unicode="KVAv_0LafQftjJJkTgEZSQ%3D%3D"},
    @{weight="600"; name="SemiBold"; unicode="KVAv_0LafQftjJJkTgEZSQ%3D%3D"},
    @{weight="700"; name="Bold"; unicode="KVAv_0LafQftjJJkTgEZSQ%3D%3D"}
)

foreach ($w in $weights) {
    $woff2Url = "$baseUrl/NotoSansSC-$($w.name).woff2"
    $woffUrl = "$baseUrl/NotoSansSC-$($w.name).woff"
    
    Write-Host "Downloading NotoSansSC-$($w.name)..."
    Invoke-WebRequest -Uri $woff2Url -OutFile "NotoSansSC-$($w.name).woff2"
    Invoke-WebRequest -Uri $woffUrl -OutFile "NotoSansSC-$($w.name).woff"
}
```

## CSS Integration
The font files are already referenced in:
- `../css/clarity/clarity-dark-theme.css`

## Verification
After downloading, verify the files exist:
- [ ] NotoSansSC-Light.woff2
- [ ] NotoSansSC-Light.woff
- [ ] NotoSansSC-Regular.woff2
- [ ] NotoSansSC-Regular.woff
- [ ] NotoSansSC-Medium.woff2
- [ ] NotoSansSC-Medium.woff
- [ ] NotoSansSC-SemiBold.woff2
- [ ] NotoSansSC-SemiBold.woff
- [ ] NotoSansSC-Bold.woff2
- [ ] NotoSansSC-Bold.woff

## File Size Information
- Total expected size: ~2-3 MB for all font files
- WOFF2 files are smaller and preferred by modern browsers
- WOFF files are fallbacks for older browsers