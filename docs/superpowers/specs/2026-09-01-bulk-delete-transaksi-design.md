# Design Specification: Fitur Hapus Banyak Transaksi (Bulk Delete)

**Tanggal**: 2026-09-01  
**Modul**: Transaksi (Role Admin)  
**Status**: Approved  

---

## 1. Latar Belakang & Tujuan
Pada modul transaksi untuk user admin, saat ini proses penghapusan transaksi hanya dapat dilakukan satu per satu melalui tombol aksi di setiap baris. Untuk mempermudah dan mempercepat pengelolaan data transaksi dalam jumlah banyak, dibutuhkan fitur **Hapus Banyak Transaksi (Bulk Delete)** dengan antarmuka checkbox seleksi dan konfirmasi keamanan.

---

## 2. Arsitektur & Perubahan Komponen

### A. Model (`App\Models\Transaksi`)
- Menambahkan method `deleteBatch(array $ids): int`:
  - Menerima array of integer `$ids`.
  - Menggunakan transaksi database (`beginTransaction`, `commit`, `rollBack`).
  - Melepaskan tautan referensi `rincian_biaya_perjalanan_dinas` jika ada (`UPDATE rincian_biaya_perjalanan_dinas SET transaksi_id = NULL WHERE transaksi_id IN (...)`).
  - Menjalankan `DELETE FROM transaksi WHERE id IN (...)` menggunakan parameterized query yang aman dari SQL Injection.
  - Mengembalikan total baris yang terhapus (`rowCount()`).

### B. Controller (`App\Controllers\TransaksiController`)
- Menambahkan method `deleteBatch(): void`:
  - Memastikan request menggunakan HTTP `POST`.
  - Mengambil data `ids` dari `$_POST['ids']`.
  - Memvalidasi dan membersihkan data `$ids` (memastikan array integer positif tidak kosong).
  - Memanggil `Transaksi::deleteBatch($validIds)`.
  - Menyiapkan flash message:
    - Sukses: `"Berhasil menghapus X transaksi"`
    - Gagal / Kosong: `"Tidak ada transaksi yang dipilih untuk dihapus"` atau pesan error exception.
  - Redirect kembali ke halaman index transaksi (`/transaksi`), dengan mempertahankan query string filter aktif jika ada (atau referer URL).

### C. Routing (`public/index.php`)
- Mendaftarkan endpoint baru:
  ```php
  elseif ($path === '/transaksi/delete-batch' && $requestMethod === 'POST') {
      $transaksiController->deleteBatch();
  }
  ```

### D. View & User Interface (`views/transaksi/index.php`)
- **Tabel Transaksi**:
  - Menambahkan kolom checkbox di header tabel:
    ```html
    <th width="4%" class="text-center">
        <input type="checkbox" class="form-check-input" id="check-all-trx" title="Pilih Semua">
    </th>
    ```
  - Menambahkan checkbox di setiap baris data:
    ```html
    <td class="text-center">
        <input type="checkbox" class="form-check-input row-checkbox" name="ids[]" value="<?= $transaksi['id'] ?>">
    </td>
    ```
- **Bilah Aksi Terpilih (Bulk Action Toolbar)**:
  - Diletakkan di atas tabel / dekat tombol filter atau header tabel.
  - Tombol aksi:
    - Tombol `Hapus Terpilih (<span id="count-selected">0</span>)` (warna merah/danger, tersembunyi jika 0 item terpilih).
    - Tombol `Batal Pilih` untuk uncheck semua item seketika.
- **Modal Konfirmasi Bootstrap**:
  - Modal `#modalConfirmBatchDelete` yang menampilkan konfirmasi jumlah data yang akan dihapus.
  - Form submit dengan method `POST` mengarah ke `/transaksi/delete-batch` dan membawa input hidden untuk setiap ID transaksi yang dipilih.
- **Interaksi JavaScript**:
  - Checkbox "Select All" mencentang semua `.row-checkbox` di halaman yang sedang aktif.
  - Listener event `change` pada checkbox untuk mengupdate jumlah counter dan toggle visibilitas tombol aksi.
  - Handle submit melalui modal konfirmasi secara dinamis.

---

## 3. Rencana Pengujian (Testing & Verification)
1. **Unit / Integration Test (Script PHP Test)**:
   - Buat test script untuk memvalidasi `Transaksi::deleteBatch()` dan `TransaksiController::deleteBatch()` (memastikan data benar-benar terhapus, foreign key link di-reset, dan error handling saat input invalid).
2. **Pengujian Fungsional UI**:
   - Centang "Pilih Semua" -> pastikan counter sesuai jumlah baris.
   - Hapus centang satu baris -> pastikan "Pilih Semua" unchecked dan counter berkurang.
   - Klik "Hapus Terpilih" -> modal konfirmasi muncul dengan jumlah yang benar.
   - Klik "Hapus" pada modal -> form terkirim, data terhapus dari database, dan flash message sukses muncul.
