@echo off
setlocal

:: =======================================================
:: KONFIGURASI SERVER (sama dengan deploy-git.bat)
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set "REMOTE_DIR=C:\laragon\www\dashboard"
set "REMOTE_STORAGE=%REMOTE_DIR%\storage"
set "LOCAL_JSON=storage\rekening_pegawai.json"

cd /d "%~dp0"

echo ======================================================
echo UPLOAD REKENING PEGAWAI - CSV BANK JATIM
echo ======================================================
echo.

:: 1. Regenerate dari TPP (opsional)
if exist "%LOCAL_JSON%" (
    set /p REGEN="Regenerate %LOCAL_JSON% dari TPP sebelum upload? (y/n): "
) else (
    set "REGEN=y"
)

if /i "%REGEN%"=="y" (
    echo.
    echo [1/3] Membuat ulang rekening dari database TPP...
    php tools\export_rekening_tpp.php
    if errorlevel 1 (
        echo.
        echo Gagal membuat rekening_pegawai.json.
        echo Pastikan DB TPP ada di path default, atau jalankan manual:
        echo   php tools\export_rekening_tpp.php "D:\path\ke\sigap_tpp.db"
        pause
        exit /b 1
    )
) else (
    echo.
    echo [1/3] Memakai file yang sudah ada: %LOCAL_JSON%
)

if not exist "%LOCAL_JSON%" (
    echo.
    echo File %LOCAL_JSON% tidak ditemukan.
    pause
    exit /b 1
)

echo.
echo [2/3] Memastikan folder storage ada di server...
echo *(Jika diminta password SSH, masukkan password akun server)*
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "if not exist %REMOTE_STORAGE% mkdir %REMOTE_STORAGE%"
if errorlevel 1 (
    echo.
    echo Gagal membuat folder storage di server. Periksa koneksi/password SSH.
    pause
    exit /b 1
)

echo.
echo [3/3] Mengunggah %LOCAL_JSON% ke server...
scp -P %SERVER_PORT% "%LOCAL_JSON%" %SERVER_USER%@%SERVER_IP%:"C:/laragon/www/dashboard/storage/rekening_pegawai.json"
if errorlevel 1 (
    echo.
    echo Upload gagal. Pastikan folder tujuan ada dan password SSH benar.
    pause
    exit /b 1
)

echo.
echo ======================================================
echo UPLOAD SELESAI!
echo File server: %REMOTE_STORAGE%\rekening_pegawai.json
echo ======================================================
echo.
echo Catatan: folder storage harus ter-commit (berisi .gitignore, .gitkeep,
echo .htaccess) agar file JSON tidak terhapus saat deploy-git.bat menjalankan
echo git clean -fd.
echo.
pause
