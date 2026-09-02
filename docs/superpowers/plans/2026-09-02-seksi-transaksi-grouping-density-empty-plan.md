# UI/UX Transaksi Saya (Grouping ST, Density Toggle, Contextual Empty State) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan fitur collapsible grouping untuk transaksi Surat Tugas yang sama, toggle mode kerapatan (normal vs compact), serta contextual empty state pada halaman "Transaksi Saya" Seksi tanpa mengubah query SQL atau alur bisnis.

**Architecture:** Menggunakan pengelompokan PHP array di layer view `views/seksi/transaksi_index.php` untuk grouping baris, Alpine.js untuk toggle collapse/expand sub-baris, CSS utility class `.table-compact` dengan persistensi `localStorage` untuk density toggle, dan conditional empty state card berdasarkan tab aktif.

**Tech Stack:** PHP 8 native, Bootstrap 5.3, Bootstrap Icons, Alpine.js 3 (CDN), JavaScript (localStorage).

## Global Constraints

- Jangan mengubah query database (SQL), struktur tabel, atau controller.
- Jangan mengubah endpoint atau alur Edit/Hapus/Tambah transaksi.
- Pertahankan perbaikan sebelumnya: truncate uraian 1 baris dengan native `title`, ikon aksi sejajar horizontal, kode rekening kecil abu-abu, dan tombol Cari outline.
- Status grup campuran menggunakan Prioritas Perhatian: Ditolak (merah) > Menunggu Verifikasi (kuning) > Diverifikasi (hijau).
- Aksi Edit/Hapus tetap berjalan individual di level sub-baris per transaksi ID.

---

### Task 1: Alpine.js CDN & Density Toggle (CSS + JavaScript Persistence)

**Files:**
- Modify: `views/seksi/transaksi_index.php`

**Interfaces:**
- Produces: CSS `.table-compact`, toggle button `#btnToggleDensity`, helper `initDensity()`

- [ ] **Step 1: Tambahkan CDN Alpine.js dan CSS `.table-compact`**

Tambahkan CDN Alpine.js di bagian atas atau bawah file:
```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```
Tambahkan CSS di dalam `<style>`:
```css
.table-compact th,
.table-compact td {
    padding-top: 0.35rem !important;
    padding-bottom: 0.35rem !important;
    padding-left: 0.5rem !important;
    padding-right: 0.5rem !important;
    font-size: 0.8rem !important;
}
.table-compact .btn-action-icon {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.75rem !important;
}
.table-compact .badge {
    padding: 0.2rem 0.5rem !important;
    font-size: 0.7rem !important;
}
```

- [ ] **Step 2: Tambahkan Tombol Toggle Density di Filter Bar**

Di samping tombol BKU (`#btnUnduhBku`), tambahkan tombol toggle:
```html
<button type="button" id="btnToggleDensity" class="btn btn-outline-secondary btn-sm flex-shrink-0" title="Ubah Kerapatan Tampilan (Normal / Compact)">
    <i class="bi bi-distribute-vertical" id="densityIcon"></i>
</button>
```

- [ ] **Step 3: Tambahkan Script Persistensi Density di `localStorage`**

Di dalam script DOMContentLoaded:
```javascript
const densityBtn = document.getElementById('btnToggleDensity');
const densityIcon = document.getElementById('densityIcon');
const txTable = document.querySelector('.table-responsive table');

function applyDensity(mode) {
    if (!txTable) return;
    if (mode === 'compact') {
        txTable.classList.add('table-compact');
        if (densityIcon) densityIcon.className = 'bi bi-list-ul';
    } else {
        txTable.classList.remove('table-compact');
        if (densityIcon) densityIcon.className = 'bi bi-distribute-vertical';
    }
    localStorage.setItem('seksi_tx_density', mode);
}

const savedDensity = localStorage.getItem('seksi_tx_density') || 'normal';
applyDensity(savedDensity);

if (densityBtn) {
    densityBtn.addEventListener('click', function() {
        const isCompact = txTable && txTable.classList.contains('table-compact');
        applyDensity(isCompact ? 'normal' : 'compact');
    });
}
```

- [ ] **Step 4: Tes dan Validasi Sintaks PHP**
Run: `D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l views/seksi/transaksi_index.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**
```bash
git add views/seksi/transaksi_index.php
git commit -m "feat(ui): tambahkan toggle density mode normal/compact dengan persistensi localStorage"
```

---

### Task 2: Logic Grouping Surat Tugas & Resolver Status Campuran

**Files:**
- Modify: `views/seksi/transaksi_index.php`

**Interfaces:**
- Produces: `$groupedTransaksis` (array data yang terkelompokkan), helper `$resolveGroupBadge`

- [ ] **Step 1: Definisikan struktur grouping di PHP sebelum render tabel**

```php
// Pengelompokan baris transaksi berdasarkan Nomor Surat Tugas
$groupedItems = [];
$stCounts = [];

