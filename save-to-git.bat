@echo off
setlocal EnableExtensions
rem Saves your changes in this folder to GitHub (commit + push).
rem Needs Git for Windows: https://git-scm.com/download/win
cd /d "%~dp0"
set "BRANCH=main"

where git >nul 2>nul
if errorlevel 1 (
  echo Git is not installed. Install Git for Windows from https://git-scm.com/download/win and run this again.
  pause
  exit /b 1
)

if not exist ".git" (
  echo Setting up Git in this folder...
  git init -b %BRANCH% >nul
)

git config user.email >nul 2>nul || git config user.email "meliodas2327@gmail.com"
git config user.name  >nul 2>nul || git config user.name  "meliodas2327"

git remote get-url origin >nul 2>nul
if errorlevel 1 (
  set "REPO_URL="
  set /p "REPO_URL=GitHub repository address (for example https://github.com/Hajitawatao/wfps.git): "
  call git remote add origin "%%REPO_URL%%"
)

git ls-remote --exit-code --heads origin %BRANCH% >nul 2>nul
set "REMOTE_STATE=%errorlevel%"
if "%REMOTE_STATE%"=="2" goto :stage
if not "%REMOTE_STATE%"=="0" (
  echo Cannot reach the GitHub repository. Check the internet connection and the address, then try again.
  pause
  exit /b 1
)

rem The branch exists on GitHub: take its history first, keeping the files in this folder.
git rev-parse --verify HEAD >nul 2>nul
if errorlevel 1 (
  git fetch origin %BRANCH% || goto :fail
  git reset --mixed FETCH_HEAD >nul || goto :fail
  git branch -u origin/%BRANCH% >nul 2>nul
)
git pull --rebase --autostash origin %BRANCH% || goto :fail

:stage
git add -A
git diff --cached --quiet
if errorlevel 1 goto :commit
rem nothing staged: push only if there are commits GitHub does not have yet
set "AHEAD=1"
for /f %%n in ('git rev-list --count origin/%BRANCH%..HEAD 2^>nul') do set "AHEAD=%%n"
if not "%AHEAD%"=="0" goto :push
echo Nothing new to save.
pause
exit /b 0

:commit
set "MSG="
set /p "MSG=What did you change? (press Enter for a dated message): "
if not defined MSG for /f "usebackq delims=" %%d in (`powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd HH:mm'"`) do set "MSG=Update %%d"
git commit -m "%MSG%" || goto :fail

:push
git push -u origin %BRANCH% || goto :fail
echo Saved to GitHub.
pause
exit /b 0

:fail
echo Something went wrong. Read the message above, fix it, and run this again.
pause
exit /b 1
