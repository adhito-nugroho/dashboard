# Script untuk Mengaktifkan Filter Seksi di Dashboard
# Jalankan script ini SETELAH migration database selesai dan data program sudah memiliki seksi_id

## File yang akan diupdate:
## 1. app/Models/Pagu.php - Tambah program_seksi_id ke query
## 2. app/Controllers/DashboardController.php - Tambah seksi filter
## 3. views/dashboard/index.php - Tambah dropdown seksi
## 4. public/index.php - Tambah seksiModel ke DashboardController

## INSTRUKSI MANUAL:

### 1. Update app/Models/Pagu.php
Ubah query SELECT di method getAll() dan getById():

```php
// BEFORE:
SELECT p.*, 
       r.kode_rekening, r.nama_rekening,
       sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
       k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
       pr.id AS program_id, pr.kode_program, pr.nama_program

// AFTER:
SELECT p.*, 
       r.kode_rekening, r.nama_rekening,
       sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
       k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
       pr.id AS program_id, pr.kode_program, pr.nama_program, pr.seksi_id AS program_seksi_id
```

### 2. Update app/Controllers/DashboardController.php

A. Tambahkan use statement:
```php
use App\Models\Seksi;
```

B. Tambahkan property:
```php
private Seksi $seksiModel;
```

C. Update constructor:
```php
public function __construct(
    Pagu $paguModel,
    Rak $rakModel,
    Transaksi $transaksiModel,
    Seksi $seksiModel,  // TAMBAHKAN INI
    Program $programModel,
    Kegiatan $kegiatanModel,
    SubKegiatan $subKegiatanModel
) {
    $this->paguModel = $paguModel;
    $this->rakModel = $rakModel;
    $this->transaksiModel = $transaksiModel;
    $this->seksiModel = $seksiModel;  // TAMBAHKAN INI
    $this->programModel = $programModel;
    $this->kegiatanModel = $kegiatanModel;
    $this->subKegiatanModel = $subKegiatanModel;
}
```

D. Update index() method - tambahkan seksi_id ke filters:
```php
$filters = [
    'seksi_id' => isset($_GET['seksi_id']) && $_GET['seksi_id'] !== '' ? (int) $_GET['seksi_id'] : null,  // TAMBAHKAN INI
    'program_id' => isset($_GET['program_id']) && $_GET['program_id'] !== '' ? (int) $_GET['program_id'] : null,
    'kegiatan_id' => isset($_GET['kegiatan_id']) && $_GET['kegiatan_id'] !== '' ? (int) $_GET['kegiatan_id'] : null,
    'sub_kegiatan_id' => isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null,
];
```

E. Update getFilterOptions():
```php
private function getFilterOptions(array $filters): array {
    return [
        'seksi' => $this->seksiModel->getAll(),  // TAMBAHKAN INI
        'program' => $this->programModel->getAll(),
        'kegiatan' => $this->kegiatanModel->getAll(),
        'sub_kegiatan' => $this->subKegiatanModel->getAll()
    ];
}
```

F. Update matchesFilters() - tambahkan seksi check:
```php
private function matchesFilters(array $pagu, array $filters): bool {
    // Check seksi filter - TAMBAHKAN BLOK INI
    if (!empty($filters['seksi_id'])) {
        if (!isset($pagu['program_seksi_id']) || $pagu['program_seksi_id'] != $filters['seksi_id']) {
            return false;
        }
    }
    
    // Check program filter
    if (!empty($filters['program_id'])) {
        if (!isset($pagu['program_id']) || $pagu['program_id'] != $filters['program_id']) {
            return false;
        }
    }
    
    // ... rest of the code
}
```

G. Update getBreakdownData() - tambahkan seksi grouping:
```php
private function getBreakdownData(int $tahun, array $filters): array {
    $pagus = $this->paguModel->getAll();
    $breakdown = [];
    
    foreach ($pagus as $pagu) {
        if ($pagu['tahun'] == $tahun && $this->matchesFilters($pagu, $filters)) {
            // TAMBAHKAN BLOK INI
            if (empty($filters['seksi_id'])) {
                // Group by seksi
                if (isset($pagu['program_seksi_id'])) {
                    $seksi = $this->seksiModel->getById($pagu['program_seksi_id']);
                    if ($seksi) {
                        $key = $seksi['nama_seksi'];
                        if (!isset($breakdown[$key])) {
                            $breakdown[$key] = ['pagu' => 0, 'realisasi' => 0];
                        }
                        $breakdown[$key]['pagu'] += (float) $pagu['nilai_pagu'];
                        $breakdown[$key]['realisasi'] += $this->transaksiModel->getTotalByRekeningAndYear($pagu['rekening_id'], $tahun);
                    }
                }
            } elseif (empty($filters['program_id'])) {
            // ... rest of the code
```

### 3. Update views/dashboard/index.php

A. Ubah col-md-4 menjadi col-md-3 untuk semua filter

B. Tambahkan dropdown seksi SEBELUM dropdown program:
```php
<div class="col-md-3">
    <label for="seksi_id" class="form-label">
        <i class="bi bi-building me-1"></i>Seksi
    </label>
    <select class="form-select" id="seksi_id" name="seksi_id" onchange="this.form.submit()">
        <option value="">-- Semua Seksi --</option>
        <?php foreach ($filterOptions['seksi'] as $seksi): ?>
            <option value="<?= $seksi['id'] ?>" <?= ($filters['seksi_id'] ?? '') == $seksi['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($seksi['nama_seksi']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
```

C. Update filter active check:
```php
<?php if (!empty($filters['seksi_id']) || !empty($filters['program_id']) || !empty($filters['kegiatan_id']) || !empty($filters['sub_kegiatan_id'])): ?>
```

D. Update breakdown description:
```php
<?php 
if (empty($filters['seksi_id'])) {
    echo 'Breakdown per Seksi';
} elseif (empty($filters['program_id'])) {
    echo 'Breakdown per Program';
} elseif (empty($filters['kegiatan_id'])) {
    echo 'Breakdown per Kegiatan';
} else {
    echo 'Breakdown per Sub Kegiatan';
}
?>
```

### 4. Update public/index.php

Update DashboardController initialization:
```php
$dashboardController = new DashboardController(
    $paguModel, 
    $rakModel, 
    $transaksiModel, 
    $seksiModel,  // TAMBAHKAN INI
    $programModel, 
    $kegiatanModel, 
    $subKegiatanModel
);
```

## SELESAI!

Setelah semua perubahan di atas dilakukan:
1. Refresh halaman dashboard
2. Filter seksi seharusnya sudah muncul
3. Test semua kombinasi filter untuk memastikan berfungsi dengan baik
