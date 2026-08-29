@echo off
echo ========================================
echo Setup User untuk Dashboard Anggaran
echo ========================================
echo.

REM Minta input password
set /p DB_PASS="Masukkan password database MySQL (default: kosong): "
if "%DB_PASS%"=="" set DB_PASS=

REM Database connection
set DB_HOST=localhost
set DB_PORT=3306
set DB_NAME=db_anggaran
set DB_USER=root

echo.
echo Membuat tabel users dan menambahkan data user...
echo.

REM Path ke mysql di Laragon
set MYSQL_PATH=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe

REM Cek apakah mysql.exe ada
if not exist "%MYSQL_PATH%" (
    echo Error: MySQL tidak ditemukan di %MYSQL_PATH%
    echo Silakan sesuaikan MYSQL_PATH di file ini
    pause
    exit /b 1
)

REM Jalankan SQL
"%MYSQL_PATH%" -h%DB_HOST% -P%DB_PORT% -u%DB_USER% -p%DB_PASS% %DB_NAME% < create_users_and_insert.sql

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo Setup berhasil!
    echo ========================================
    echo.
    echo User yang berhasil dibuat:
    echo - Username: admin   ^| Password: admin123 ^| Role: Admin
    echo - Username: tu      ^| Password: admin123 ^| Role: TU
    echo - Username: rlpm    ^| Password: admin123 ^| Role: RLPM
    echo - Username: tkuk    ^| Password: admin123 ^| Role: TKUK
    echo.
    echo Silakan login dengan salah satu user di atas.
    echo ========================================
) else (
    echo.
    echo Error: Gagal menjalankan SQL. Periksa koneksi database.
)

echo.
pause
