# Prompt Perbaikan UI/UX — Dashboard Monitoring Anggaran CDK Bojonegoro

Gunakan prompt ini sebagai instruksi lengkap untuk coding agent (Claude Code, Cursor, Copilot, dll).

---

## Konteks Proyek

Kamu sedang mengerjakan dashboard web **Monitoring Realisasi Anggaran** untuk CDK Wilayah Bojonegoro. Dashboard ini menampilkan data serapan anggaran per bulan, per seksi, dan per sub kegiatan dibandingkan terhadap Rencana Anggaran Kas (RAK).

---

## Daftar Perbaikan yang Harus Dikerjakan

### 1. Perbaikan Warna Kolom Selisih di Tabel (PRIORITAS TINGGI)

**Masalah:** Kolom "Selisih" di halaman `Detail per Bulan` menggunakan warna hijau untuk nilai negatif (under RAK), yang secara konvensi sangat menyesatkan karena hijau diasosiasikan dengan kondisi "baik/positif".

**Yang harus dilakukan:**
- Nilai selisih **negatif (under RAK)** → gunakan warna **merah** (`text-red-600` atau `#DC2626`) dengan ikon panah bawah `↓`
- Nilai selisih **positif (over RAK)** → gunakan warna **biru** atau **hijau** (`text-blue-600` / `#2563EB`) dengan ikon panah atas `↑`
- Kolom `% Capaian THD RAK`:
  - `< 50%` → badge merah
  - `50–90%` → badge oranye/amber
  - `90–110%` → badge hijau
  - `> 110%` → badge biru (over RAK)
- Terapkan perubahan ini juga di semua tempat lain yang menampilkan selisih RAK vs Realisasi (tab Komposisi, Deviasi RAK, dll.)

```
// Contoh logika warna selisih:
function getSelisihStyle(value) {
  if (value < 0) return { color: '#DC2626', icon: '↓', label: 'Under RAK' }
  if (value > 0) return { color: '#2563EB', icon: '↑', label: 'Over RAK' }
  return { color: '#6B7280', icon: '—', label: 'Sesuai RAK' }
}
```

---

### 2. Restrukturisasi Navigasi Tab (PRIORITAS TINGGI)

**Masalah:** 8 tab horizontal sejajar tanpa pengelompokan hierarki membuat pengguna baru sulit memahami alur dan tujuan tiap halaman.

**Yang harus dilakukan:**
- Kelompokkan 8 tab menjadi **3 grup logis** dengan visual separator atau sub-label grup:

  | Grup | Tab yang termasuk |
  |------|-------------------|
  | **Ringkasan** | Ringkasan, Grafik Bulanan, Komposisi |
  | **Analisis** | Detail per Bulan, Sisa Semester, Breakdown |
  | **Perencanaan** | Struktur Anggaran, Deviasi RAK |

- Tampilkan label grup sebagai teks kecil (`10–11px`, `text-gray-400`) di atas tab pada grup pertama masing-masing
- Tab aktif tetap di-highlight seperti sekarang
- Pada mobile/layar sempit, collapse menjadi dropdown select

---

### 3. Indikator Filter Aktif (PRIORITAS SEDANG)

**Masalah:** Setelah pengguna memilih filter (Seksi, Program, Kegiatan, Sub Kegiatan), tidak ada penanda visual bahwa filter sedang aktif. Pengguna bisa lupa filter masih aktif saat berpindah tab.

**Yang harus dilakukan:**
- Jika ada filter yang tidak pada nilai default ("Semua ..."), tampilkan:
  - Badge counter di area Filter Data: `Filter Aktif: 2` (berwarna biru)
  - Setiap dropdown yang aktif mendapat border berwarna biru (`border-blue-500`) dan background sedikit lebih beda
  - Tombol "Reset" berubah menjadi lebih prominent (warna merah/oranye, tidak hanya teks abu-abu)
- Saat berpindah tab, pertahankan state filter dan tampilkan banner kecil di bawah tab: `"Menampilkan data untuk: Seksi RLPM"` dengan tombol `×` untuk reset cepat

---

### 4. Anotasi Anomali pada Grafik (PRIORITAS SEDANG)

**Masalah:** Data realisasi Mei hanya 9,5% dari target RAK (anomali ekstrem), tapi grafik tidak memberikan konteks apapun.

