# Server Setup Guide

## Option 1: PHP Built-in Server (Development)

### Method A: Using the provided scripts (Easiest)

**Windows (PowerShell):**
```powershell
.\start-server.ps1
```

**Windows (Command Prompt):**
```cmd
start-server.bat
```

### Method B: Using Laragon's PHP directly (document root = public/)

Agar CSS dan JS ter-load, jalankan dengan **document root = folder public**:

**PowerShell:**
```powershell
cd D:\laragon\www\dashboard
D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -S localhost:8000 -t public public/router.php
```

**Command Prompt:**
```cmd
cd D:\laragon\www\dashboard
D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -S localhost:8000 -t public public/router.php
```

**Note:** Ganti `php-8.1.10-Win32-vs16-x64` dengan folder versi PHP Anda.

### Method C: Add PHP to PATH (Optional)

Tambahkan PHP Laragon ke PATH sistem, lalu dari folder project:

```bash
php -S localhost:8000 -t public public/router.php
```

Akses:
- `http://localhost:8000/` – Dashboard
- `http://localhost:8000/program` – Program
- `http://localhost:8000/kegiatan` – Kegiatan
- dll.

**Penting:** Jalankan dari folder project; document root harus `public/` agar file CSS/JS diload dengan benar.

## Option 2: Laragon Virtual Host (Recommended for Production)

1. Open Laragon
2. Right-click on the project folder
3. Select "Add to Laragon" or create a virtual host
4. Point the document root to the `public` directory
5. Access via: `http://dashboard-anggaran.test/`

## Option 3: Apache/Nginx Configuration

### Apache
Set DocumentRoot to the `public` directory:
```apache
DocumentRoot "D:/laragon/www/dashboard-anggaran/public"
<Directory "D:/laragon/www/dashboard-anggaran/public">
    AllowOverride All
    Require all granted
</Directory>
```

### Nginx
```nginx
root D:/laragon/www/dashboard-anggaran/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Troubleshooting

### 404 Errors
- **Ensure you're running the server from the correct directory**
  - If using `router.php`, run from project root
  - If not using router, run from `public` directory
- Check that `public/index.php` exists
- Verify `.htaccess` is present (for Apache)
- Check the URL - should be `http://localhost:8000/program` not `http://localhost:8000/public/program`

### Routing Issues
- Make sure all routes start with `/` (e.g., `/program` not `program`)
- Check that the base path is correctly detected
- If running from root with router.php, use: `php -S localhost:8000 router.php`

### Quick Test
1. Stop your current server (Ctrl+C)
2. Navigate to project root: `cd D:\laragon\www\dashboard-anggaran`
3. Run: `php -S localhost:8000 router.php`
4. Access: `http://localhost:8000/program`
