@echo off
rem Saves a copy of the WFP database to the backups folder (wfp_YYYYMMDD_HHMMSS.sql).
call "%~dp0start.bat" backup
pause
