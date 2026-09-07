@echo off
setlocal enabledelayedexpansion
REM ===========================================================================
REM  DivingClub-Manager - local Windows setup (PostgreSQL)
REM ---------------------------------------------------------------------------
REM  Usage:
REM     scripts\setup-local.bat                 (asks for a backup path)
REM     scripts\setup-local.bat C:\path\backup.zip
REM
REM  What it does:
REM     1. composer install / npm install / npm run build
REM     2. creates .env (key:generate) and writes the PostgreSQL DB settings
REM     3. creates the Postgres database + user
REM     4. if a Spatie backup .zip is given: restores the DB dump and files
REM     5. runs migrations, storage:link and clears caches
REM
REM  Prerequisites: PHP 8.3+, Composer, Node/npm, PostgreSQL (psql on PATH).
REM  Edit the DB_* values below if you want different credentials.
REM ===========================================================================

set "DB_NAME=divingclub"
set "DB_USER=divingclub"
set "DB_PASS=secret"
set "DB_HOST=127.0.0.1"
set "DB_PORT=5432"

REM --- move to the project root (this script lives in \scripts) ---
cd /d "%~dp0.."
if not exist "composer.json" (
    echo [ERROR] composer.json not found. Run this from the project's scripts\ folder.
    goto :end
)
echo Project root: %CD%
echo.

REM --- prerequisite checks ---
call :need php
call :need composer
call :need npm
call :need psql
if defined MISSING (
    echo [ERROR] Missing required tool(s): %MISSING%
    echo Install them and re-run.
    goto :end
)

REM --- backup path (argument or prompt; blank = skip restore) ---
set "BACKUP=%~1"
if "%BACKUP%"=="" (
    set /p "BACKUP=Path to backup .zip (leave blank to skip restore): "
)

echo.
echo === [1/6] Installing PHP dependencies ===
call composer install --no-interaction || goto :fail

echo.
echo === [2/6] Installing and building front-end assets ===
call npm install || goto :fail
call npm run build || goto :fail

echo.
echo === [3/6] Preparing .env ===
if not exist ".env" (
    copy /Y ".env.example" ".env" >nul
    call php artisan key:generate --ansi || goto :fail
) else (
    echo .env already exists - leaving APP_KEY untouched.
)
REM write / update the DB_* keys in .env (uncomments them if commented)
powershell -NoProfile -ExecutionPolicy Bypass -Command "$p='.env'; $lines=@('DB_CONNECTION=pgsql','DB_HOST=%DB_HOST%','DB_PORT=%DB_PORT%','DB_DATABASE=%DB_NAME%','DB_USERNAME=%DB_USER%','DB_PASSWORD=%DB_PASS%'); $c=Get-Content $p -Raw; foreach($l in $lines){ $k=($l -split '=',2)[0]; $rx='(?m)^#?\s*'+$k+'=.*$'; if($c -match $rx){ $c=[regex]::Replace($c,$rx,$l) } else { $c=$c.TrimEnd()+[Environment]::NewLine+$l } }; Set-Content -NoNewline -Path $p -Value $c"
echo .env DB settings written (%DB_CONNECTION% %DB_NAME%).

echo.
echo === [4/6] Creating PostgreSQL database and user ===
echo (you'll be prompted for the 'postgres' superuser password)
psql -U postgres -c "CREATE DATABASE %DB_NAME%;" -c "CREATE USER %DB_USER% WITH PASSWORD '%DB_PASS%';" -c "GRANT ALL PRIVILEGES ON DATABASE %DB_NAME% TO %DB_USER%;"
psql -U postgres -d %DB_NAME% -c "GRANT ALL ON SCHEMA public TO %DB_USER%;"
echo (errors above about "already exists" are safe to ignore)

echo.
echo === [5/6] Restoring backup ===
if "%BACKUP%"=="" (
    echo No backup given - skipping restore.
) else (
    if not exist "%BACKUP%" (
        echo [ERROR] Backup file not found: %BACKUP%
        goto :fail
    )
    set "RDIR=%TEMP%\dcms_restore"
    if exist "!RDIR!" rmdir /S /Q "!RDIR!"
    echo Extracting %BACKUP% ...
    powershell -NoProfile -Command "Expand-Archive -Force -Path '%BACKUP%' -DestinationPath '!RDIR!'" || goto :fail
    set "DUMP="
    for /r "!RDIR!\db-dumps" %%F in (*.sql) do set "DUMP=%%F"
    if not defined DUMP (
        echo [ERROR] No .sql dump found under db-dumps\ in the backup.
        goto :fail
    )
    echo Importing dump: !DUMP!
    set "PGPASSWORD=%DB_PASS%"
    psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d %DB_NAME% -f "!DUMP!" || goto :fail
    if exist "!RDIR!\public"  xcopy /E /Y /I "!RDIR!\public"  "storage\app\public"  >nul
    if exist "!RDIR!\private" xcopy /E /Y /I "!RDIR!\private" "storage\app\private" >nul
    echo Restore complete.
)

echo.
echo === [6/6] Finalising ===
call php artisan optimize:clear
call php artisan migrate --force
call php artisan storage:link 2>nul
call php artisan cache:clear
echo.
echo ============================================================
echo  Done. Start the app with:  php artisan serve
echo  Then open http://localhost:8000
echo ============================================================
goto :end

:need
where %1 >nul 2>nul
if errorlevel 1 set "MISSING=!MISSING! %1"
goto :eof

:fail
echo.
echo [FAILED] A step above returned an error. Fix it and re-run.

:end
echo.
pause
endlocal