// Hitung kemunculan masing-masing nomor ST
foreach ($transaksis as $t) {
    $stNum = trim((string)($t['nomor_surat_tugas'] ?? ''));
    if ($stNum !== '') {
        $stCounts[$stNum] = ($stCounts[$stNum] ?? 0) + 1;
    }
}

// Kelompokkan item
$processedGroups = [];
foreach ($transaksis as $t) {
    $stNum = trim((string)($t['nomor_surat_tugas'] ?? ''));
    // Hanya group jika ada nomor ST dan terdapat >= 2 transaksi dengan nomor ST tsb
    if ($stNum !== '' && ($stCounts[$stNum] ?? 0) >= 2) {
        if (!isset($processedGroups[$stNum])) {
            $processedGroups[$stNum] = true;
            // Kumpulkan seluruh anggota transaksi dengan ST ini
            $members = array_filter($transaksis, fn($item) => trim((string)($item['nomor_surat_tugas'] ?? '')) === $stNum);
            $groupedItems[] = [
                'type'    => 'group',
                'st'      => $stNum,
                'members' => array_values($members),
            ];
        }
    } else {
        $groupedItems[] = [
            'type' => 'single',
            'data' => $t,
        ];
    }
}
```

- [ ] **Step 2: Buat resolver status campuran untuk baris parent grup**

Sesuai konfirmasi: Ditolak (prioritas 1) > Menunggu Verifikasi (prioritas 2) > Diverifikasi (prioritas 3):
```php
$resolveGroupStatus = function(array $members): array {
    $hasDitolak = false;
    $hasDiajukan = false;
    foreach ($members as $m) {
        $st = $m['status'] ?? 'diverifikasi';
        if ($st === 'ditolak') $hasDitolak = true;
        if ($st === 'diajukan') $hasDiajukan = true;
    }
    if ($hasDitolak) {
        return ['Ditolak', 'danger', '#fef2f2', '#991b1b', '#fecaca'];
    }
    if ($hasDiajukan) {
        return ['Menunggu Verifikasi', 'warning', '#fefce8', '#854d0e', '#fef08a'];
    }
    return ['Diverifikasi', 'success', '#f0fdf4', '#166534', '#bbf7d0'];
};
```

- [ ] **Step 3: Tes dan Validasi Sintaks PHP**
Run: `D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l views/seksi/transaksi_index.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**
```bash
git add views/seksi/transaksi_index.php
git commit -m "feat(ui): siapkan logic grouping Surat Tugas dan resolver status campuran prioritas perhatian"
```

---

### Task 3: Render Tampilan Tabel Grouping Collapsible (Desktop & Mobile)

**Files:**
- Modify: `views/seksi/transaksi_index.php`

**Interfaces:**
- Consumes: `$groupedItems`, `$resolveGroupStatus`
- Produces: HTML render table dengan Alpine.js `x-data="{ open: false }"`

- [ ] **Step 1: Render Desktop Table dengan Alpine.js collapsible**

Untuk setiap item dalam `$groupedItems`:
- Jika `type === 'single'`: Render baris tunggal seperti biasa.
- Jika `type === 'group'`:
  - Hitung total nilai: `array_sum(array_column($members, 'nilai'))`.
  - Baris Parent:
    - Kolom Tanggal: Tanggal transaksi pertama + nomor ST badge.
    - Kolom Sub Kegiatan & Rekening: Dari anggota pertama (shared).
    - Kolom Uraian: Chip "Perjadin" + Badge "Grup ST: [No ST]" + info jumlah pegawai ("N Pegawai") + tombol toggle expand/collapse (`x-on:click="open = !open"`).
    - Kolom Nilai: Total nilai akumulatif seluruh anggota grup (Rp ...).
    - Kolom Status: Badge prioritas perhatian hasil `$resolveGroupStatus($members)`.
    - Kolom Aksi: Tombol expand/collapse indicator (`bi-chevron-down` / `bi-chevron-up`).
  - Sub-baris per pegawai (`x-show="open" x-cloak`):
    - Background lembut `#f8fafc` dengan indentasi visual `↳`.
    - Menampilkan Nomor Bukti, Nama Pegawai (`👤 [Nama]`), Nilai transaksi pegawai tersebut, Status spesifik transaksi tersebut, serta tombol Aksi Edit, Hapus, dan Unduh Excel individual (berdasarkan `$member['id']`).

