@echo off
setlocal EnableExtensions
title R1MC WFP server
cd /d "%~dp0"

rem ==========================================================================
rem  R1MC Work and Financial Plan - starts the system on this PC for the LAN.
rem    start.bat          check the database, then run the web server
rem    start.bat backup   save a copy of the database to the backups folder
rem  Settings for this PC go in start.local.bat (copy start.local.bat.example).
rem ==========================================================================

if exist "start.local.bat" call "start.local.bat"
if not defined WFP_PORT set "WFP_PORT=86"
if not defined XAMPP_DIR set "XAMPP_DIR=C:\xampp"

rem ---- find PHP: PHP_BIN, then PATH, then XAMPP, then C:\php -------------------
set "PHP="
if defined PHP_BIN if exist "%PHP_BIN%" set "PHP=%PHP_BIN%"
if not defined PHP for /f "delims=" %%p in ('where php 2^>nul') do if not defined PHP set "PHP=%%p"
if not defined PHP if exist "%XAMPP_DIR%\php\php.exe" set "PHP=%XAMPP_DIR%\php\php.exe"
if not defined PHP if exist "C:\php\php.exe" set "PHP=C:\php\php.exe"
if not defined PHP goto no_php

"%PHP%" -r "exit(version_compare(PHP_VERSION, '8.1.0', '<') ? 1 : 0);"
if errorlevel 1 goto old_php
"%PHP%" -r "exit(extension_loaded('pdo_mysql') ? 0 : 1);"
if errorlevel 1 goto no_pdo

if /i "%~1"=="backup" goto backup

if not exist "frontend\dist\index.html" goto no_dist

rem ---- is the port free? -------------------------------------------------------
set "BUSY_PID="
for /f "tokens=5" %%a in ('netstat -ano ^| findstr /r /c:":%WFP_PORT% .*LISTENING"') do if not defined BUSY_PID set "BUSY_PID=%%a"
if defined BUSY_PID goto port_busy

rem ---- database -----------------------------------------------------------------
set "TRIES=0"
:db_check
"%PHP%" backend\tools\install.php
set "RC=%ERRORLEVEL%"
if "%RC%"=="0" goto serve
if "%RC%"=="3" goto db_old
if "%RC%"=="4" goto fail
if not "%RC%"=="2" goto fail
rem MySQL is not running: start XAMPP's MySQL once, then wait up to 30 seconds
if not "%TRIES%"=="0" goto db_wait
call :start_mysql
if errorlevel 1 goto no_mysql
:db_wait
set /a TRIES+=1
if %TRIES% gtr 30 goto no_mysql
timeout /t 1 /nobreak >nul
goto db_check

:start_mysql
if not exist "%XAMPP_DIR%\mysql\bin\mysqld.exe" exit /b 1
echo   Starting MySQL from %XAMPP_DIR%\mysql ...
start "WFP MySQL - keep this window open" /min "%XAMPP_DIR%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP_DIR%\mysql\bin\my.ini" --standalone
exit /b 0

:db_old
echo.
echo   Replacing it deletes everything in that old database.
set "ANSWER="
set /p "ANSWER=  Type YES to replace it, or just press Enter to stop: "
if /i not "%ANSWER%"=="YES" goto stop
"%PHP%" backend\tools\install.php --replace
if errorlevel 1 goto fail
goto serve

rem ---- run -----------------------------------------------------------------------
:serve
echo.
echo   ============================================================
echo    R1MC Work and Financial Plan is running.
echo    On this PC:      http://localhost:%WFP_PORT%/
echo    On other PCs:
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do for /f "tokens=*" %%b in ("%%a") do echo                     http://%%b:%WFP_PORT%/
echo.
echo    Keep this window open. Close it to stop the system.
echo    Other PCs cannot connect? Run allow-lan.bat once as administrator.
echo   ============================================================
echo.
start "" "http://localhost:%WFP_PORT%/"
"%PHP%" -S 0.0.0.0:%WFP_PORT% -t frontend\dist server.php
goto stop

:backup
"%PHP%" backend\tools\backup.php
if errorlevel 1 goto fail
goto stop

rem ---- problems ------------------------------------------------------------------
:no_php
echo.
echo   [ERROR] PHP was not found.
echo   Install XAMPP to %XAMPP_DIR%, or set PHP_BIN in start.local.bat, for example:
echo       set "PHP_BIN=D:\xampp\php\php.exe"
goto fail

:old_php
echo.
echo   [ERROR] %PHP% is older than PHP 8.1. Use the PHP of a recent XAMPP (set PHP_BIN in start.local.bat).
goto fail

:no_pdo
echo.
echo   [ERROR] %PHP% cannot talk to MySQL: the pdo_mysql extension is off.
echo   In the php.ini next to php.exe, remove the ; in front of  extension=pdo_mysql
goto fail

:no_dist
echo.
echo   [ERROR] frontend\dist is missing, so there are no pages to serve.
echo   Use the delivered zip (it includes frontend\dist), or build it: npm install, then npm run build
goto fail

:port_busy
set "BUSY_NAME=unknown program"
for /f "tokens=1 delims=," %%n in ('tasklist /fi "PID eq %BUSY_PID%" /fo csv /nh') do set "BUSY_NAME=%%~n"
echo.
echo   [ERROR] Port %WFP_PORT% is already used by %BUSY_NAME% (PID %BUSY_PID%).
echo   If it is an older WFP window, close it. Otherwise pick another port in start.local.bat:
echo       set "WFP_PORT=8086"
goto fail

:no_mysql
echo.
echo   [ERROR] MySQL is not running and could not be started.
echo   Open the XAMPP Control Panel and click Start beside MySQL, then run start.bat again.
goto fail

:fail
echo.
pause
exit /b 1

:stop
endlocal
exit /b 0
