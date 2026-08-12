# Form RAK (Rencana Anggaran Kas) - User Guide

## 📋 Overview

Form RAK yang telah ditingkatkan dengan fitur-fitur untuk mempermudah input rencana anggaran kas bulanan selama satu tahun.

## ✨ Fitur Utama

### 1. **Quick Fill - Isi Semua Bulan**
Tombol untuk mengisi semua bulan dengan nilai yang sama sekaligus.

**Cara Pakai:**
1. Klik tombol "Isi Semua"
2. Masukkan nilai RAK (tanpa titik/koma)
3. Semua 12 bulan akan terisi dengan nilai yang sama

**Contoh:**
- Input: `10000000`
- Hasil: Semua bulan terisi `Rp 10.000.000`

### 2. **Copy ke Bulan Berikutnya**
Tombol panah (→) untuk copy nilai ke bulan berikutnya.

**Cara Pakai:**
1. Isi nilai RAK untuk bulan tertentu
2. Klik tombol panah (→) di sebelah kanan input
3. Nilai akan di-copy ke bulan berikutnya
4. Input akan flash hijau sebagai feedback visual

**Contoh:**
- Januari: `Rp 5.000.000` → Klik → → Februari: `Rp 5.000.000`

### 3. **Clear All - Kosongkan Semua**
Tombol untuk mengosongkan semua nilai RAK.

**Cara Pakai:**
1. Klik tombol "Kosongkan"
2. Konfirmasi dengan klik "OK"
3. Semua nilai akan dikosongkan

### 4. **Progress Tracking**
Visual progress bar yang menunjukkan berapa bulan yang sudah terisi.

**Informasi yang Ditampilkan:**
- **Total RAK:** Jumlah total dari semua bulan
- **Bulan Terisi:** X/12 bulan
- **Progress Bar:**
  - 🟡 Kuning: < 50% terisi
  - 🔵 Biru: 50-99% terisi
  - 🟢 Hijau: 100% terisi (semua bulan)

### 5. **Auto Format Rupiah**
Input otomatis diformat dengan pemisah ribuan.

**Contoh:**
- Ketik: `1000000`
- Tampil: `1.000.000`

### 6. **Real-time Calculation**
Total RAK dihitung otomatis setiap kali ada perubahan.

### 7. **Pagu Validation**
Sistem akan mengecek apakah total RAK melebihi Pagu.

**Informasi yang Ditampilkan:**
- **Pagu:** Nilai pagu yang ditetapkan
- **Total RAK:** Total rencana anggaran kas
- **Sisa Pagu:** Pagu - Total RAK
  - 🟢 Hijau: Masih ada sisa
  - 🔴 Merah: Melebihi pagu (warning)

## 🎯 Workflow Input RAK

### Langkah 1: Pilih Hierarki
1. Pilih **Program**
2. Pilih **Kegiatan** (otomatis muncul setelah pilih program)
3. Pilih **Sub Kegiatan** (otomatis muncul setelah pilih kegiatan)
4. Pilih **Rekening** (otomatis muncul setelah pilih sub kegiatan)
5. Masukkan **Tahun**

### Langkah 2: Input RAK Bulanan

**Opsi A: Input Manual**
- Isi setiap bulan satu per satu
- Gunakan tombol copy (→) untuk mempercepat

**Opsi B: Quick Fill**
- Klik "Isi Semua"
- Masukkan nilai
- Edit bulan tertentu jika perlu

**Opsi C: Kombinasi**
- Isi beberapa bulan manual
- Copy ke bulan berikutnya
- Adjust sesuai kebutuhan

### Langkah 3: Verify
1. Cek **Progress Bar** - pastikan semua bulan terisi
2. Cek **Total RAK** - pastikan sesuai rencana
3. Cek **Sisa Pagu** - pastikan tidak melebihi pagu

### Langkah 4: Submit
- Klik "Simpan" untuk menyimpan data

## 💡 Tips & Tricks

### Tip 1: Rencana Merata
Jika RAK sama setiap bulan:
1. Klik "Isi Semua"
2. Masukkan nilai
3. Done! ✅

