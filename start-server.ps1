# PowerShell script to start PHP Built-in Server
# Uses Laragon's PHP installation

$phpPath = "D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"

# Try to find PHP if default path doesn't exist
if (-not (Test-Path $phpPath)) {
    $phpDirs = Get-ChildItem "D:\laragon\bin\php" -Directory -ErrorAction SilentlyContinue
    if ($phpDirs) {
        $phpPath = Join-Path $phpDirs[0].FullName "php.exe"
    }
}

# Check if PHP exists
if (-not (Test-Path $phpPath)) {
    Write-Host "PHP not found!" -ForegroundColor Red
    Write-Host "Please update the `$phpPath variable in this script." -ForegroundColor Yellow
    Write-Host "Common locations:" -ForegroundColor Yellow
    Write-Host "  D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
    Write-Host "  D:\laragon\bin\php\php-8.2.0-Win32-vs16-x64\php.exe"
    Write-Host "  D:\laragon\bin\php\php-8.3.0-Win32-vs16-x64\php.exe"
    exit 1
}

Write-Host "Starting PHP Built-in Server (document root: public/)..." -ForegroundColor Green
Write-Host ""
Write-Host "Open: http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host ""

Set-Location $PSScriptRoot
& $phpPath -S localhost:8000 -t public public/router.php

