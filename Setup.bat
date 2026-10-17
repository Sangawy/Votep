@echo off
title Peshmerga System Setup Wizard
echo Launching Setup Wizard...
powershell -ExecutionPolicy Bypass -File "%~dp0SetupWizard.ps1"
pause
