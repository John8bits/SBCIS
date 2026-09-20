param([string]$BaseUrl = 'http://127.0.0.1:8765')
$ErrorActionPreference = 'Stop'
function Check-Response([string]$Path, [string]$Method, [int]$ExpectedStatus, [string]$ExpectedState) {
    try {
        $response = Invoke-WebRequest ($BaseUrl + $Path) -Method $Method -UseBasicParsing
        $statusCode = [int]$response.StatusCode
        $body = $response.Content
    } catch {
        if (!$_.Exception.Response) { throw }
        $statusCode = [int]$_.Exception.Response.StatusCode
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        try { $body = $reader.ReadToEnd() } finally { $reader.Dispose() }
    }
    if ($statusCode -ne $ExpectedStatus) { throw "$Path returned $statusCode, expected $ExpectedStatus" }
    if ($ExpectedState -and ($body | ConvertFrom-Json).status -ne $ExpectedState) { throw "Unexpected JSON state for $Path" }
    return $body
}
$first = (Check-Response '/app/Controllers/interpolation.php' 'GET' 200 '') | ConvertFrom-Json
$second = (Check-Response '/app/Controllers/interpolation.php' 'GET' 200 '') | ConvertFrom-Json
if ($first.status -ne $second.status) { throw 'Unchanged publication status is unstable' }
if ($null -ne $first.result -or $first.PSObject.Properties.Name -contains 'points') { throw 'Public endpoint unexpectedly publishes unapproved observations/surface' }
$null = Check-Response '/app/Controllers/interpolation.php?scope=municipality' 'GET' 400 'invalid_selection'
$null = Check-Response '/app/Controllers/interpolation.php?variable=spt_n_value' 'GET' 400 'invalid_selection'
$null = Check-Response '/app/Controllers/interpolation.php?variable[]=x' 'GET' 400 'invalid_selection'
$null = Check-Response '/app/Controllers/interpolation.php' 'POST' 405 'invalid_selection'
foreach ($action in @('status','measurements','regenerate')) {
    $method = if ($action -eq 'regenerate') { 'POST' } else { 'GET' }
    $null = Check-Response ('/app/Controllers/interpolation.php?action=' + $action) $method 401 'unauthorized'
}
foreach ($page in @('/views/gis.php', '/views/map_embed.php')) {
    $html = Check-Response $page 'GET' 200 ''
    if (!$html.Contains('gisInterpolation') -or !$html.Contains('src/js/interpolation.js')) { throw "Missing controls/script: $page" }
    if ($html.Contains('data-interpolation="regenerate"') -or $html.Contains('data-interpolation="toggle"') -or $html.Contains('data-interpolation="variable"')) { throw "Public management control found: $page" }
    if ($html.Contains('SYNTH-DEMO-')) { throw "Synthetic preview record leaked into production page: $page" }
}
Write-Output 'HTTP checks passed: public and admin GIS routes remain verified-data-only.'
