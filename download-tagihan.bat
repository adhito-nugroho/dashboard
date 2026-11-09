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
set "FILE_BBM=Tagihan_BBM_Maret-Oktober_%TAHUN%.xlsx"
set "FILE_PERJADIN=Perjalanan_Dinas_Maret-Oktober_%TAHUN%.xlsx"

:: Cari ssh/scp absolut: PATH cmd kadang tidak memuat OpenSSH.
set "SSH_EXE="
set "SCP_EXE="
for %%P in (
    "C:\Windows\System32\OpenSSH\ssh.exe"
    "C:\Program Files\OpenSSH\ssh.exe"
    "C:\Program Files\Git\usr\bin\ssh.exe"
) do (
    if not defined SSH_EXE if exist "%%~P" set "SSH_EXE=%%~P"
)
for %%P in (
    "C:\Windows\System32\OpenSSH\scp.exe"
    "C:\Program Files\OpenSSH\scp.exe"
    "C:\Program Files\Git\usr\bin\scp.exe"
) do (
    if not defined SCP_EXE if exist "%%~P" set "SCP_EXE=%%~P"
)
if not defined SSH_EXE (
    for /f "delims=" %%P in ('where ssh 2^>nul') do if not defined SSH_EXE set "SSH_EXE=%%P"
)
if not defined SCP_EXE (
    for /f "delims=" %%P in ('where scp 2^>nul') do if not defined SCP_EXE set "SCP_EXE=%%P"
)
if not defined SSH_EXE (
    echo ssh.exe tidak ditemukan. Install OpenSSH Client atau sesuaikan path di batch ini.
    pause
    exit /b 1
)
if not defined SCP_EXE (
    echo scp.exe tidak ditemukan. Install OpenSSH Client atau sesuaikan path di batch ini.
    pause
    exit /b 1
)

cd /d "%~dp0"

if not exist "%LOCAL_DIR%" mkdir "%LOCAL_DIR%"

echo ======================================================
echo EXPORT TAGIHAN LISTRIK, AIR, BBM ^& PERJALANAN DINAS - MARET S.D. OKTOBER
echo ======================================================
echo.

echo [1/2] Membuat file Excel di server...
echo *(Jika diminta password SSH, masukkan password akun server)*
"%SSH_EXE%" -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && %REMOTE_PHP% tools\export_tagihan_listrik_air.php --tahun=%TAHUN% --dari=%DARI% --sampai=%SAMPAI%"
if errorlevel 1 (
    echo.
    echo Gagal membuat file Excel di server.
    echo Jika muncul "Connection refused" ke 127.0.0.1:2222:
    echo   - Jalankan batch ini dari LAPTOP, bukan dari dalam sesi SSH server.
    echo   - Pastikan Cloudflare SSH tunnel aktif di laptop.
    echo Selain itu, pastikan script sudah di-deploy dan data rekening listrik/air/BBM/perjalanan dinas tersedia.
    pause
    exit /b 1
)

echo.
echo [2/2] Mengunduh file ke %LOCAL_DIR% ...
"%SCP_EXE%" -P %SERVER_PORT% %SERVER_USER%@%SERVER_IP%:"C:/laragon/www/dashboard/storage/exports/%FILE_LISTRIK%" %SERVER_USER%@%SERVER_IP%:"C:/laragon/www/dashboard/storage/exports/%FILE_AIR%" %SERVER_USER%@%SERVER_IP%:"C:/laragon/www/dashboard/storage/exports/%FILE_BBM%" %SERVER_USER%@%SERVER_IP%:"C:/laragon/www/dashboard/storage/exports/%FILE_PERJADIN%" "%LOCAL_DIR%"
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
echo - %FILE_BBM%
echo - %FILE_PERJADIN%
echo ======================================================
echo.

explorer "%LOCAL_DIR%"
pause
