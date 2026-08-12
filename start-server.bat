@echo off
REM Start PHP Built-in Server for Dashboard Anggaran
REM Document root = public/ so CSS, JS, and images load correctly.

set PHP_PATH=D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe

if not exist "%PHP_PATH%" (
    echo PHP not found at: %PHP_PATH%
    echo Update PHP_PATH in this file to your Laragon PHP path.
    pause
    exit /b 1
)

echo Starting PHP Built-in Server (document root: public/)...
echo.
echo Open: http://localhost:8000
echo.
echo Press Ctrl+C to stop.
echo.

cd /d "%~dp0"
"%PHP_PATH%" -S localhost:8000 -t public public/router.php

