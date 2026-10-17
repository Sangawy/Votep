# ====================================================================
# PowerShell Direct Web Disk (WebDAV) Sync Script for Pirxdry
# Direct deployment to production host without losing upload files
# Using cPanel Web Disk (WebDAV) secure protocol on Port 2078
# ====================================================================

param (
    [switch]$ForceFull,
    [string]$TargetFolder = "",
    [string]$RemotePathOverride = ""
)

$Source = $PSScriptRoot
$ConfigFile = "$PSScriptRoot\config\webdisk_config.json"
$safeTargetName = if ($TargetFolder -ne "") { $TargetFolder.Replace('/', '_').Replace('\', '_') } else { "" }
$LastSyncFile = if ($TargetFolder -ne "") { "$PSScriptRoot\config\last_sync_$safeTargetName.txt" } else { "$PSScriptRoot\config\last_sync.txt" }

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "       Pirxdry Express Direct Web Disk Deployer   " -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan

# 0. Bypass SSL certificate validations (Critical for cPanel/Self-signed SSLs)
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
[System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }

# 1. Check and load Web Disk Config
if (-not (Test-Path $ConfigFile)) {
    Write-Host "[ERROR] Configuration file not found at: $ConfigFile" -ForegroundColor Red
    Exit
}

try {
    $Config = Get-Content -Path $ConfigFile -Raw | ConvertFrom-Json
    $WebDiskUrl = $Config.webdisk_url
    $WebDiskUser = $Config.webdisk_user
    $WebDiskPass = $Config.webdisk_pass
    $rawRemote = if ($Config.PSObject.Properties['remote_path']) { $Config.remote_path } else { "" }
    if ($null -eq $rawRemote) { $rawRemote = "" }
    $RemotePath = if ($RemotePathOverride -ne "") { $RemotePathOverride.Trim('/') } else { $rawRemote.Trim('/') }
}
catch {
    Write-Host "[ERROR] Failed to parse configuration file: $_" -ForegroundColor Red
    Exit
}

if ([string]::IsNullOrWhiteSpace($WebDiskUrl) -or [string]::IsNullOrWhiteSpace($WebDiskUser) -or [string]::IsNullOrWhiteSpace($WebDiskPass)) {
    Write-Host "[ERROR] Incomplete credentials in config/webdisk_config.json" -ForegroundColor Red
    Exit
}

# Ensure base URL has the correct format
$WebDiskUrl = $WebDiskUrl.TrimEnd("/")

# Generate authorization headers
$Pair = "$($WebDiskUser):$($WebDiskPass)"
$EncodedCredentials = [System.Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes($Pair))
$Headers = @{
    "Authorization" = "Basic $EncodedCredentials"
}

Write-Host "Connecting to cPanel Web Disk..." -ForegroundColor Yellow
Write-Host "Web Disk URL: $WebDiskUrl" -ForegroundColor Yellow
$DisplayPath = if ($RemotePath -ne "") { "/$RemotePath" } else { "/" }
Write-Host "Target Remote Path: $DisplayPath" -ForegroundColor Yellow
Write-Host "Uploading from: $Source" -ForegroundColor Yellow
Write-Host "--------------------------------------------------" -ForegroundColor Cyan

# Test Connection and Authentication
try {
    $TestUrl = if ($RemotePath -ne "") { "$WebDiskUrl/$RemotePath" } else { "$WebDiskUrl/" }
    Write-Host "Testing credentials on $TestUrl..." -ForegroundColor Gray
    
    $TestRequest = [System.Net.HttpWebRequest]::Create($TestUrl)
    $TestRequest.Method = "PROPFIND"
    $TestRequest.Headers.Add("Authorization", "Basic $EncodedCredentials")
    $TestRequest.Timeout = 8000
    
    $TestResponse = $TestRequest.GetResponse()
    $TestResponse.Close()
    Write-Host "[SUCCESS] Successfully connected to Web Disk!" -ForegroundColor Green
}
catch {
    Write-Host "[WARNING] Connection test returned notice, proceeding with deployment. Details: $_" -ForegroundColor Yellow
}

# Cache for directories we already verified or created
$CreatedDirs = @{}

# Helper function to ensure remote directories exist using WebDAV MKCOL
function Ensure-WebDiskDirectory {
    param (
        [string]$dirUrl,
        [hashtable]$headers
    )
    
    $dirUrl = $dirUrl.TrimEnd("/")
    if ($CreatedDirs.ContainsKey($dirUrl)) {
        return $true
    }
    
    $request = [System.Net.HttpWebRequest]::Create($dirUrl)
    $request.Method = "MKCOL"
    $request.Headers.Add("Authorization", $headers["Authorization"])
    $request.UserAgent = "Mozilla/5.0 (PowerShell WebDAV Deployer)"
    
    try {
        $response = $request.GetResponse()
        $response.Close()
        $CreatedDirs[$dirUrl] = $true
        return $true
    }
    catch [System.Net.WebException] {
        $exception = $_.Exception
        if ($exception.Response) {
            $statusCode = [int]$exception.Response.StatusCode
            if ($statusCode -eq 405 -or $statusCode -eq 301 -or $statusCode -eq 409) {
                $CreatedDirs[$dirUrl] = $true
                return $true
            }
        }
        return $false
    }
    catch {
        return $false
    }
}

# Helper function to upload file via WebDAV PUT
function Upload-WebDiskFile {
    param (
        [string]$localFilePath,
        [string]$remoteUrl,
        [hashtable]$headers
    )
    
    try {
        $fileBytes = [System.IO.File]::ReadAllBytes($localFilePath)
        
        $request = [System.Net.HttpWebRequest]::Create($remoteUrl)
        $request.Method = "PUT"
        $request.Headers.Add("Authorization", $headers["Authorization"])
        $request.ContentType = "application/octet-stream"
        $request.ContentLength = $fileBytes.Length
        $request.UserAgent = "Mozilla/5.0 (PowerShell WebDAV Deployer)"
        $request.Timeout = 35000
        
        $requestStream = $request.GetRequestStream()
        $requestStream.Write($fileBytes, 0, $fileBytes.Length)
        $requestStream.Close()
        
        $response = $request.GetResponse()
        $response.Close()
        return $true
    }
    catch {
        Write-Host "  [FAIL] Error uploading file: $_" -ForegroundColor Red
        return $false
    }
}

# Scan and filter files
if ($TargetFolder -ne "") {
    $TargetClean = $TargetFolder.Trim('\/ ')
    $TargetScanPath = Join-Path $Source $TargetClean
    if (-not (Test-Path $TargetScanPath)) {
        Write-Host "[ERROR] Target folder not found: $TargetScanPath" -ForegroundColor Red
        Exit
    }
    Write-Host "Targeted Sync: Filtering files inside folder: $TargetClean" -ForegroundColor Yellow
    $Files = Get-ChildItem -Path $TargetScanPath -Recurse -File | Where-Object {
        $rel = $_.FullName.Substring($Source.Length).TrimStart('\/')
        $rel -notmatch '^uploads[\/\\]' -and 
        $rel -notmatch '^backups[\/\\]' -and 
        $rel -notmatch '^updates[\/\\]' -and 
        $rel -notmatch '^setup_files[\/\\]' -and 
        $rel -notmatch '[\/\\]uploads[\/\\]' -and 
        $rel -notmatch '[\/\\]updates[\/\\]' -and 
        $rel -notmatch 'node_modules' -and
        $rel -notmatch '\.git' -and
        $rel -notmatch '\.vscode' -and
        $rel -notmatch 'scratch' -and
        $rel -notmatch '\.zip' -and
        $rel -notmatch '\.tar\.gz$' -and
        $rel -notmatch '\.tmp$' -and
        $rel -notmatch '\.log$'
    }
} else {
    $Files = Get-ChildItem -Path $Source -Recurse -File | Where-Object {
        $rel = $_.FullName.Substring($Source.Length).TrimStart('\/')
        $rel -notmatch '^uploads[\/\\]' -and 
        $rel -notmatch '^backups[\/\\]' -and 
        $rel -notmatch '^updates[\/\\]' -and 
        $rel -notmatch '^setup_files[\/\\]' -and 
        $rel -notmatch '[\/\\]uploads[\/\\]' -and 
        $rel -notmatch '[\/\\]updates[\/\\]' -and 
        $rel -notmatch 'node_modules' -and
        $rel -notmatch '\.git' -and
        $rel -notmatch '\.vscode' -and
        $rel -notmatch 'scratch' -and
        $rel -notmatch '\.zip' -and
        $rel -notmatch '\.tar\.gz$' -and
        $rel -notmatch '\.tmp$' -and
        $rel -notmatch '\.log$'
    }
}

$LastSyncTime = $null
if (-not $ForceFull -and (Test-Path $LastSyncFile)) {
    try {
        $LastSyncContent = (Get-Content -Path $LastSyncFile -Raw).Trim()
        if ($LastSyncContent -match '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$') {
            $LastSyncTime = [DateTime]::Parse($LastSyncContent).ToUniversalTime()
            Write-Host "Incremental Sync: Checking files modified since $LastSyncContent" -ForegroundColor Yellow
        }
    }
    catch {
        Write-Host "[WARNING] Failed to parse last sync time. Defaulting to full sync." -ForegroundColor Yellow
    }
} else {
    Write-Host "Full Sync: Processing all files..." -ForegroundColor Yellow
}

$FilesToSync = @()
foreach ($file in $Files) {
    if ($null -eq $LastSyncTime -or $file.LastWriteTimeUtc -gt $LastSyncTime) {
        $FilesToSync += $file
    }
}

Write-Host "Scanning local files to upload (excluding uploads/ & backups/)..." -ForegroundColor Gray
Write-Host "Found $($FilesToSync.Count) files that need to be synced:" -ForegroundColor Cyan

if ($FilesToSync.Count -eq 0) {
    Write-Host "Everything is already up to date! No files modified since last sync." -ForegroundColor Green
    Exit
}

$SuccessCount = 0
$FailCount = 0
$StartTime = Get-Date

for ($i = 0; $i -lt $FilesToSync.Count; $i++) {
    $file = $FilesToSync[$i]
    $relPath = $file.FullName.Substring($Source.Length).TrimStart('\/').Replace('\', '/')
    $fileSizeKB = [math]::Round($file.Length / 1KB, 1)
    
    # Construct target URL
    $fileUrl = if ($RemotePath -ne "") { "$WebDiskUrl/$RemotePath/$relPath" } else { "$WebDiskUrl/$relPath" }
    
    # Ensure remote parent directory exists
    $parentRelPath = [System.IO.Path]::GetDirectoryName($relPath).Replace('\', '/')
    if ($parentRelPath -and $parentRelPath -ne '.' -and $parentRelPath -ne '') {
        $parts = $parentRelPath.Split('/')
        $curRel = ""
        foreach ($part in $parts) {
            if ([string]::IsNullOrWhiteSpace($part)) { continue }
            $curRel = if ($curRel) { "$curRel/$part" } else { $part }
            $curDirUrl = if ($RemotePath -ne "") { "$WebDiskUrl/$RemotePath/$curRel" } else { "$WebDiskUrl/$curRel" }
            Ensure-WebDiskDirectory -dirUrl $curDirUrl -headers $Headers | Out-Null
        }
    }
    
    $indexStr = "[$($i + 1)/$($FilesToSync.Count)]"
    Write-Host "$indexStr Syncing: $relPath (${fileSizeKB} KB)... " -NoNewline
    
    $ok = Upload-WebDiskFile -localFilePath $file.FullName -remoteUrl $fileUrl -headers $Headers
    if ($ok) {
        Write-Host "[OK]" -ForegroundColor Green
        $SuccessCount++
    } else {
        $FailCount++
    }
}

$Elapsed = (Get-Date) - $StartTime
Write-Host "--------------------------------------------------" -ForegroundColor Cyan
Write-Host "Deployment Completed in $($Elapsed.Minutes)m $($Elapsed.Seconds)s!" -ForegroundColor Green
Write-Host "  Successfully Synced: $SuccessCount files" -ForegroundColor Green
if ($FailCount -gt 0) {
    Write-Host "  Failed to Sync: $FailCount files" -ForegroundColor Red
} else {
    Write-Host "  All files synced successfully without errors!" -ForegroundColor Green
}

# Update sync timestamp if any files were successfully uploaded or no failures
if ($SuccessCount -gt 0 -or $FailCount -eq 0) {
    $CurrentUtc = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
    $CurrentUtc | Out-File -FilePath $LastSyncFile -Encoding utf8 -Force
}
Write-Host "==================================================" -ForegroundColor Cyan