### Tip 2: Rencana Bertahap
Jika RAK berbeda per bulan:
1. Isi bulan pertama
2. Klik copy (→) untuk bulan berikutnya
3. Edit nilai yang berbeda
4. Repeat untuk bulan lainnya

### Tip 3: Rencana Per Kuartal
Jika RAK sama per kuartal:
1. Isi Januari
2. Copy ke Februari dan Maret
3. Isi April (nilai berbeda)
4. Copy ke Mei dan Juni
5. Dst...

### Tip 4: Koreksi Cepat
Jika salah input:
1. Klik "Kosongkan"
2. Mulai dari awal
3. Atau edit manual bulan yang salah

## 🎨 UI Elements

### Layout
```
┌─────────────────────────────────────────────────────┐
│ Rencana Anggaran Kas per Bulan *                    │
│                          [Isi Semua] [Kosongkan]    │
├─────────────────────────────────────────────────────┤
│ Total RAK: Rp X.XXX.XXX    Bulan Terisi: X/12      │
│ ████████████░░░░░░░░░░░░ (Progress Bar)            │
├─────────────────────────────────────────────────────┤
│ Bulan      │ Nilai RAK         │ Bulan    │ Nilai  │
│ Januari    │ Rp [____] [→]     │ Februari │ Rp [__]│
│ Maret      │ Rp [____] [→]     │ April    │ Rp [__]│
│ ...                                                 │
└─────────────────────────────────────────────────────┘
```

### Button Icons
- 📊 **Isi Semua:** `bi-distribute-vertical`
- ❌ **Kosongkan:** `bi-x-circle`
- ➡️ **Copy:** `bi-arrow-right`

## 🔧 Technical Details

### Form Fields
- **Input Name:** `bulan_1` to `bulan_12`
- **Input Type:** Text (formatted as Rupiah)
- **Validation:** Required, must be numeric, cannot exceed Pagu

### JavaScript Functions
- `calculateTotal()` - Calculate total RAK
- `updateProgress()` - Update progress bar
- `checkPaguLimit()` - Validate against Pagu
- `fillAllBtn` - Fill all months
- `clearAllBtn` - Clear all values
- `copy-btn` - Copy to next month

### Auto-formatting
- Input: Raw numbers
- Display: Indonesian Rupiah format (1.000.000)
- Submit: Raw numbers (dots removed)

## 📝 Example Scenarios

### Scenario 1: RAK Merata
**Kebutuhan:** RAK Rp 10.000.000 per bulan
**Langkah:**
1. Klik "Isi Semua"
2. Input: `10000000`
3. Total: Rp 120.000.000
4. Progress: 12/12 (100%)
5. Simpan

### Scenario 2: RAK Bertahap
**Kebutuhan:** 
- Jan-Mar: Rp 5.000.000
- Apr-Jun: Rp 10.000.000
- Jul-Sep: Rp 15.000.000
- Oct-Dec: Rp 20.000.000

**Langkah:**
1. Isi Januari: `5000000`
2. Copy → Februari, Maret
3. Isi April: `10000000`
4. Copy → Mei, Juni
5. Isi Juli: `15000000`
6. Copy → Agustus, September
7. Isi Oktober: `20000000`
8. Copy → November, Desember
9. Total: Rp 180.000.000
10. Simpan

### Scenario 3: RAK Tidak Teratur
**Kebutuhan:** Setiap bulan berbeda
**Langkah:**
1. Isi manual satu per satu
2. Atau isi bulan pertama
3. Copy dan edit sesuai kebutuhan
4. Monitor progress bar
5. Simpan

## ⚠️ Validation Rules

1. **Minimal Input:** Minimal 1 bulan harus terisi
2. **Maximum Value:** Total RAK tidak boleh melebihi Pagu
3. **Numeric Only:** Hanya angka yang diperbolehkan
4. **Positive Values:** Nilai harus positif (≥ 0)

## 🎯 Success Indicators

✅ Progress bar hijau (100%)
✅ Total RAK sesuai rencana
✅ Sisa Pagu hijau (tidak melebihi)
✅ Semua bulan terisi
✅ Form validation passed

---

**Form RAK sekarang lebih mudah dan cepat untuk input!** 🚀
