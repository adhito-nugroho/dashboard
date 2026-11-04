@echo off
setlocal enabledelayedexpansion

:: Import RKA PAPBD 2026 (folder RLPM) ke server.
:: Jalankan deploy-git.bat dulu supaya tools/import_rka.php + migrasi tagar
:: sudah ada di server. Skrip ini akan meminta password SSH beberapa kali.

set "SRC_DIR=C:\Users\user\Dropbox\!!!2026\keuangan\RKA PAPBD 2026\RLPM"
set "LOCAL_APP=%~dp0.."
set "LOCAL_JSON=%TEMP%\rka_rlpm_2026.json"

set "SERVER_USER=adit"
set "SERVER_IP=127.0.0.1"
set "SERVER_PORT=2222"
set "REMOTE_DIR=C:/laragon/imports"
set "REMOTE_APP=C:\laragon\www\dashboard"
set "REMOTE_PHP=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
set "REMOTE_JSON=C:/laragon/imports/rka_rlpm_2026.json"

cd /d "%LOCAL_APP%"

echo ======================================================
echo [1/4] Ekstrak PDF RKA PAPBD RLPM
echo ======================================================
python tools\rka_extract.py --dir "%SRC_DIR%" --match ".pdf" --out "%LOCAL_JSON%"
if errorlevel 1 goto :err

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
