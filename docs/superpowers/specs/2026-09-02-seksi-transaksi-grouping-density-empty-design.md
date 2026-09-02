# Design Specification: UI/UX Transaksi Saya — Grouping Surat Tugas, Density Toggle & Contextual Empty State

**File Target**: `views/seksi/transaksi_index.php`  
**Tanggal**: 2026-09-02  
**Scope**: Khusus halaman "Transaksi Saya" Seksi (`/seksi/transaksi`). Tanpa mengubah query database, skema tabel, atau alur bisnis.

---

## 1. Ringkasan Fitur

Peningkatan UI/UX tabel transaksi seksi yang berfokus pada:
1. **Grouping Collapsible Berdasarkan Surat Tugas (ST)**: Mengelompokkan baris transaksi yang memiliki nomor Surat Tugas yang sama menjadi 1 baris induk (parent) yang dapat di-expand/collapse menggunakan Alpine.js.
2. **Toggle Density / Mode Tampilan (Normal vs Compact)**: Tombol toggle kerapatan baris tabel (padding & ukuran font) yang tersimpan di `localStorage`.
3. **Contextual Empty State**: Menampilkan pesan dan ikon ramah pengguna yang kontekstual berdasarkan tab aktif saat data kosong.

---

## 2. Arsitektur & Detail Implementasi

### A. Grouping Surat Tugas (Collapsible Rows)

#### 1. Mekanisme Grouping di Layer PHP (View)
- Tidak mengubah query SQL dasar (menjaga performa, pagination, dan filter tetap 100% aman).
- Setelah `$transaksis` diambil dari database, di dalam view dilakukan pengelompokan baris:
  - Transaksi dengan `nomor_surat_tugas` yang sama dan jumlahnya $\ge 2$ dijadikan **1 Grup Collapsible**.
  - Transaksi tanpa Surat Tugas (atau nomor ST unik yang hanya berisi 1 transaksi) tetap dirender sebagai **Baris Tunggal Biasa**.
- Total nilai pada baris induk merupakan akumulasi (`sum`) nilai seluruh anggota grup.

#### 2. Penanganan Status Campuran (Badge Prioritas Perhatian)
Sesuai konfirmasi user, baris induk (parent group) menggunakan **Prioritas Perhatian**:
- **Ditolak** (Prioritas 1): Jika ada minimal 1 transaksi dalam grup yang berstatus `ditolak`.
- **Menunggu Verifikasi** (Prioritas 2): Jika tidak ada yang ditolak, tetapi ada minimal 1 transaksi yang berstatus `diajukan`.
- **Diverifikasi** (Prioritas 3): Hanya jika **seluruh** transaksi dalam grup sudah berstatus `diverifikasi`.

#### 3. Interaktivitas Alpine.js
- Setiap baris grup dibungkus dalam container / attribute Alpine.js:
  ```html
  <tbody x-data="{ open: false }">
      <!-- Baris Parent -->
      <tr @click="open = !open" class="group-parent-row cursor-pointer">
          ...
          <button type="button" class="btn btn-sm btn-light">
              <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
              <span x-text="open ? 'Tutup' : 'Lihat Pegawai (N)'"></span>
          </button>
      </tr>
      <!-- Sub-Baris per Pegawai -->
      <tr x-show="open" x-cloak class="group-child-row">
          <!-- Aksi Edit, Hapus, dan Lampiran tetap di level sub-baris per ID -->
      </tr>
  </tbody>
  ```
- Default state: **Collapsed** (`open: false`).
- Aksi Edit & Hapus tetap berada di level sub-baris per transaksi dengan ID masing-masing.

---

### B. Mode Compact / Density Toggle

#### 1. Tombol Toggle
- Ditempatkan di baris filter / header tabel sebelah tombol BKU / Reset.
- Ikon Bootstrap Icons: `bi-list-ul` (Normal) vs `bi-distribute-vertical` / `bi-text-paragraph` (Compact).
- Disertai tooltip visual "Mode Renggang / Mode Rapat".

#### 2. Styling CSS
- Kelas `.table-compact`:
  - `padding` sel tabel berkurang dari `0.75rem` menjadi `0.35rem 0.6rem`.
  - Font size baris tabel: `0.8rem` (dari `0.875rem`).
  - Tinggi tombol aksi menyesuaikan menjadi `28px × 28px`.

#### 3. Persistensi State
- Menggunakan `localStorage.getItem('seksi_transaksi_density')` ('normal' atau 'compact').
- Saat halaman dimuat, script membaca nilai localStorage dan langsung mengaplikasikan kelas ke tabel tanpa kedip (*flash*).

---

### C. Contextual Empty State

Menggantikan area tabel ketika jumlah data adalah 0:

| Tab / Kondisi Aktif | Teks Judul | Subteks | Ikon |
| :--- | :--- | :--- | :--- |
| **Diverifikasi** | Belum ada transaksi yang diverifikasi. | Transaksi yang disetujui admin/bendahara akan tampil di sini. | `bi-check2-circle` |
| **Ditolak** | Belum ada transaksi yang ditolak. | Bagus! Tidak ada transaksi belanja yang ditolak oleh verifikator. | `bi-shield-check` |
| **Menunggu Verifikasi** | Tidak ada transaksi yang menunggu verifikasi. | Semua transaksi yang diajukan sudah diproses. | `bi-inbox` |
| **Filter / Pencarian Aktif** | Tidak ada transaksi yang cocok dengan filter ini. | Coba sesuaikan bulan, tahun, atau kata kunci pencarian Anda. | `bi-search` |
| **Semua (Baru Kosong)** | Belum Ada Transaksi yang Diajukan. | Klik tombol di bawah untuk mulai menginput transaksi belanja baru. | `bi-receipt-cutoff` |

---

## 3. Rencana Pengujian / Verifikasi
1. **Grouping Test**:
   - Menampilkan Surat Tugas yang memiliki $\ge 2$ transaksi dalam kondisi collapsed.
   - Mengklik toggle expand untuk melihat sub-baris rincian pegawai dan memastikan aksi edit/hapus/unduh berfungsi per transaksi.
2. **Mixed Status Test**:
   - Memastikan ST dengan status campuran menampilkan badge prioritas yang tepat (misal Ditolak jika ada yang ditolak, Menunggu jika ada yang diajukan).
3. **Compact Mode Test**:
   - Mengklik tombol toggle density, memastikan baris memadat, lalu me-refresh halaman untuk memastikan preferensi tersimpan di `localStorage`.
4. **Empty State Test**:
   - Membuka tab dengan data 0 (misal tab "Ditolak" jika belum ada yang ditolak) dan memastikan pesan kontekstual tampil menggantikan tabel kosong.
