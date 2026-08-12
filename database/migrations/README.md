# Panduan Menambahkan Kolom seksi_id ke Tabel Program

## 📋 Ringkasan
Panduan ini menjelaskan cara menambahkan kolom `seksi_id` ke tabel `program` dan mengaktifkan filter seksi di dashboard.

## 🔧 Langkah-Langkah

### 1. Jalankan Database Migration

#### Opsi A: Menggunakan Batch Script (Otomatis)
```bash
cd database/migrations
run_migration.bat
```

#### Opsi B: Manual via phpMyAdmin atau MySQL Command Line
1. Buka phpMyAdmin (http://localhost/phpmyadmin)
2. Pilih database Anda
3. Klik tab "SQL"
4. Copy-paste isi file `add_seksi_id_to_program.sql`
5. Klik "Go" untuk menjalankan

#### Opsi C: Via MySQL Command Line
```bash
mysql -u root -p nama_database < database/migrations/add_seksi_id_to_program.sql
```

### 2. Update Data Existing Program

Setelah migration berhasil, Anda perlu mengisi `seksi_id` untuk program yang sudah ada.

#### Cara 1: Via phpMyAdmin
1. Buka tabel `program`
2. Edit setiap record dan pilih seksi yang sesuai

#### Cara 2: Via SQL Query
```sql
-- Contoh: Assign seksi_id untuk program tertentu
UPDATE program SET seksi_id = 1 WHERE id IN (1, 2, 3);
UPDATE program SET seksi_id = 2 WHERE id IN (4, 5, 6);

-- Atau assign berdasarkan nama program
UPDATE program SET seksi_id = 1 WHERE nama_program LIKE '%Pendidikan%';
UPDATE program SET seksi_id = 2 WHERE nama_program LIKE '%Kesehatan%';
```

### 3. Update Program Controller

File yang sudah diupdate:
- ✅ `app/Models/Program.php` - Method create() dan update() sudah support seksi_id

File yang perlu diupdate manual:
- ⚠️ `app/Controllers/ProgramController.php` - Tambahkan handling untuk seksi_id

Contoh update di ProgramController:
```php
// Di method store()
$seksiId = isset($_POST['seksi_id']) && $_POST['seksi_id'] !== '' ? (int) $_POST['seksi_id'] : null;
$id = $this->programModel->create($kodeProgram, $namaProgram, $tahun, $seksiId);

// Di method update()
$seksiId = isset($_POST['seksi_id']) && $_POST['seksi_id'] !== '' ? (int) $_POST['seksi_id'] : null;
$this->programModel->update($id, $kodeProgram, $namaProgram, $tahun, $seksiId);
```

### 4. Update Program Form Views

Tambahkan dropdown seksi di form create dan edit program:

```php
<div class="mb-3">
    <label for="seksi_id" class="form-label">Seksi</label>
    <select class="form-select" id="seksi_id" name="seksi_id">
        <option value="">-- Pilih Seksi (Opsional) --</option>
        <?php foreach ($seksiList as $seksi): ?>
            <option value="<?= $seksi['id'] ?>" <?= isset($program) && $program['seksi_id'] == $seksi['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($seksi['kode_seksi'] . ' - ' . $seksi['nama_seksi']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
```

### 5. Aktifkan Filter Seksi di Dashboard

Setelah semua langkah di atas selesai, jalankan script berikut untuk mengaktifkan filter seksi:

```bash
# Script akan dibuat untuk mengaktifkan filter seksi
# File yang akan diupdate:
# - app/Controllers/DashboardController.php
# - app/Models/Pagu.php
# - views/dashboard/index.php
# - public/index.php
```

## ✅ Verifikasi

Setelah semua langkah selesai, pastikan:

1. ✅ Kolom `seksi_id` ada di tabel `program`
2. ✅ Data program existing sudah memiliki `seksi_id`
3. ✅ Form program bisa create/update dengan seksi
4. ✅ Dashboard menampilkan filter seksi
5. ✅ Filter seksi berfungsi dengan baik

## 🔍 Troubleshooting

### Error: Column 'seksi_id' cannot be null
**Solusi**: Pastikan semua program existing sudah memiliki seksi_id, atau ubah kolom menjadi nullable

### Error: Foreign key constraint fails
**Solusi**: Pastikan semua seksi_id yang diassign ada di tabel seksi

### Filter seksi tidak muncul
**Solusi**: Pastikan sudah menjalankan script aktivasi filter seksi

## 📝 Catatan Penting

- Kolom `seksi_id` bersifat **NULLABLE** (boleh kosong)
- Foreign key constraint memastikan data integrity
- Backup database sebelum menjalankan migration
- Test di development environment terlebih dahulu

## 🚀 Next Steps

Setelah migration berhasil, Anda bisa:
1. Menjalankan script aktivasi filter seksi
2. Test semua fitur untuk memastikan tidak ada error
3. Update data program existing dengan seksi yang sesuai
