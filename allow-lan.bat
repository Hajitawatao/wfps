@echo off
rem Run once as administrator (right-click > Run as administrator) so other PCs on the LAN can open the system.
setlocal
cd /d "%~dp0"
if exist "start.local.bat" call "start.local.bat"
if not defined WFP_PORT set "WFP_PORT=86"
netsh advfirewall firewall delete rule name="R1MC WFP" >nul 2>&1
netsh advfirewall firewall add rule name="R1MC WFP" dir=in action=allow protocol=TCP localport=%WFP_PORT% profile=private,domain
if errorlevel 1 (echo. & echo   Run this file as administrator: right-click, Run as administrator.) else (echo. & echo   Done. Other PCs can now open the system on port %WFP_PORT%.)
pause
