<#
    .SYNOPSIS
    System Auto-Updater for Peshmerga System
#>

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "          Peshmerga System Auto-Updater             " -ForegroundColor White -BackgroundColor DarkBlue
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host ""

$CurrentDir = $PSScriptRoot
$UpdateFolder = Join-Path $CurrentDir "updates"
$BackupFolder = Join-Path $CurrentDir "backups\manual_backups"
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"

# 1. Dynamic Environment Resolution
$ConfigPath = Join-Path $CurrentDir "config.php"
$DBName = "pirxdry_db" # Default fallback
if (Test-Path $ConfigPath) {
    foreach ($line in Get-Content $ConfigPath) {
        if ($line -match "define\('DB_NAME',\s*'(.+?)'\)") {
            $DBName = $Matches[1]
            break
        }
    }
}

$InstallRoot = ""
if ($CurrentDir -match "(?i)([A-Z]):\\laragon\\www\\") {
    $InstallRoot = "$($Matches[1]):\Laragon"
} else {
    foreach ($d in @("C", "D", "E", "F")) {
        if (Test-Path "$($d):\Laragon\laragon.exe") {
            $InstallRoot = "$($d):\Laragon"
            break
        }
    }
}

$MySQLDump = $null
$MySQLExe = $null
if (![string]::IsNullOrEmpty($InstallRoot)) {
    $MySQLDump = Get-ChildItem -Path "$InstallRoot\bin\mysql\mysql-*\bin\mysqldump.exe", "$InstallRoot\bin\mariadb\mariadb-*\bin\mysqldump.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
    $MySQLExe = Get-ChildItem -Path "$InstallRoot\bin\mysql\mysql-*\bin\mysql.exe", "$InstallRoot\bin\mariadb\mariadb-*\bin\mysql.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
}

# 2. Check if updates folder exists
if (!(Test-Path $UpdateFolder)) {
    Write-Host "Error: 'updates' folder not found. Please extract the update files to the 'updates' folder." -ForegroundColor Red
    pause
    exit
}

# 3. Start Backup
Write-Host "[ Step 1 ] Creating Backup..." -ForegroundColor Yellow
$DestBackup = Join-Path $BackupFolder "backup_$Timestamp"
if (!(Test-Path $DestBackup)) { New-Item -Path $DestBackup -ItemType Directory -Force | Out-Null }

Write-Host "Backing up files..." -ForegroundColor Gray
$Excludes = @("backups", "updates", "updater.ps1", "updater.bat", "uploads", "library Books", "MD File")

Get-ChildItem -Path $CurrentDir -Recurse | ForEach-Object {
    # Get path relative to the root directory
    $relativePath = $_.FullName.Substring($CurrentDir.Length + 1)
    
    # Check if this item is inside any of the excluded folders
    $skip = $false
    foreach ($exclude in $Excludes) {
        if ($relativePath -eq $exclude -or $relativePath -like "$exclude\*") {
            $skip = $true
            break
        }
    }
    
    if (!$skip) {
        $targetPath = Join-Path $DestBackup $relativePath
        if ($_.PSIsContainer) {
            if (!(Test-Path $targetPath)) {
                New-Item -Path $targetPath -ItemType Directory -Force | Out-Null
            }
        } else {
            $parentDir = Split-Path $targetPath
            if (!(Test-Path $parentDir)) {
                New-Item -Path $parentDir -ItemType Directory -Force | Out-Null
            }
            Copy-Item -Path $_.FullName -Destination $targetPath -Force
        }
    }
}

# Database Backup (Attempt)
if ($MySQLDump) {
    Write-Host "Backing up database..." -ForegroundColor Gray
    $DBBackupFile = Join-Path $DestBackup "db_backup.sql"
    & $MySQLDump.FullName -u root $DBName --result-file=$DBBackupFile
    Write-Host "Database backup created: $DBBackupFile" -ForegroundColor Green
}
Write-Host "Backup completed successfully." -ForegroundColor Green
Write-Host ""

# 4. File Replacement
Write-Host "[ Step 2 ] Updating Code Files..." -ForegroundColor Yellow
$Excludes = @("config.php", "uploads", "backups", "updates", "library Books", "MD File")

Get-ChildItem -Path $UpdateFolder -Recurse | ForEach-Object {
    $relativePath = $_.FullName.Substring($UpdateFolder.Length + 1)
    
    # Check if this item is inside any of the excluded folders/files
    $skip = $false
    foreach ($exclude in $Excludes) {
        if ($relativePath -eq $exclude -or $relativePath -like "$exclude\*") {
            $skip = $true
            break
        }
    }
    
    if (!$skip) {
        $targetPath = Join-Path $CurrentDir $relativePath
        if ($_.PSIsContainer) {
            if (!(Test-Path $targetPath)) {
                New-Item -Path $targetPath -ItemType Directory -Force | Out-Null
            }
        } else {
            $parentDir = Split-Path $targetPath
            if (!(Test-Path $parentDir)) {
                New-Item -Path $parentDir -ItemType Directory -Force | Out-Null
            }
            Copy-Item -Path $_.FullName -Destination $targetPath -Force
            Write-Host "Updated: $relativePath" -ForegroundColor Gray
        }
    }
}
Write-Host "Files updated successfully." -ForegroundColor Green
Write-Host ""

# 5. SQL Migration
Write-Host "[ Step 3 ] Running Database Migrations..." -ForegroundColor Yellow
$UpdateSQL = Join-Path $UpdateFolder "update.sql"

if (Test-Path $UpdateSQL) {
    if ($MySQLExe) {
        Write-Host "Executing SQL migration..." -ForegroundColor Gray
        $oldErrorAction = $ErrorActionPreference
        $ErrorActionPreference = "Continue"
        $MySQLPath = $UpdateSQL.Replace('\', '/')
        & $MySQLExe.FullName -u root --default-character-set=utf8mb4 -e "source $MySQLPath" $DBName
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Error running database migration (Exit Code: $LASTEXITCODE)." -ForegroundColor Red
        } else {
            Write-Host "Database updated successfully." -ForegroundColor Green
        }
        $ErrorActionPreference = $oldErrorAction
    } else {
        Write-Host "MySQL engine not found. Skipping DB migration." -ForegroundColor Red
    }
} else {
    Write-Host "No SQL migration file found." -ForegroundColor Gray
}
Write-Host ""

# 6. Version Update & Cleanup
Write-Host "[ Step 4 ] Finalizing and Cleanup..." -ForegroundColor Yellow

# Try to update version in DB if possible
if ($MySQLExe) {
    $NewVer = "1.2.0"
    if (Test-Path "$UpdateFolder\version.txt") {
        $NewVer = (Get-Content "$UpdateFolder\version.txt" -Raw).Trim()
    }
    & $MySQLExe.FullName -u root -e "UPDATE settings SET current_version = '$NewVer' WHERE id = 1;" $DBName
    Write-Host "System version updated to: $NewVer" -ForegroundColor Cyan
}

# Cleanup Cache
$CacheDir = Join-Path $CurrentDir "temp_cache"
if (Test-Path $CacheDir) {
    Remove-Item -Path "$CacheDir\*" -Recurse -Force
    Write-Host "Cache cleared." -ForegroundColor Gray
}

Write-Host ""
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "        Update Completed Successfully!              " -ForegroundColor White -BackgroundColor DarkGreen
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host ""
pause
