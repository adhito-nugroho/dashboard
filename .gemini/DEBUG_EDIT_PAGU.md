# Debug Edit Pagu

## 🔍 Debugging Steps

### 1. Check Browser Console
Buka Developer Tools (F12) → Console tab

Saat submit form, Anda akan melihat:
```
Form validation passed, submitting...
Form action: http://localhost/pagu/update/1
Form data: {
  program_id: "1",
  kegiatan_id: "2",
  sub_kegiatan_id: "3",
  rekening_id: "4",
  tahun: "2026",
  nilai_pagu: "1.000.000"
}
```

### 2. Check PHP Error Log
Lokasi: `d:\laragon\www\dashboard-anggaran\storage\logs\` atau `d:\laragon\logs\`

Cari log seperti:
```
Updating Pagu ID: 1
Rekening ID: 4
Tahun: 2026
Nilai Pagu (cleaned): 1000000
Pagu updated successfully
```

Atau jika ada error:
```
Exception updating pagu: [error message]
```

### 3. Check Database
Jalankan query di Navicat:
```sql
SELECT * FROM pagu WHERE id = 1;
```

Verify apakah nilai_pagu berubah.

### 4. Check Session Flash Message
Tambahkan debug di `views/pagu/index.php`:
```php
<?php
var_dump($_SESSION);
echo "Flash Message: " . ($_SESSION['flash_message'] ?? 'none');
echo "Flash Type: " . ($_SESSION['flash_type'] ?? 'none');
?>
```

## 🔧 Possible Issues

### Issue 1: Form tidak ter-submit
**Symptom:** Console log "Form validation failed"
**Solution:** Cek field mana yang invalid (akan ada class `is-invalid`)

### Issue 2: Routing tidak match
**Symptom:** 404 error atau halaman blank
**Solution:** Verify routing di `public/index.php` line 263

### Issue 3: Database update gagal
**Symptom:** Error log "Exception updating pagu"
**Solution:** Cek error message di log

### Issue 4: Flash message tidak muncul
**Symptom:** Data ter-update tapi tidak ada notifikasi
**Solution:** 
- Cek session sudah start
- Cek flash message di session
- Cek view menampilkan flash message

## ✅ Expected Behavior

1. User klik "Simpan Perubahan"
2. Console log: "Form validation passed"
3. Form submit ke `/pagu/update/1`
4. PHP log: "Updating Pagu ID: 1"
5. PHP log: "Pagu updated successfully"
6. Redirect ke `/pagu`
7. Flash message muncul: "Pagu berhasil diperbarui"
8. Data ter-update di database

## 🚀 Quick Test

1. Edit pagu dengan ID 1
2. Ubah nilai pagu dari 1.000.000 ke 2.000.000
3. Klik "Simpan Perubahan"
4. Buka Console (F12)
5. Cek error log di Laragon
6. Verify database

## 📝 Debug Checklist

- [ ] Browser console menampilkan "Form validation passed"
- [ ] Form action URL benar: `/pagu/update/{id}`
- [ ] PHP error log menampilkan "Updating Pagu ID: X"
- [ ] PHP error log menampilkan "Pagu updated successfully"
- [ ] Database ter-update dengan nilai baru
- [ ] Redirect ke `/pagu` berhasil
- [ ] Flash message muncul di halaman pagu
