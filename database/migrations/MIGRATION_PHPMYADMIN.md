# Cara Menjalankan Migration via phpMyAdmin

## Langkah-langkah:

### 1. Buka phpMyAdmin
- URL: http://localhost/phpmyadmin
- Atau klik "Database" di Laragon menu

### 2. Pilih Database
- Klik nama database Anda di sidebar kiri
- Biasanya nama database: `dashboard_anggaran` atau sesuai yang Anda gunakan

### 3. Buka Tab SQL
- Klik tab "SQL" di bagian atas

### 4. Copy-Paste SQL Berikut:

```sql
-- Migration: Add seksi_id column to program table
-- Date: 2026-01-12

-- Step 1: Add seksi_id column to program table
ALTER TABLE `program` 
ADD COLUMN `seksi_id` INT(11) NULL AFTER `tahun`,
ADD INDEX `idx_seksi_id` (`seksi_id`);

-- Step 2: Add foreign key constraint (optional, for data integrity)
ALTER TABLE `program`
ADD CONSTRAINT `fk_program_seksi`
FOREIGN KEY (`seksi_id`) REFERENCES `seksi`(`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;
```

### 5. Klik "Go" atau "Kirim"

### 6. Verifikasi
Setelah berhasil, cek struktur tabel program:
- Klik tabel `program` di sidebar
- Klik tab "Structure"
- Pastikan kolom `seksi_id` sudah ada

## Jika Ada Error:

### Error: Table 'seksi' doesn't exist
**Solusi**: Pastikan tabel `seksi` sudah ada di database Anda

### Error: Duplicate column name 'seksi_id'
**Solusi**: Kolom sudah ada, skip migration ini

### Error: Foreign key constraint fails
**Solusi**: 
1. Jalankan hanya Step 1 (tanpa foreign key)
2. Atau pastikan tabel seksi sudah ada dan memiliki data

## Setelah Migration Berhasil:

### Update Data Program Existing
Jalankan query berikut untuk assign seksi ke program yang sudah ada:

```sql
-- Lihat daftar seksi yang tersedia
SELECT * FROM seksi;

-- Lihat program yang belum punya seksi
SELECT * FROM program WHERE seksi_id IS NULL;

-- Assign seksi ke program (sesuaikan dengan data Anda)
-- Contoh:
UPDATE program SET seksi_id = 1 WHERE id = 1;
UPDATE program SET seksi_id = 1 WHERE id = 2;
UPDATE program SET seksi_id = 2 WHERE id = 3;

-- Atau assign berdasarkan pattern nama
UPDATE program SET seksi_id = 1 WHERE nama_program LIKE '%Pendidikan%';
UPDATE program SET seksi_id = 2 WHERE nama_program LIKE '%Kesehatan%';
```

## Next Steps:

Setelah migration dan update data selesai:
1. ✅ Kolom seksi_id sudah ada
2. ✅ Data program sudah memiliki seksi_id
3. 📝 Lanjut ke aktivasi filter seksi (lihat ACTIVATE_SEKSI_FILTER.md)
