# PERBAIKAN: Seksi di Level Sub Kegiatan (Bukan Program)

## ✅ Struktur Database yang Benar

Seksi berada di level **SUB_KEGIATAN**, bukan di level Program.

### Hierarki yang Benar:
```
Program
  └── Kegiatan
       └── Sub Kegiatan (memiliki seksi_id) ← SEKSI ADA DI SINI
            └── Rekening
                 └── Pagu
```

## 🔄 Langkah Rollback & Perbaikan

### 1. Rollback Database - Hapus seksi_id dari Program

Jalankan SQL ini di Navicat atau phpMyAdmin:

```sql
-- Rollback: Remove seksi_id column from program table

-- Step 1: Drop foreign key constraint
ALTER TABLE `program`
DROP FOREIGN KEY `fk_program_seksi`;

-- Step 2: Drop index
ALTER TABLE `program`
DROP INDEX `idx_seksi_id`;

-- Step 3: Drop seksi_id column
ALTER TABLE `program`
DROP COLUMN `seksi_id`;
```

### 2. Verifikasi Struktur sub_kegiatan

Pastikan tabel `sub_kegiatan` sudah memiliki kolom `seksi_id`:

```sql
-- Cek struktur tabel
DESC sub_kegiatan;

-- Seharusnya ada kolom:
-- - id
-- - kegiatan_id
-- - seksi_id  ← INI YANG PENTING
-- - kode_sub_kegiatan
-- - nama_sub_kegiatan
```

Jika belum ada, jalankan:

```sql
ALTER TABLE `sub_kegiatan`
ADD COLUMN `seksi_id` INT(11) NULL AFTER `kegiatan_id`,
ADD INDEX `idx_seksi_id` (`seksi_id`),
ADD CONSTRAINT `fk_sub_kegiatan_seksi`
FOREIGN KEY (`seksi_id`) REFERENCES `seksi`(`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;
```

## ✅ Perubahan Kode yang Sudah Dilakukan

### 1. ✅ app/Models/Pagu.php
- Query SELECT mengambil `sk.seksi_id AS sub_kegiatan_seksi_id`
- Bukan dari `pr.seksi_id` (program)

### 2. ✅ app/Controllers/DashboardController.php
- `matchesFilters()` menggunakan `sub_kegiatan_seksi_id`
- `getBreakdownData()` grouping seksi dari `sub_kegiatan_seksi_id`

### 3. ✅ app/Models/Program.php
- Rollback: Hapus parameter `seksi_id` dari create() dan update()

### 4. ✅ Dashboard View & public/index.php
- Tetap sama, tidak perlu perubahan

## 🎯 Cara Kerja Filter Seksi

Sekarang filter seksi bekerja dengan benar:

1. User pilih **Seksi** di dropdown
2. System filter data berdasarkan `sub_kegiatan.seksi_id`
3. Hanya menampilkan pagu yang sub_kegiatannya memiliki seksi_id tersebut

### Breakdown Hierarchy:
- **Tanpa filter** → Breakdown per **Seksi** (dari sub_kegiatan)
- **Filter Seksi** → Breakdown per **Program**
- **Filter Program** → Breakdown per **Kegiatan**
- **Filter Kegiatan** → Breakdown per **Sub Kegiatan**

## 📝 Update Data Sub Kegiatan

Pastikan semua sub_kegiatan memiliki seksi_id:

```sql
-- Cek sub_kegiatan tanpa seksi
SELECT * FROM sub_kegiatan WHERE seksi_id IS NULL;

-- Assign seksi ke sub_kegiatan
UPDATE sub_kegiatan SET seksi_id = 1 WHERE id IN (1, 2, 3);
UPDATE sub_kegiatan SET seksi_id = 2 WHERE id IN (4, 5, 6);

-- Atau berdasarkan pattern nama
UPDATE sub_kegiatan SET seksi_id = 1 WHERE nama_sub_kegiatan LIKE '%Pendidikan%';
```

## ✅ Checklist

- [ ] Rollback: Hapus seksi_id dari tabel program
- [ ] Verifikasi: Tabel sub_kegiatan memiliki kolom seksi_id
- [ ] Update data: Semua sub_kegiatan memiliki seksi_id
- [ ] Test: Dashboard filter seksi berfungsi dengan benar
- [ ] Test: Breakdown chart menampilkan data per seksi

## 🚀 Test Dashboard

Setelah rollback database selesai:

1. Refresh dashboard
2. Pilih filter Seksi
3. Verifikasi data yang ditampilkan sesuai dengan seksi yang dipilih
4. Cek breakdown chart menampilkan data yang benar

---

**Catatan**: Seksi ada di level Sub Kegiatan karena satu kegiatan bisa memiliki beberapa sub kegiatan yang ditangani oleh seksi yang berbeda.
