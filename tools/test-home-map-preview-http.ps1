param([string]$BaseUrl = 'http://127.0.0.1:18085')
$ErrorActionPreference = 'Stop'
$checks = 0
$origin = [Uri]($BaseUrl.TrimEnd('/') + '/')
$previewUrl = [Uri]::new($origin, 'views/home_map_preview.php')
$preview = Invoke-WebRequest $previewUrl -UseBasicParsing
if ($preview.StatusCode -ne 200 -or !$preview.Content.Contains('id="gisMap"')) { throw 'Preview did not render the shared map.' }
$checks++
if ($preview.Headers['X-Frame-Options'] -ne 'SAMEORIGIN') { throw 'Preview is blocked by X-Frame-Options.' }
$checks++
$previewPolicy = $preview.Headers['Content-Security-Policy']
if ($previewPolicy -notmatch "frame-ancestors 'self'(;|$)" -or $previewPolicy -match "frame-ancestors 'none'") {
    throw 'Preview CSP blocks same-origin homepage embedding.'
}
$checks++
if ($preview.Headers['Cache-Control'] -notmatch 'no-store') { throw 'Preview may serve stale borehole data.' }
$checks++
foreach ($page in @('index.php', 'views/gis.php')) {
    $response = Invoke-WebRequest ([Uri]::new($origin, $page)) -UseBasicParsing
    if ($response.Headers['X-Frame-Options'] -ne 'DENY') { throw "Framing protection changed for $page" }
    $policy = $response.Headers['Content-Security-Policy']
    if ($policy -notmatch "frame-ancestors 'none'") { throw "Framing protection changed for $page" }
    # Only the preview's frame-ancestors directive may differ from the site policy.
    if ($previewPolicy -ne $policy.Replace("frame-ancestors 'none'", "frame-ancestors 'self'")) {
        throw 'Preview unexpectedly changed other security directives.'
    }
    $checks++
}
foreach ($asset in [regex]::Matches($preview.Content, '(?:src|href)="([^"]+)"')) {
    $assetUrl = [Uri]::new($previewUrl, $asset.Groups[1].Value)
    if ($assetUrl.Authority -ne $origin.Authority) { continue }
    $response = Invoke-WebRequest $assetUrl -UseBasicParsing
    if ($response.StatusCode -ne 200) { throw "Preview asset failed: $assetUrl" }
    $checks++
}
Write-Output "$checks homepage preview HTTP checks passed: embedding headers, preserved site policy, cache, and local dependencies."
