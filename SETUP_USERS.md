# Panduan Setup User Dashboard

## Yang Sudah Dibuat

1. **Tabel `users`** dengan struktur:
   - id, username, password, role, seksi_id, timestamps
   
2. **4 User Default**:
   - **admin** → Role: admin (akses penuh)
   - **tu** → Role: tu (dashboard TU)
   - **rlpm** → Role: rlpm (dashboard RLPM)
   - **tkuk** → Role: tkuk (dashboard TKUK)
   
3. **Password Default**: `admin123` (untuk semua user)

4. **3 Dashboard Seksi**:
   - `/dashboard/tu` → Dashboard Tata Usaha
   - `/dashboard/rlpm` → Dashboard Rencana, Laporan dan Pemanfaatan Hutan
   - `/dashboard/tkuk` → Dashboard Teknik Konservasi dan Usaha Kehutanan

## Cara Install

### Metode 1: Menggunakan Batch Script (Otomatis)

1. Buka Command Prompt
2. Masuk ke folder migrations:
   ```
   cd D:\laragon\www\dashboard\database\migrations
   ```
3. Jalankan:
   ```
   setup_users.bat
   ```
4. Masukkan password database MySQL (default kosong, tekan Enter)

### Metode 2: Manual via phpMyAdmin

1. Buka phpMyAdmin: http://localhost/phpmyadmin
2. Pilih database `db_anggaran`
3. Klik tab "SQL"
4. Copy-paste isi file `create_users_and_insert.sql`
5. Klik "Go"

### Metode 3: Manual via MySQL Command Line

```bash
mysql -u root -p db_anggaran < D:\laragon\www\dashboard\database\migrations\create_users_and_insert.sql
```

## Login Credentials

| Username | Password | Role  | Akses Dashboard |
|----------|----------|-------|-----------------|
| admin    | admin123 | admin | Semua fitur (seksi, program, dll) |
| tu       | admin123 | tu    | Dashboard TU saja |
| rlpm     | admin123 | rlpm  | Dashboard RLPM saja |
| tkuk     | admin123 | tkuk  | Dashboard TKUK saja |

## URL Akses

- Login: http://localhost/login
- Dashboard Admin: http://localhost/dashboard atau http://localhost/seksi
- Dashboard TU: http://localhost/dashboard/tu
- Dashboard RLPM: http://localhost/dashboard/rlpm
- Dashboard TKUK: http://localhost/dashboard/tkuk

## Fitur Dashboard per Seksi

Setiap dashboard seksi menampilkan:
- **Total Pagu** seksi tersebut
- **Total RAK** seksi tersebut
- **Realisasi** dan persentase serapan
- **Sisa Anggaran**
- **Grafik Realisasi Bulanan** (RAK vs Realisasi)

Data yang ditampilkan difilter berdasarkan seksi_id masing-masing user.

## Testing

1. Buka http://localhost/login
2. Login dengan salah satu user (misal: `tu` / `admin123`)
3. Akan redirect ke dashboard sesuai role
4. Cek data anggaran yang muncul sesuai dengan seksi

## Troubleshooting

### Error: Table 'seksi' doesn't exist
Pastikan tabel `seksi` sudah ada dengan data:
```sql
SELECT * FROM seksi WHERE kode_seksi IN ('TU', 'RLPM', 'TKUK');
```

### Error: Foreign key constraint fails
Tabel `seksi` belum memiliki data. Tambahkan data seksi terlebih dahulu.

### Tidak bisa login
Pastikan password hash di database sesuai. Hash yang digunakan:
`$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`

### Dashboard kosong/tidak ada data
Pastikan data transaksi memiliki `seksi_id` yang sesuai dengan seksi user.

## File Yang Dimodifikasi

1. `app/Controllers/AuthController.php` - Login dengan database
2. `app/Controllers/DashboardSeksiController.php` - Controller dashboard seksi
3. `views/auth/login.php` - Update tampilan login
4. `views/dashboard/seksi.php` - Template dashboard seksi
5. `public/index.php` - Routing untuk dashboard seksi
6. `database/migrations/create_users_and_insert.sql` - Script SQL
7. `database/migrations/setup_users.bat` - Batch installer

## Catatan Keamanan

- Password default `admin123` sebaiknya diganti setelah login pertama kali
- Gunakan HTTPS di production
- Tambahkan fitur ganti password untuk keamanan lebih baik
