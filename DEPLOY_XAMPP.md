# Deploy ke Server XAMPP

Panduan singkat saat mengupload folder project ini ke server yang memakai XAMPP (Apache + MySQL + PHP).

---

## 1. Yang **harus** diubah

### a) Konfigurasi database (`config/.env`)

Sesuaikan dengan database di server:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nama_database_anda
DB_USER=username_mysql
DB_PASS=password_mysql
```

- Buat database di phpMyAdmin (MySQL) jika belum.
- Import skema/tabel sesuai `DATABASE_SCHEMA.md` atau backup database Anda.

### b) Document root Apache

Agar hanya folder `public` yang diakses dari web (lebih aman):

**Opsi A – Virtual Host (disarankan)**

1. Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`, tambahkan:

```apache
<VirtualHost *:80>
    ServerName dashboard.local
    DocumentRoot "C:/xampp/htdocs/dashboard/public"
    <Directory "C:/xampp/htdocs/dashboard/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

2. Sesuaikan path `C:/xampp/htdocs/dashboard` dengan lokasi folder project Anda.
3. Restart Apache. Akses: `http://dashboard.local` (atau tambahkan entri di `C:\Windows\System32\drivers\etc\hosts` jika pakai nama domain lokal).

**Opsi B – Tanpa virtual host**

- Upload seluruh project ke `htdocs/dashboard` (atau nama folder lain).
- Akses: `http://localhost/dashboard/public/`
- Tidak perlu ubah kode; `base_url()` sudah mendeteksi path `/dashboard/public/`.

---

## 2. Yang **perlu dicek** di XAMPP

### Apache: mod_rewrite & AllowOverride

- **mod_rewrite** harus aktif (untuk `.htaccess`).
- Di `httpd.conf`, untuk folder DocumentRoot harus ada `AllowOverride All` (bukan `None`), agar file `public/.htaccess` dipakai.

Di XAMPP umumnya sudah benar; jika URL tanpa `.php` tidak jalan, cek dua hal di atas.

### PHP

- Project membutuhkan **PHP 8.0+** (cek di XAMPP Control Panel / `php -v`).
- Ekstensi yang dipakai: `pdo_mysql` (untuk database). Cek di `php.ini`:  
  `extension=pdo_mysql` (tanpa titik koma di depan).

---

## 3. Yang **tidak** perlu diubah

- **Kode aplikasi** (controller, model, view) – tidak perlu diubah hanya karena pindah ke XAMPP.
- **`router.php`** – hanya dipakai untuk PHP built-in server (`php -S`). Di XAMPP pakai Apache + `public/index.php` + `.htaccess`, jadi `router.php` tidak dipakai.
- **`base_url()`** di `config/helpers.php` – sudah menyesuaikan otomatis dengan path (document root atau subfolder).

---

## 4. Ringkasan

| Item                    | Perlu diubah? | Keterangan                                      |
|-------------------------|---------------|-------------------------------------------------|
| `config/.env`           | **Ya**        | Sesuaikan DB_HOST, DB_NAME, DB_USER, DB_PASS    |
| Document root           | **Disarankan**| Arahkan ke folder `public` (virtual host)       |
| `public/.htaccess`      | Tidak         | Sudah benar untuk Apache                        |
| Kode PHP                | Tidak         | -                                               |
| `router.php`            | Tidak         | Hanya untuk development (PHP built-in server)  |

Setelah upload, pastikan:

1. File `config/.env` ada dan sudah diisi sesuai database server.
2. Apache bisa baca `public/.htaccess` (mod_rewrite + AllowOverride).
3. Buka di browser: `http://localhost/dashboard/public/` atau URL virtual host Anda.

Jika muncul error 500, cek log Apache (`logs/error.log`) dan pastikan path di vhost dan permission folder benar.
