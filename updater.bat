@echo off
title Peshmerga System Updater
echo Initializing update process...
powershell -ExecutionPolicy Bypass -File "%~dp0updater.ps1"
pause
