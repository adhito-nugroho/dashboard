# Cascading Filter Implementation

## ✅ Fitur Cascading Filter

Filter dropdown sekarang **saling terhubung** - pilihan di dropdown berikutnya dibatasi berdasarkan pilihan sebelumnya.

## 🔄 Alur Cascading Filter

### **1. Filter Seksi → Program**
Ketika user memilih **Seksi RLPM**:
- Dropdown **Program** hanya menampilkan program yang memiliki sub_kegiatan dengan seksi_id = RLPM
- Dropdown **Kegiatan** hanya menampilkan kegiatan yang terkait dengan program tersebut
- Dropdown **Sub Kegiatan** hanya menampilkan sub_kegiatan dengan seksi_id = RLPM

### **2. Filter Program → Kegiatan**
Ketika user memilih **Program Tertentu**:
- Dropdown **Kegiatan** hanya menampilkan kegiatan yang program_id = program terpilih
- Dropdown **Sub Kegiatan** hanya menampilkan sub_kegiatan dari kegiatan tersebut

### **3. Filter Kegiatan → Sub Kegiatan**
Ketika user memilih **Kegiatan Tertentu**:
- Dropdown **Sub Kegiatan** hanya menampilkan sub_kegiatan yang kegiatan_id = kegiatan terpilih

## 📊 Logic Cascading

### **Seksi Filter (Level 1)**
```
Seksi RLPM dipilih
  ↓
Cari semua sub_kegiatan dengan seksi_id = RLPM
  ↓
Cari semua kegiatan yang memiliki sub_kegiatan tersebut
  ↓
Cari semua program yang memiliki kegiatan tersebut
  ↓
Filter dropdown Program, Kegiatan, Sub Kegiatan
```

### **Program Filter (Level 2)**
```
Program X dipilih
  ↓
Cari semua kegiatan dengan program_id = X
  ↓
Cari semua sub_kegiatan yang memiliki kegiatan tersebut
  ↓
Filter dropdown Kegiatan, Sub Kegiatan
```

### **Kegiatan Filter (Level 3)**
```
Kegiatan Y dipilih
  ↓
Cari semua sub_kegiatan dengan kegiatan_id = Y
  ↓
Filter dropdown Sub Kegiatan
```

## 🎯 Contoh Penggunaan

### **Skenario 1: Filter per Seksi**
1. User pilih **Seksi RLPM**
2. Dropdown Program menampilkan: Program Pengelolaan Hutan, Program Pendidikan
3. Dropdown Kegiatan menampilkan: Hanya kegiatan dari 2 program di atas
4. Dropdown Sub Kegiatan menampilkan: Hanya sub_kegiatan dengan seksi RLPM

### **Skenario 2: Filter per Program**
1. User pilih **Program Pengelolaan Hutan**
2. Dropdown Kegiatan menampilkan: Hanya kegiatan dari Program Pengelolaan Hutan
3. Dropdown Sub Kegiatan menampilkan: Hanya sub_kegiatan dari kegiatan tersebut

### **Skenario 3: Filter Bertingkat**
1. User pilih **Seksi RLPM**
2. User pilih **Program Pengelolaan Hutan** (dari list yang sudah difilter)
3. User pilih **Kegiatan Rehabilitasi** (dari list yang sudah difilter)
4. Dropdown Sub Kegiatan: Hanya sub_kegiatan dari Kegiatan Rehabilitasi dengan Seksi RLPM

## 💡 Benefits

✅ **User Experience**: User tidak bingung dengan pilihan yang tidak relevan
✅ **Data Accuracy**: Mencegah kombinasi filter yang tidak valid
✅ **Performance**: Mengurangi jumlah data yang ditampilkan
✅ **Intuitive**: Alur filter mengikuti hierarki data yang natural

## 🔧 Implementation Details

### **Method: getFilterOptions()**
- Input: `$filters` array (seksi_id, program_id, kegiatan_id, sub_kegiatan_id)
- Output: Filtered options untuk setiap dropdown
- Logic: Cascading dari top-down (Seksi → Program → Kegiatan → Sub Kegiatan)

### **Key Features:**
- `array_filter()` untuk filter data berdasarkan kondisi
- `array_column()` untuk extract ID dari array
- `array_unique()` untuk remove duplicate IDs
- `array_values()` untuk re-index array setelah filter

## 📝 Testing Checklist

- [ ] Pilih Seksi → Verify Program, Kegiatan, Sub Kegiatan terfilter
- [ ] Pilih Program → Verify Kegiatan, Sub Kegiatan terfilter
- [ ] Pilih Kegiatan → Verify Sub Kegiatan terfilter
- [ ] Reset filter → Verify semua dropdown kembali menampilkan semua data
- [ ] Kombinasi filter → Verify data konsisten