**Yang harus dilakukan:**
- Deteksi otomatis titik data yang capaiannya `< 20%` dari RAK bulan tersebut
- Tampilkan **marker khusus** pada titik tersebut di grafik (misal: titik lebih besar, warna merah, atau ikon `!`)
- Saat hover/klik pada titik anomali, tampilkan tooltip yang lebih kaya:
  ```
  Mei 2026
  RAK Target : Rp 307.680.441
  Realisasi  : Rp 29.343.163
  Capaian    : 9,5%
  ⚠ Realisasi jauh di bawah target RAK
  ```
- Opsional: tampilkan catatan singkat di bawah grafik: `"Mei: realisasi di bawah 10% dari target RAK"`

---

### 5. Export per Section (PRIORITAS SEDANG)

**Masalah:** Tombol "Export Excel" hanya ada satu di header dan mengekspor semua data sekaligus, kurang fleksibel.

**Yang harus dilakukan:**
- Pertahankan tombol Export di header (untuk export seluruh data/all tabs)
- Tambahkan tombol **Export** kecil (ikon `⬇ xlsx`) di pojok kanan atas setiap tabel/section:
  - Tabel Detail per Bulan → export data per bulan saja
  - Tabel Breakdown → export per seksi
  - Tabel Deviasi RAK → export daftar sub kegiatan berdeviasi
- Nama file export otomatis mengikuti konteks: `deviasi-rak-2026.xlsx`, `detail-bulan-2026.xlsx`, dst.

---

### 6. Kolom Prioritas & Catatan di Halaman Deviasi RAK (PRIORITAS RENDAH)

**Masalah:** Daftar 26 sub kegiatan berdeviasi hanya bersifat informatif tanpa mendukung tindak lanjut.

**Yang harus dilakukan:**
- Tambahkan kolom **Tingkat Risiko** yang dihitung otomatis berdasarkan besar deviasi:
  - Deviasi > 50% dari RAK sub kegiatan → `Kritis` (badge merah)
  - Deviasi 20–50% → `Sedang` (badge oranye)
  - Deviasi < 20% → `Rendah` (badge kuning)
- Tambahkan kolom **Catatan** (text input inline, max 100 karakter) agar petugas bisa memberi keterangan langsung dari tabel
- Data catatan disimpan ke local state atau API endpoint yang sudah ada
- Tambahkan filter cepat: `Semua | Kritis | Sedang | Rendah`

---

### 7. Proyeksi Serapan Akhir Tahun (PRIORITAS RENDAH / FITUR BARU)

**Masalah:** Tidak ada informasi prediktif — pengguna hanya melihat data historis.

**Yang harus dilakukan:**
- Di halaman Ringkasan, tambahkan kartu baru: **"Proyeksi Akhir Tahun"**
- Hitung proyeksi dengan metode sederhana: rata-rata realisasi per bulan (dari bulan yang sudah ada data) × 12
- Tampilkan:
  - Angka proyeksi (Rp)
  - % proyeksi terhadap total Pagu
  - Label status: `"Diperkirakan tidak mencapai target"` / `"Diperkirakan on track"`
- Berikan disclaimer kecil: `"Proyeksi berdasarkan rata-rata Jan–Mei 2026"`

---

## Catatan Teknis untuk Developer

- Semua perubahan warna harus kompatibel dengan mode terang dan gelap (gunakan CSS variables jika pakai Tailwind dark mode)
- Pastikan perubahan warna selisih (poin 1) diaplikasikan **konsisten** di seluruh komponen — cek semua file komponen yang merender data RAK vs Realisasi
- Untuk filter aktif (poin 3), gunakan global state / context agar persisten antar tab
- Anotasi grafik (poin 4) — jika menggunakan Chart.js, gunakan `plugins.annotation` atau custom plugin. Jika Recharts, gunakan `<ReferenceDot>` dengan label custom
- Prioritas pengerjaan: **1 → 2 → 3 → 4 → 5 → 6 → 7**

---

## Kriteria Selesai (Definition of Done)

- [ ] Warna selisih negatif = merah, positif = biru, di semua halaman
- [ ] Tab terkelompok dalam 3 grup dengan label grup
- [ ] Filter aktif terindikasi secara visual (badge + border biru + reset prominent)
- [ ] Titik anomali grafik ditandai dan tooltip informatif
- [ ] Tombol export per section tersedia di setiap tabel
- [ ] Kolom risiko + catatan di halaman Deviasi RAK
- [ ] Kartu proyeksi akhir tahun di halaman Ringkasan
