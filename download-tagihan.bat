@echo off
setlocal

:: =======================================================
:: KONFIGURASI SERVER (sama dengan deploy-git.bat)
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set "REMOTE_DIR=C:\laragon\www\dashboard"
set "REMOTE_PHP=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"
set "REMOTE_EXPORT=%REMOTE_DIR%\storage\exports"
set "LOCAL_DIR=%USERPROFILE%\Downloads"

:: Default: Maret s.d. Oktober 2026
set TAHUN=2026
set DARI=3
set SAMPAI=10
set "FILE_LISTRIK=Tagihan_Listrik_Maret-Oktober_%TAHUN%.xlsx"
set "FILE_AIR=Tagihan_Air_Maret-Oktober_%TAHUN%.xlsx"

cd /d "%~dp0"

echo ======================================================
echo EXPORT TAGIHAN LISTRIK ^& AIR - MARET S.D. OKTOBER
echo ======================================================
echo.

echo [1/2] Membuat file Excel di server...
echo *(Jika diminta password SSH, masukkan password akun server)*
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && %REMOTE_PHP% tools\export_tagihan_listrik_air.php --tahun=%TAHUN% --dari=%DARI% --sampai=%SAMPAI%"
if errorlevel 1 (
    echo.
    echo Gagal membuat file Excel di server.
    echo Pastikan script sudah di-deploy dan data rekening listrik/air tersedia.
    pause
    exit /b 1
)

echo.
echo [2/2] Mengunduh file ke %LOCAL_DIR% ...
scp -P %SERVER_PORT% %SERVER_USER%@%SERVER_IP%:"C:/laragon/www/dashboard/storage/exports/%FILE_LISTRIK%" %SERVER_USER%@%SERVER_IP%:"C:/laragon/www/dashboard/storage/exports/%FILE_AIR%" "%LOCAL_DIR%\"
if errorlevel 1 (
    echo.
    echo Download gagal. Periksa koneksi/password SSH dan nama file di server.
    pause
    exit /b 1
)

echo.
echo ======================================================
echo SELESAI!
echo File ada di: %LOCAL_DIR%
echo - %FILE_LISTRIK%
echo - %FILE_AIR%
echo ======================================================
echo.

explorer "%LOCAL_DIR%"
pause
