# Design Specification: Export RAK ke File Excel

## 1. Overview
Fitur ini menambahkan kemampuan ekspor data Rencana Anggaran Kas (RAK) bulanan ke format file Excel (`.xlsx`) langsung dari halaman daftar RAK (`/rak`). Data yang diekspor akan mencerminkan filter aktif (Tahun, Kegiatan, Sub Kegiatan) dan mencakup seluruh data yang cocok (tanpa dibatasi paginasi tabel web 10 baris).

## 2. Requirements & User Intent
- **Menu Target**: Menu RAK (`/rak`).
- **Pemicu**: Tombol "Export Excel" di header daftar RAK.
- **Data Scope**: Seluruh baris RAK sesuai kriteria filter aktif (`tahun`, `kegiatan_id`, `sub_kegiatan_id`).
- **Format Output**: Format Tabel Daftar RAK (`.xlsx`) berisikan rincian rekening, alokasi anggaran 12 bulan (Januari - Desember), total tahunan, dan baris grand total.

## 3. Architecture & Components

### 3.1 Routing (`public/index.php`)
- Tambah route baru:
  - Route: `GET /rak/export`
  - Handler: `$rakController->export()`
  - Autentikasi: Diwajibkan login (mewarisi aturan default protected routes).

### 3.2 View (`views/rak/index.php`)
- Tambahkan tombol **Export Excel** di header halaman berdampingan dengan tombol **Tambah RAK**.
- URL tombol dibangun dengan membawa parameter query filter saat ini:
  - `base_url('rak/export?' . http_build_query($_GET))`
- Ikon tombol: `bi-file-earmark-excel`, styling Bootstrap `btn btn-outline-success` atau `btn btn-success`.

### 3.3 Controller (`app/Controllers/RakController.php`)
- Menambahkan method `export(): void`.
- Dependensi: `PhpOffice\PhpSpreadsheet\Spreadsheet`, `PhpOffice\PhpSpreadsheet\Writer\Xlsx`, style classes (`Alignment`, `Border`, `Fill`, `Font`).
- Mengambil filter:
  - `tahun` (opsional / integer)
  - `kegiatan_id` (opsional / integer)
  - `sub_kegiatan_id` (opsional / integer)
- Query data: `$this->rakModel->getWithFilters($filterTahun, $filterKegiatan, $filterSubKegiatan)`.
- Grouping data per `rekening_id` + `tahun`, menyusun 12 bulan alokasi (`Januari` s/d `Desember`) dan menghitung `total`.

### 3.4 Excel Structure & Layout
1. **Header Metadata**:
   - Baris 1: Judul **RENCANA ANGGARAN KAS (RAK)** (Bold, Size 14).
   - Baris 2: Informasi Filter Aktif (misal: Tahun: 2026, Kegiatan: ..., Sub Kegiatan: ...).
   - Baris 3: Tanggal Export (format `DD/MM/YYYY HH:mm`).
   - Baris 4: Baris kosong pemisah.
2. **Table Header (Baris 5)**:
   - Kolom A: `No`
   - Kolom B: `Kode Rekening`
   - Kolom C: `Nama Rekening`
   - Kolom D: `Program`
   - Kolom E: `Kegiatan`
   - Kolom F: `Sub Kegiatan`
   - Kolom G: `Tahun`
   - Kolom H s/d S: Bulan `Jan`, `Feb`, `Mar`, `Apr`, `Mei`, `Jun`, `Jul`, `Agu`, `Sep`, `Okt`, `Nov`, `Des`
   - Kolom T: `Total (Rp)`
   - Styling: Background biru tua / navy (`#1E3A5F`), teks putih tebal, vertical & horizontal center alignment, border tipis.
3. **Data Rows**:
   - Kolom A: Nomor urut (1..N, rata tengah).
   - Kolom B: Kode rekening (rata tengah).
   - Kolom C: Nama rekening (rata kiri).
   - Kolom D, E, F: Kode / nama Program, Kegiatan, Sub Kegiatan (rata kiri).
   - Kolom G: Tahun (rata tengah).
   - Kolom H s/d S: Nilai RAK bulan 1 s/d 12 diformat angka `#,##0` (rata kanan).
   - Kolom T: Total nilai RAK tahunan diformat `#,##0` (rata kanan).
4. **Grand Total Row**:
   - Label: `TOTAL KESELURUHAN` (kolom A s/d G di-merge atau diberi penanda).
   - Kolom H s/d S: Sum nilai per bulan menggunakan formula `=SUM(...)` atau nilai agregasi.
   - Kolom T: Formula `=SUM(...)` total tahunan.
   - Styling: Font bold, background kuning lembut / abu muda (`#E2E8F0` atau `#FEF08A`), border tipis.
5. **Autosize**:
   - Kolom A s/d T diset auto width untuk memastikan data terbaca jelas.

### 3.5 Response & Penamaan File
- Nama file: `RAK_[Tahun]_[Ymd_His].xlsx` atau `RAK_Semua_[Ymd_His].xlsx`.
- HTTP Header:
  - `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
  - `Content-Disposition: attachment;filename="..."`
  - `Cache-Control: max-age=0`
- Output dikirim langsung menggunakan `(new Xlsx($spreadsheet))->save('php://output')` lalu `exit`.

## 4. Error Handling & Edge Cases
- **Data Kosong**: Jika tidak ada data RAK yang cocok dengan filter, file Excel tetap dibuat dengan menyertakan header dan satu baris keterangan *"Tidak ada data RAK"* atau total bernilai 0, sehingga tidak menyebabkan crash HTTP 500.
- **Karakter Khusus**: Nama rekening / program / sub kegiatan yang mengandung karakter khusus diamankan saat dimasukkan ke cell.
- **Akses Tanpa Login**: Otomatis dialihkan ke halaman login oleh middleware di `public/index.php`.
