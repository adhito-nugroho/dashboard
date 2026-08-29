# Setup User Dashboard Anggaran

## Deskripsi
Script ini membuat tabel `users` dan menambahkan 4 user default:
- **admin** (role: admin) - Akses penuh ke semua fitur
- **tu** (role: tu) - Dashboard khusus Seksi TU
- **rlpm** (role: rlpm) - Dashboard khusus Seksi RLPM
- **tkuk** (role: tkuk) - Dashboard khusus Seksi TKUK

Password default untuk semua user: **admin123**

## Cara Menjalankan

### Windows (Laragon/XAMPP)

1. Buka Command Prompt atau PowerShell
2. Masuk ke folder migrations:
   ```bash
   cd D:\laragon\www\dashboard\database\migrations
   ```

3. Jalankan script setup:
   ```bash
   setup_users.bat
   ```

4. Masukkan password database MySQL ketika diminta (biasanya kosong untuk Laragon)

### Manual (phpMyAdmin atau MySQL CLI)

1. Buka phpMyAdmin atau MySQL CLI
2. Pilih database `db_anggaran`
3. Jalankan file SQL: `create_users_and_insert.sql`

## Struktur Tabel Users

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'tu', 'rlpm', 'tkuk') NOT NULL,
    seksi_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seksi_id) REFERENCES seksi(id) ON DELETE SET NULL
)
```

## Login Credentials

| Username | Password | Role | Dashboard |
|----------|----------|------|-----------|
| admin    | admin123 | admin | Akses penuh |
| tu       | admin123 | tu    | Dashboard TU |
| rlpm     | admin123 | rlpm  | Dashboard RLPM |
| tkuk     | admin123 | tkuk  | Dashboard TKUK |

## URL Dashboard

- Admin: http://localhost/dashboard atau http://localhost/seksi
- TU: http://localhost/dashboard/tu
- RLPM: http://localhost/dashboard/rlpm
- TKUK: http://localhost/dashboard/tkuk

## Troubleshooting

### Error: MySQL tidak ditemukan
Edit file `setup_users.bat` dan sesuaikan path `MYSQL_PATH` dengan instalasi MySQL Anda.

### Error: Table 'seksi' doesn't exist
Pastikan tabel `seksi` sudah ada dan memiliki data untuk kode seksi: TU, RLPM, TKUK

### Error: Foreign key constraint fails
Pastikan data seksi sudah ada di database sebelum menjalankan script ini.
