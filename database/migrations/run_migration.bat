@echo off
REM Script to run database migration
REM This will add seksi_id column to program table

echo ========================================
echo Database Migration: Add seksi_id to program
echo ========================================
echo.

REM Check if .env file exists
if not exist "..\config\.env" (
    echo ERROR: .env file not found!
    echo Please make sure .env file exists in config directory
    pause
    exit /b 1
)

REM Read database credentials from .env
for /f "tokens=1,2 delims==" %%a in ('type "..\config\.env" ^| findstr /r "^DB_"') do (
    set %%a=%%b
)

echo Database: %DB_NAME%
echo Host: %DB_HOST%
echo User: %DB_USER%
echo.
echo Running migration...
echo.

REM Run the migration
mysql -h %DB_HOST% -u %DB_USER% -p%DB_PASS% %DB_NAME% < add_seksi_id_to_program.sql

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo Migration completed successfully!
    echo ========================================
    echo.
    echo Next steps:
    echo 1. Update existing program records to assign seksi_id
    echo 2. Update Program model and controller
    echo 3. Update Program forms to include seksi selection
    echo.
) else (
    echo.
    echo ========================================
    echo Migration failed!
    echo ========================================
    echo Please check the error message above
    echo.
)

pause
