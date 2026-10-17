@echo off
title Clear Print Queue
:: Auto-elevate to Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    powershell -Command "Start-Process cmd -ArgumentList '/c \"\"%~f0\"\"' -Verb RunAs"
    exit /b
)

echo ====================================================
echo Clearing Print Queue (Srinaway Printa Wastawakan)...
echo ====================================================

echo.
echo 1. Stopping Print Spooler...
net stop spooler /y
taskkill /f /im printfilterpipelinesvc.exe >nul 2>&1

echo 2. Deleting stuck print files...
del /q /f /s "%systemroot%\System32\Spool\Printers\*.*" >nul 2>&1

echo 3. Restarting Print Spooler...
net start spooler

echo.
echo ====================================================
echo SUCCESS! Hamoo printa wastakan sranawa.
echo ====================================================
echo.
pause
