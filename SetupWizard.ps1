# -*- coding: utf-8 -*-
<#
    .SYNOPSIS
    Peshmerga System Setup Wizard - Smart Setup
#>

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "   Welcome to Peshmerga System Setup Wizard   " -ForegroundColor White -BackgroundColor Blue
Write-Host "====================================================" -ForegroundColor Cyan

# 1. Drive Selection & Dynamic Detection
$InstallRoot = ""
# If we are already running inside laragon/www folder, auto-detect the drive and path
if ($PSScriptRoot -match "(?i)([A-Z]):\\laragon\\www\\") {
    $DetectedDrive = $Matches[1]
    $InstallRoot = "$($DetectedDrive):\Laragon"
    Write-Host "Auto-detected Laragon installation at: $InstallRoot" -ForegroundColor Green
} else {
    # Check common drives for existing Laragon installation
    $CommonDrives = @("C", "D", "E", "F")
    $FoundDrives = @()
    foreach ($d in $CommonDrives) {
        if (Test-Path "$($d):\Laragon\laragon.exe") {
            $FoundDrives += $d
        }
    }
    
    if ($FoundDrives.Count -gt 0) {
        $DefaultDrive = $FoundDrives[0]
        Write-Host "Found existing Laragon on drive(s): $($FoundDrives -join ", ")" -ForegroundColor Gray
        $DriveChoice = Read-Host "Select Drive where Laragon is installed [Default: $DefaultDrive]"
        $DriveChoice = $DriveChoice.ToUpper().Replace(":", "").Trim()
        if ([string]::IsNullOrEmpty($DriveChoice)) {
            $DriveChoice = $DefaultDrive
        }
        $InstallRoot = "$($DriveChoice):\Laragon"
    } else {
        $AllowedDrives = @("C", "D", "E", "F")
        Write-Host "Available Drives: $($AllowedDrives -join ", ")" -ForegroundColor Gray
        $DriveChoice = Read-Host "Select Drive where Laragon is (or will be) installed [Default: C]"
        $DriveChoice = $DriveChoice.ToUpper().Replace(":", "").Trim()
        if ([string]::IsNullOrEmpty($DriveChoice)) {
            $DriveChoice = "C"
        }
        if (!($AllowedDrives -contains $DriveChoice)) {
            Write-Host "Error: Invalid Drive." -ForegroundColor Red
            pause ; exit
        }
        $InstallRoot = "$($DriveChoice):\Laragon"
    }
}

$ProjectFolderName = Split-Path $PSScriptRoot -Leaf
$ProjectRoot = "$InstallRoot\www\$ProjectFolderName"
$SetupFiles = Join-Path $PSScriptRoot "setup_files"

# 2. Server Installation (Laragon) - SKIP IF EXISTS
if (Test-Path "$InstallRoot\laragon.exe") {
    Write-Host "Laragon is already installed at $InstallRoot. Skipping installation..." -ForegroundColor Green
} else {
    $LaragonPath = Join-Path $SetupFiles "laragon-wamp.exe"
    if (Test-Path $LaragonPath) {
        Write-Host "Installing Laragon..." -ForegroundColor Yellow
        try { Start-Process -FilePath $LaragonPath -Wait } catch { }
    } else {
        Write-Host "Laragon installer not found and Laragon not installed. Please install it manually." -ForegroundColor Red
    }
}

# 3. Scanner Installation - ASK USER
$ScannerChoice = Read-Host "Do you want to install/run Scanner setup? (Y/N)"
if ($ScannerChoice.ToUpper() -eq "Y") {
    $ScannerPath = Join-Path $SetupFiles "scan-setup.exe"
    if (Test-Path $ScannerPath) {
        Write-Host "Installing Scanner..." -ForegroundColor Yellow
        try { Start-Process -FilePath $ScannerPath -Wait } catch { }
    } else {
        Write-Host "Scanner installer (scan-setup.exe) not found." -ForegroundColor Red
    }
} else {
    Write-Host "Skipping Scanner setup..." -ForegroundColor Gray
}

# 4. Project File Mapping
$SourceFullPath = (Resolve-Path $PSScriptRoot).Path
$DestFullPath = $ProjectRoot
if (Test-Path $ProjectRoot) {
    $DestFullPath = (Resolve-Path $ProjectRoot).Path
}

