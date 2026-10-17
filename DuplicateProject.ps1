# -*- coding: utf-8 -*-
<#
    .SYNOPSIS
    Script to Duplicate Project and Database - Fixed
#>

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$SourceDir = $PSScriptRoot
$ParentDir = Split-Path $SourceDir -Parent
$DestDir = Join-Path $ParentDir "pirxdry"

# Dynamically locate InstallRoot (Laragon)
$InstallRoot = ""
if ($SourceDir -match "(?i)([A-Z]):\\laragon\\www\\") {
    $InstallRoot = "$($Matches[1]):\Laragon"
} else {
    foreach ($d in @("C", "D", "E", "F")) {
        if (Test-Path "$($d):\Laragon\laragon.exe") {
            $InstallRoot = "$($d):\Laragon"
            break
        }
    }
}

if ([string]::IsNullOrEmpty($InstallRoot)) {
    Write-Host "Error: Could not locate Laragon installation folder." -ForegroundColor Red
    pause ; exit
}

$MySQLExePath = Get-ChildItem -Path "$InstallRoot\bin\mysql\mysql-*\bin\mysql.exe", "$InstallRoot\bin\mariadb\mariadb-*\bin\mysql.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
$MySQLDumpExePath = Get-ChildItem -Path "$InstallRoot\bin\mysql\mysql-*\bin\mysqldump.exe", "$InstallRoot\bin\mariadb\mariadb-*\bin\mysqldump.exe" -ErrorAction SilentlyContinue | Select-Object -First 1

if (!$MySQLExePath -or !$MySQLDumpExePath) {
    Write-Host "Error: Could not locate mysql.exe or mysqldump.exe in Laragon." -ForegroundColor Red
    pause ; exit
}

$MySQLExe = $MySQLExePath.FullName
$MySQLDumpExe = $MySQLDumpExePath.FullName

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "       Duplicating Project to Pirxdry               " -ForegroundColor White -BackgroundColor Blue
Write-Host "====================================================" -ForegroundColor Cyan

# 1. Copy Files
Write-Host "1. Copying files to $DestDir ..." -ForegroundColor Yellow
try {
    if (Test-Path $DestDir) {
        Remove-Item -Path $DestDir -Recurse -Force
    }
    Copy-Item -Path $SourceDir -Destination $DestDir -Recurse -Force
    Write-Host "Files copied successfully." -ForegroundColor Green
} catch {
    Write-Host "Error copying files." -ForegroundColor Red
}

# 2. Copy Database
Write-Host "2. Copying Database (peshmerga_db -> Pirxdry_db) ..." -ForegroundColor Yellow
try {
    # Create new DB
    & $MySQLExe -u root -e "CREATE DATABASE IF NOT EXISTS Pirxdry_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    # Dump and Import using native tools and source command to avoid UTF8 piping issues
    $TempDump = Join-Path $env:TEMP "temp_db_dump.sql"
    & $MySQLDumpExe -u root peshmerga_db --result-file=$TempDump
    
    $MySQLPath = $TempDump.Replace('\', '/')
    & $MySQLExe -u root --default-character-set=utf8mb4 -e "source $MySQLPath" Pirxdry_db
    
    if (Test-Path $TempDump) { Remove-Item $TempDump }
    Write-Host "Database duplicated successfully." -ForegroundColor Green
} catch {
    Write-Host "Error duplicating database: $_" -ForegroundColor Red
}

# 3. Update Config in new folder
Write-Host "3. Updating config.php ..." -ForegroundColor Yellow
try {
    $ConfigPath = Join-Path $DestDir "config.php"
    if (Test-Path $ConfigPath) {
        $Content = Get-Content $ConfigPath
        $Content = $Content -replace "peshmerga_db", "Pirxdry_db"
        $Content | Set-Content $ConfigPath
        Write-Host "Config file updated to Pirxdry_db." -ForegroundColor Green
    }
} catch {
    Write-Host "Error updating config file." -ForegroundColor Red
}

Write-Host ""
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "        PROJECT DUPLICATED SUCCESSFULLY             " -ForegroundColor White -BackgroundColor DarkGreen
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "URL: http://localhost/pirxdry"
Write-Host ""
pause
