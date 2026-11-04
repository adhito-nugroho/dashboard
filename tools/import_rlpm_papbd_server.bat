@echo off
setlocal enabledelayedexpansion

:: ============================================================
:: JALANKAN DI LAPTOP, BUKAN DI SERVER.
:: Server tidak punya Python. Skrip ini mengekstrak PDF di laptop,
:: meng-upload JSON ke server, lalu menjalankan importer PHP di server.
:: Deploy dulu (deploy-git.bat) supaya tools/import_rka.php + migrasi
:: tagar sudah ada di server.
:: ============================================================

set "SRC_DIR=C:\Users\user\Dropbox\!!!2026\keuangan\RKA PAPBD 2026\RLPM"
set "LOCAL_APP=%~dp0.."
set "LOCAL_JSON=%USERPROFILE%\Downloads\rka_rlpm_2026.json"

set "SERVER_USER=adit"
set "SERVER_IP=127.0.0.1"
set "SERVER_PORT=2222"
set "REMOTE_DIR=C:/laragon/imports"
set "REMOTE_APP=C:\laragon\www\dashboard"
set "REMOTE_PHP=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
set "REMOTE_JSON=C:/laragon/imports/rka_rlpm_2026.json"

cd /d "%LOCAL_APP%"

where python >nul 2>&1
if errorlevel 1 (
    echo Python tidak ditemukan di mesin ini.
    echo Jalankan skrip ini di LAPTOP tempat folder PDF berada, bukan di server.
    goto :err
)
if not exist "%SRC_DIR%" (
    echo Folder PDF tidak ditemukan:
    echo   %SRC_DIR%
    echo Jalankan skrip ini di LAPTOP, bukan di server.
    goto :err
)

echo ======================================================
echo [1/4] Ekstrak PDF RKA PAPBD RLPM
echo ======================================================
python tools\rka_extract.py --dir "%SRC_DIR%" --match ".pdf" --out "%LOCAL_JSON%"
if errorlevel 1 goto :err
echo JSON: %LOCAL_JSON%

echo.
echo ======================================================
echo [2/4] Upload JSON ke server
echo ======================================================
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "if not exist C:\laragon\imports mkdir C:\laragon\imports"
if errorlevel 1 goto :err
scp -P %SERVER_PORT% "%LOCAL_JSON%" %SERVER_USER%@%SERVER_IP%:%REMOTE_DIR%/
if errorlevel 1 goto :err

echo.
echo ======================================================
echo [3/4] DRY-RUN di server (belum mengubah data)
echo ======================================================
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "%REMOTE_PHP% %REMOTE_APP%\tools\import_rka.php %REMOTE_JSON%"
if errorlevel 1 goto :err

echo.
set /p KONFIRMASI="Terapkan perubahan di server? (y/n): "
if /i not "%KONFIRMASI%"=="y" goto :done

echo.
echo ======================================================
echo [4/4] APPLY di server
echo ======================================================
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "%REMOTE_PHP% %REMOTE_APP%\tools\import_rka.php %REMOTE_JSON% --apply"
if errorlevel 1 goto :err

:done
echo.
echo Selesai.
pause
exit /b 0

:err
echo.
echo GAGAL. Periksa pesan di atas.
pause
exit /b 1
