@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "PORT=4173"
set "URL=http://127.0.0.1:%PORT%/"

echo Starting local environment in:
echo %CD%
echo.

for /f "tokens=5" %%p in ('netstat -ano ^| findstr /r /c:":%PORT% .*LISTENING"') do (
  echo Stopping existing process on port %PORT%: %%p
  taskkill /PID %%p /F >nul 2>nul
)

where docker >nul 2>nul
if %ERRORLEVEL% EQU 0 (
  echo Starting PostgreSQL in Docker...
  docker compose up -d postgres
) else (
  echo Docker not found, skipping PostgreSQL startup.
)

if not exist "config\db.php" (
  if exist "config\db.example.php" (
    copy /Y "config\db.example.php" "config\db.php" >nul
    echo Created config\db.php from config\db.example.php
  ) else (
    echo config\db.example.php not found, skipping DB config bootstrap.
  )
)

start "" "%URL%"

where php >nul 2>nul
if %ERRORLEVEL% EQU 0 (
  if exist "scripts\migrate-content-to-postgres.php" (
    if exist "config\db.php" (
      echo Running content migration check...
      php scripts\migrate-content-to-postgres.php >nul 2>nul
    )
  )
  echo Starting PHP dev server: %URL%
  php -S 127.0.0.1:%PORT% -t .
  goto :eof
)

echo PHP not found. Falling back to Node static dev server.
echo Starting static dev server: %URL%
node scripts\dev-server.mjs