if ($SourceFullPath -eq $DestFullPath) {
    Write-Host "Project files are already located in the destination folder ($ProjectRoot)." -ForegroundColor Green
    Write-Host "Skipping copy step to avoid overwriting or infinite loops." -ForegroundColor Green
} else {
    if (!(Test-Path $ProjectRoot)) { New-Item -Path $ProjectRoot -ItemType Directory -Force | Out-Null }
    Write-Host "Mapping Project Files to $ProjectRoot ..." -ForegroundColor Green
    try {
        Copy-Item -Path "$PSScriptRoot\*" -Destination $ProjectRoot -Recurse -Exclude "setup_files", "SetupWizard.ps1", "Setup.bat", "updater.ps1", "updater.bat", "backups" -Force
        Write-Host "Project files mapped successfully." -ForegroundColor Green
    } catch {
        Write-Host "Error copying files: $_" -ForegroundColor Red
    }
}

# 5. Database Automation
$MySQLExe = Get-ChildItem -Path "$InstallRoot\bin\mysql\mysql-*\bin\mysql.exe", "$InstallRoot\bin\mariadb\mariadb-*\bin\mysql.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
if ($MySQLExe) {
    Write-Host "Setting up Database..." -ForegroundColor Green
    try {
        # Get DB Name from config.php if possible
        $ConfigPath = Join-Path $PSScriptRoot "config.php"
        if (!(Test-Path $ConfigPath)) {
            $ConfigPath = Join-Path $ProjectRoot "config.php"
        }
        $DBName = "pirxdry_db" # Default fallback
        if (Test-Path $ConfigPath) {
            $ConfigContent = Get-Content $ConfigPath
            if ($ConfigContent -match "define\('DB_NAME',\s*'(.+?)'\)") {
                $DBName = $Matches[1]
            }
        }

        Write-Host "Using Database: $DBName" -ForegroundColor Cyan
        & $MySQLExe.FullName -u root -e "CREATE DATABASE IF NOT EXISTS $DBName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

        $SQLFiles = @("database.sql", "staff_setup.sql", "database_setup.sql")
        foreach ($File in $SQLFiles) {
            $Path = Join-Path $ProjectRoot $File
            if (!(Test-Path $Path)) {
                $Path = Join-Path $PSScriptRoot $File
            }
            if (Test-Path $Path) {
                Write-Host "Importing $File ..." -ForegroundColor Gray
                try {
                    $MySQLPath = $Path.Replace('\', '/')
                    & $MySQLExe.FullName -u root --default-character-set=utf8mb4 -e "source $MySQLPath" $DBName
                } catch {
                    Write-Host "Warning: Could not import $File directly: $_" -ForegroundColor Yellow
                }
            }
        }
        
        Write-Host "Applying Final Database Fixes..." -ForegroundColor Yellow
        $FixPath = Join-Path $ProjectRoot "fix_database_final.php"
        if (!(Test-Path $FixPath)) {
            $FixPath = Join-Path $PSScriptRoot "fix_database_final.php"
        }
        $PHPExe = Get-ChildItem -Path "$InstallRoot\bin\php\php-*\php.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($PHPExe -and (Test-Path $FixPath)) {
            & $PHPExe.FullName $FixPath
        }

    } catch { Write-Host "Error in Database Setup: $_" -ForegroundColor Red }
}

# 6. Shortcut Creation
try {
    $DesktopPath = [Environment]::GetFolderPath("Desktop")
    $ShortcutPath = Join-Path $DesktopPath "Library & Staff System ($ProjectFolderName).url"
    
    $IconPath = "$ProjectRoot\assets\logo.ico"
    $Content = "[InternetShortcut]`r`nURL=http://localhost/$ProjectFolderName`r`nIconIndex=0`r`nIconFile=$IconPath"
    $Content | Out-File -FilePath $ShortcutPath -Encoding ascii
    Write-Host "Desktop Shortcut Created" -ForegroundColor Green
} catch {
    Write-Host "Error creating shortcut"
}

Write-Host "Setup Completed Successfully!" -ForegroundColor Cyan
Start-Process "http://localhost/$ProjectFolderName/index.php"
pause