- [ ] **Step 2: Render Mobile Card List dengan Grouping Collapsible**

Di tampilan mobile (`d-md-none`):
- Grup ditampilkan sebagai kartu utama ST dengan tombol toggle accordion.
- Saat dibuka, menampilkan daftar kartu sub-transaksi masing-masing pegawai lengkap dengan aksi Edit, Hapus, dan Unduh Excel.

- [ ] **Step 3: Tes dan Validasi Sintaks PHP**
Run: `D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l views/seksi/transaksi_index.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**
```bash
git add views/seksi/transaksi_index.php
git commit -m "feat(ui): implementasikan render tabel collapsible grouping Surat Tugas dengan Alpine.js"
```

---

### Task 4: Contextual Empty State Berdasarkan Tab Aktif

**Files:**
- Modify: `views/seksi/transaksi_index.php`

**Interfaces:**
- Consumes: `$curStatus`, `$hasFilter`, `$transaksis`

- [ ] **Step 1: Siapkan variabel teks dan ikon kontekstual**

```php
$emptyTitle = 'Belum Ada Transaksi yang Diajukan';
$emptySubtitle = 'Seksi Anda belum pernah mengajukan transaksi belanja. Klik tombol di bawah untuk mulai menginput transaksi baru.';
$emptyIcon = 'bi-receipt-cutoff';
$emptyIconColor = '#3b82f6';
$emptyIconBg = '#eff6ff';

if ($curStatus === 'diverifikasi') {
    $emptyTitle = 'Belum Ada Transaksi yang Diverifikasi';
    $emptySubtitle = 'Transaksi belanja yang telah disetujui oleh admin/verifikator akan tampil di tab ini.';
    $emptyIcon = 'bi-check2-circle';
    $emptyIconColor = '#16a34a';
    $emptyIconBg = '#f0fdf4';
} elseif ($curStatus === 'ditolak') {
    $emptyTitle = 'Belum Ada Transaksi yang Ditolak';
    $emptySubtitle = 'Bagus! Tidak ada transaksi belanja yang ditolak oleh verifikator.';
    $emptyIcon = 'bi-shield-check';
    $emptyIconColor = '#059669';
    $emptyIconBg = '#ecfdf5';
} elseif ($curStatus === 'diajukan') {
    $emptyTitle = 'Tidak Ada Transaksi yang Menunggu Verifikasi';
    $emptySubtitle = 'Semua pengajuan transaksi belanja Anda saat ini sudah diproses.';
    $emptyIcon = 'bi-inbox';
    $emptyIconColor = '#d97706';
    $emptyIconBg = '#fef3c7';
} elseif ($hasFilter) {
    $emptyTitle = 'Tidak ada transaksi yang cocok dengan filter ini.';
    $emptySubtitle = 'Coba ubah filter status, bulan, tahun, atau kata kunci pencarian Anda.';
    $emptyIcon = 'bi-search';
    $emptyIconColor = '#64748b';
    $emptyIconBg = '#f1f5f9';
}
```

- [ ] **Step 2: Render Empty State Card di Tengah Area Tabel**

Gantikan area tabel ketika data kosong:
```html
<div class="text-center py-5 px-3">
    <div style="width:68px;height:68px;border-radius:50%;background:<?= $emptyIconBg ?>;color:<?= $emptyIconColor ?>;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:1rem;">
        <i class="bi <?= $emptyIcon ?>"></i>
    </div>
    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($emptyTitle) ?></h5>
    <p class="text-muted mx-auto mb-3" style="max-width:440px;font-size:0.875rem;">
        <?= htmlspecialchars($emptySubtitle) ?>
    </p>
    <?php if ($hasFilter): ?>
        <a href="<?= base_url('seksi/transaksi') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
        </a>
    <?php else: ?>
        <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary px-3 py-2 fw-semibold" style="border-radius:8px;">
            <i class="bi bi-plus-circle me-1"></i> Tambah Transaksi Sekarang
        </a>
    <?php endif; ?>
</div>
```

- [ ] **Step 3: Tes dan Validasi Sintaks PHP**
Run: `D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l views/seksi/transaksi_index.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**
```bash
git add views/seksi/transaksi_index.php
git commit -m "feat(ui): tambahkan contextual empty state untuk setiap tab dan filter transaksi"
```

---

### Task 5: Pengujian Akhir & Verifikasi Keseluruhan

- [ ] **Step 1: Jalankan linter dan PHP syntax check di seluruh file terkait**
Run: `D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l views/seksi/transaksi_index.php`
- [ ] **Step 2: Cek git status dan pastikan working tree bersih**
- [ ] **Step 3: Dokumentasikan walkthrough hasil akhir untuk user**
