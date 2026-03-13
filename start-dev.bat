@echo off
setlocal

cd /d "%~dp0"

set "PORT=4173"
set "URL=http://127.0.0.1:%PORT%/"

echo Starting local server in:
echo %CD%
echo.
echo Open: %URL%
echo Press Ctrl+C to stop the server.
echo.

for /f "tokens=5" %%p in ('netstat -ano ^| findstr /r /c:":%PORT% .*LISTENING"') do (
  echo Stopping existing process on port %PORT%: %%p
  taskkill /PID %%p /F >nul 2>nul
)

start "" "%URL%"
node scripts\dev-server.mjs
