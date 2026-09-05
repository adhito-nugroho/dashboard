<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get flash message
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Resolve filter values (passed from controller, fallback safely)
$filterBulan        = $filterBulan ?? null;
$filterTahun        = $filterTahun ?? (int) date('Y');
$filterKegiatan     = $filterKegiatan ?? null;
$filterSubKegiatan  = $filterSubKegiatan ?? null;
$filterKegiatanList    = $filterKegiatanList ?? [];
$filterSubKegiatanList = $filterSubKegiatanList ?? [];
$filterStatus          = $filterStatus ?? null;

$statusLabels = [
    'diajukan'     => ['Menunggu Verifikasi', 'warning'],
    'diverifikasi' => ['Terverifikasi', 'success'],
    'ditolak'      => ['Ditolak', 'danger'],
];

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
    4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September',
    10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Build active filter labels
$activeFilterLabels = [];
if ($filterBulan !== null) $activeFilterLabels[] = $namaBulan[$filterBulan] . ' ' . $filterTahun;
if ($filterSubKegiatan !== null) {
    foreach ($filterSubKegiatanList as $sk) {
        if ($sk['id'] == $filterSubKegiatan) { $activeFilterLabels[] = $sk['kode_sub_kegiatan'] . ' ' . $sk['nama_sub_kegiatan']; break; }
    }
} elseif ($filterKegiatan !== null) {
    foreach ($filterKegiatanList as $kg) {
        if ($kg['id'] == $filterKegiatan) { $activeFilterLabels[] = $kg['kode_kegiatan'] . ' ' . $kg['nama_kegiatan']; break; }
    }
}
if ($filterStatus !== null && isset($statusLabels[$filterStatus])) {
    $activeFilterLabels[] = 'Status: ' . $statusLabels[$filterStatus][0];
}
$isFiltered = !empty($activeFilterLabels);
?>

<!-- Alpine.js CDN & Tailwind CDN (preflight disabled to preserve Bootstrap compatibility) -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: {
            preflight: false,
        },
        theme: {
            extend: {
                colors: {
                    navy: {
                        800: '#1e293b',
                        900: '#0f172a',
                    }
                }
            }
        }
    }
</script>

<style>
/* Styling Design System Data Transaksi */
.badge-seksi {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    font-weight: 700;
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    border-radius: 4px;
    padding: 2px 7px;
    display: inline-block;
    line-height: 1.3;
}
.code-rekening {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    background-color: #f8fafc;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    font-size: 0.76rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 5px;
    display: inline-block;
    letter-spacing: 0.02em;
    line-height: 1.3;
}
.badge-status {
    font-size: 0.74rem;
    font-weight: 600;
    padding: 4px 9px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    line-height: 1.2;
    white-space: nowrap;
}
.badge-status-diajukan {
    background-color: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}
.badge-status-diverifikasi {
    background-color: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.badge-status-ditolak {
    background-color: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.table-trx thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.85rem 0.75rem;
    border-bottom: 2px solid #e2e8f0;
    border-top: none;
    white-space: nowrap;
}
.table-trx tbody td {
    padding: 0.85rem 0.75rem;
    font-size: 0.86rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.table-trx tbody tr {
    transition: background-color 0.15s ease;
}
.table-trx tbody tr:hover td {
    background-color: #f8fafc !important;
}
.uraian-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.45;
    max-width: 320px;
    color: #1e293b;
}
.btn-action {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 0.85rem;
    line-height: 1;
    transition: all 0.15s ease;
    text-decoration: none;
    cursor: pointer;
}
.btn-action:hover {
    transform: translateY(-1px);
}
.btn-action-detail {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.btn-action-detail:hover {
    background-color: #e2e8f0;
    color: #0f172a;
}
.btn-action-verif {
    background-color: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.btn-action-verif:hover {
    background-color: #059669;
    color: #ffffff;
    border-color: #059669;
}
.btn-action-reject {
    background-color: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.btn-action-reject:hover {
    background-color: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}
.btn-action-edit {
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.btn-action-edit:hover {
    background-color: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}
.btn-action-delete {
    background-color: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.btn-action-delete:hover {
    background-color: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
}
.trx-checkbox {
    width: 1.2rem !important;
    height: 1.2rem !important;
    border: 2px solid #94a3b8 !important;
    border-radius: 4px !important;
    cursor: pointer !important;
    vertical-align: middle !important;
    transition: all 0.15s ease-in-out !important;
}
.trx-checkbox:hover {
    border-color: #4338ca !important;
    box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.15) !important;
}
.trx-checkbox:checked {
    background-color: #4338ca !important;
    border-color: #4338ca !important;
}
.trx-checkbox:indeterminate {
    background-color: #4338ca !important;
    border-color: #4338ca !important;
}
.filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}
</style>

<div class="transaksi-page-container">
    <!-- Flash Message -->
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show shadow-sm mb-4" role="alert" data-auto-dismiss>
            <div class="d-flex align-items-center">
                <i class="bi bi-<?= $flashType === 'error' ? 'exclamation-circle-fill' : ($flashType === 'success' ? 'check-circle-fill' : 'info-circle-fill') ?> me-2 fs-5"></i>
                <div><?= htmlspecialchars($flashMessage) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="trx-title-chip">
                    <i class="bi bi-receipt-cutoff"></i>
                </span>
                <h1 class="trx-page-title">Data Transaksi</h1>
            </div>
            <p class="trx-page-sub ms-1">
                Monitoring riwayat realisasi anggaran, verifikasi pengajuan transaksi seksi, dan pembukuan BKU
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('transaksi/create') ?>" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 fw-medium shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>Input Transaksi (Batch)</span>
            </a>
        </div>
    </div>

    <!-- Filter Card (Structured 2-row layout) -->
    <div class="card filter-card border-0 shadow-sm mb-4">
        <div class="card-header trx-filter-head d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="text-primary"><i class="bi bi-funnel-fill"></i></span>
                <span class="trx-filter-title">Filter Data Transaksi</span>
                <?php if ($isFiltered): ?>
                    <span class="trx-filter-count">
                        <i class="bi bi-funnel me-1"></i><?= count($activeFilterLabels) ?> Filter Aktif
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-3" id="filterBody">
            <form method="GET" action="<?= base_url('transaksi') ?>" id="filterForm">
                <!-- Baris 1: Kontrol Filter Sejajar -->
                <div class="row g-2 g-lg-3 align-items-end">
                    <!-- Bulan -->
                    <div class="col-12 col-sm-6 col-lg">
                        <label for="filter-bulan" class="form-label trx-filter-label">
                            <i class="bi bi-calendar-month text-muted"></i> Bulan
                        </label>
                        <select name="bulan" id="filter-bulan" class="form-select form-select-sm">
                            <option value="">-- Semua Bulan --</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $filterBulan == $m ? 'selected' : '' ?>>
                                    <?= $namaBulan[$m] ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Tahun -->
                    <div class="col-12 col-sm-6 col-lg-auto" style="min-width: 110px;">
                        <label for="filter-tahun" class="form-label trx-filter-label">
                            <i class="bi bi-calendar3 text-muted"></i> Tahun
                        </label>
                        <select name="tahun" id="filter-tahun" class="form-select form-select-sm">
                            <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
                                <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Kegiatan -->
                    <div class="col-12 col-sm-6 col-lg">
                        <label for="filter-kegiatan" class="form-label trx-filter-label">
                            <i class="bi bi-layers text-muted"></i> Kegiatan
                        </label>
                        <select name="kegiatan_id" id="filter-kegiatan" class="form-select form-select-sm">
                            <option value="">-- Semua Kegiatan --</option>
                            <?php foreach ($filterKegiatanList as $kg): ?>
                                <option value="<?= $kg['id'] ?>" <?= $filterKegiatan == $kg['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kg['kode_kegiatan'] . ' - ' . $kg['nama_kegiatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sub Kegiatan -->
                    <div class="col-12 col-sm-6 col-lg">
                        <label for="filter-sub-kegiatan" class="form-label trx-filter-label">
                            <i class="bi bi-diagram-3 text-muted"></i> Sub Kegiatan
                        </label>
                        <select name="sub_kegiatan_id" id="filter-sub-kegiatan" class="form-select form-select-sm">
                            <option value="">-- Semua Sub Kegiatan --</option>
                            <?php foreach ($filterSubKegiatanList as $sk): ?>
                                <option value="<?= $sk['id'] ?>"
                                        data-kegiatan-id="<?= $sk['kegiatan_id'] ?>"
                                        <?= $filterSubKegiatan == $sk['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sk['kode_sub_kegiatan'] . ' - ' . $sk['nama_sub_kegiatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status (Sejajar rapi bersama filter lainnya) -->
                    <div class="col-12 col-sm-6 col-lg">
                        <label for="filter-status" class="form-label trx-filter-label">
                            <i class="bi bi-shield-check text-muted"></i> Status
                        </label>
                        <select name="status" id="filter-status" class="form-select form-select-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="diajukan" <?= $filterStatus === 'diajukan' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                            <option value="diverifikasi" <?= $filterStatus === 'diverifikasi' ? 'selected' : '' ?>>Terverifikasi</option>
                            <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                        </select>
                    </div>
                </div>

                <!-- Baris 2: Informasi Filter Aktif & Tombol Aksi -->
                <div class="trx-filter-foot d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                    <!-- Status Filter Aktif -->
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <?php if ($isFiltered): ?>
                            <span class="trx-active-hint fw-semibold me-1"><i class="bi bi-funnel me-1"></i>Filter aktif:</span>
                            <?php foreach ($activeFilterLabels as $flabel): ?>
                                <span class="trx-active-chip">
                                    <?= htmlspecialchars($flabel) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="trx-active-hint">
                                <i class="bi bi-info-circle me-1"></i>Menampilkan seluruh riwayat transaksi tanpa filter khusus
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Tombol Aksi: hierarki primary > secondary > tertiary -->
                    <div class="d-flex flex-wrap align-items-center gap-2 w-100 w-md-auto justify-content-md-end">
                        <a href="<?= base_url('transaksi') ?>" class="btn-reset-tertiary" id="btn-reset">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Reset</span>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 px-3 fw-medium" id="btn-unduh-bku" title="Unduh BKU gabungan seluruh kantor sesuai filter Bulan &amp; Tahun aktif">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>Unduh BKU</span>
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 px-3 fw-semibold shadow-sm" id="btn-filter">
                            <i class="bi bi-funnel-fill"></i>
                            <span>Terapkan Filter</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Cascade Script for Kegiatan -> Sub Kegiatan -->
    <script>
    (function() {
        const kegiatanSel   = document.getElementById('filter-kegiatan');
        const subKegSel     = document.getElementById('filter-sub-kegiatan');
        if (kegiatanSel && subKegSel) {
            const allSubOptions = Array.from(subKegSel.querySelectorAll('option[data-kegiatan-id]'));
            function cascade() {
                const selectedKeg = kegiatanSel.value;
                allSubOptions.forEach(opt => {
                    const match = !selectedKeg || opt.dataset.kegiatanId === selectedKeg;
                    opt.style.display = match ? '' : 'none';
                    if (!match && opt.selected) { opt.selected = false; subKegSel.value = ''; }
                });
            }
            kegiatanSel.addEventListener('change', cascade);
            cascade();
        }
    })();
    </script>

    <!-- Bulk Action Toolbar -->
    <div id="bulk-action-bar" class="d-none trx-bulkbar">
        <div class="d-flex align-items-center gap-2">
            <span class="trx-bulkbar-count">
                <i class="bi bi-check2-square"></i>
            </span>
            <span class="trx-bulkbar-text"><span id="selected-count" class="text-danger fw-bold">0</span> transaksi terpilih</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" id="btn-uncheck-all">
                <i class="bi bi-x me-1"></i>Batal Pilih
            </button>
            <button type="button" class="btn btn-danger btn-sm px-3 fw-medium shadow-sm" id="btn-bulk-delete">
                <i class="bi bi-trash me-1"></i>Hapus Terpilih
            </button>
        </div>
    </div>

    <!-- Transactions Table Card -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <?php if (empty($transaksis)): ?>
                <div class="p-5 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light text-muted rounded-circle mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-inbox fs-2"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">
                        <?php if ($filterBulan !== null): ?>
                            Tidak Ada Transaksi pada <?= $namaBulan[$filterBulan] ?> <?= $filterTahun ?>
                        <?php else: ?>
                            Belum Ada Data Transaksi
                        <?php endif; ?>
                    </h6>
                    <p class="text-muted small mb-3">
                        <?php if ($isFiltered): ?>
                            Tidak ditemukan data transaksi yang sesuai dengan kriteria filter aktif saat ini.
                        <?php else: ?>
                            Belum ada riwayat transaksi anggaran yang tercatat di sistem.
                        <?php endif; ?>
                    </p>
                    <?php if ($filterBulan === null && !$isFiltered): ?>
                        <a href="<?= base_url('transaksi/create') ?>" class="btn btn-sm btn-primary px-3 py-2 fw-medium shadow-sm">
                            <i class="bi bi-plus-circle me-1"></i> Input Transaksi Pertama (Batch)
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('transaksi') ?>" class="btn btn-sm btn-outline-secondary px-3 py-2">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Filter
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive trx-table-wrap">
                    <table class="table table-trx align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="3%" class="text-center align-middle">
                                    <input type="checkbox" class="form-check-input trx-checkbox" id="check-all-trx" title="Pilih Semua di Halaman Ini">
                                </th>
                                <th width="3%" class="text-center">No</th>
                                <th width="8%">Tanggal</th>
                                <th width="12%">Seksi</th>
                                <th width="16%">Rekening</th>
                                <th width="24%">Uraian</th>
                                <th width="13%" class="text-end">Nilai (Rp)</th>
                                <th width="9%">Nomor Bukti</th>
                                <th width="11%" class="text-center">Status</th>
                                <th width="9%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalNilai = 0;
                            foreach ($transaksis as $index => $transaksi): 
                                $totalNilai += (float) $transaksi['nilai'];
                                $st = $transaksi['status'] ?? 'diverifikasi';
                            ?>
                                <tr class="trx-row-main">
                                    <!-- Checkbox -->
                                    <td class="text-center align-middle">
                                        <input type="checkbox" class="form-check-input trx-checkbox row-trx-checkbox" value="<?= $transaksi['id'] ?>">
                                    </td>

                                    <!-- No -->
                                    <td class="text-center text-muted font-monospace text-xs"><?= $index + 1 ?></td>

                                    <!-- Tanggal -->
                                    <td>
                                        <span class="fw-medium text-dark"><?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?></span>
                                    </td>

                                    <!-- Seksi (badge kode saja; nama lengkap di strip detail) -->
                                    <td>
                                        <span class="badge-seksi"><?= htmlspecialchars($transaksi['kode_seksi']) ?></span>
                                    </td>

                                    <!-- Rekening (kode saja; hierarki anggaran di strip detail) -->
                                    <td>
                                        <span class="code-rekening"><?= htmlspecialchars($transaksi['kode_rekening']) ?></span>
                                    </td>

                                    <!-- Uraian + tombol expand info anggaran -->
                                    <td>
                                        <div class="d-flex align-items-start gap-1">
                                            <button type="button" class="trx-expand" aria-expanded="false" aria-label="Tampilkan info anggaran" data-bs-toggle="tooltip" title="Info Seksi / Program / Kegiatan">
                                                <i class="bi bi-chevron-down"></i>
                                            </button>
                                            <div class="uraian-clamp" title="<?= htmlspecialchars($transaksi['uraian']) ?>">
                                                <?= htmlspecialchars($transaksi['uraian']) ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Nilai (Right-aligned, tabular font) -->
                                    <td class="text-end">
                                        <span class="fw-bold text-dark font-monospace" style="font-size: 0.9rem;">
                                            Rp <?= number_format($transaksi['nilai'], 0, ',', '.') ?>
                                        </span>
                                    </td>

                                    <!-- Nomor Bukti -->
                                    <td>
                                        <div class="d-flex align-items-center gap-1 text-secondary font-monospace text-xs">
                                            <i class="bi bi-receipt opacity-50"></i>
                                            <span><?= htmlspecialchars($transaksi['nomor_bukti'] ?: '-') ?></span>
                                        </div>
                                    </td>

                                    <!-- Status (Clear hierarchy, no clash with badges) -->
                                    <td class="text-center">
                                        <?php if ($st === 'diajukan'): ?>
                                            <span class="badge-status badge-status-diajukan">
                                                <i class="bi bi-clock-history"></i>
                                                <span>Menunggu Verifikasi</span>
                                            </span>
                                        <?php elseif ($st === 'diverifikasi'): ?>
                                            <span class="badge-status badge-status-diverifikasi">
                                                <i class="bi bi-check-circle-fill"></i>
                                                <span>Terverifikasi</span>
                                            </span>
                                        <?php elseif ($st === 'ditolak'): ?>
                                            <span class="badge-status badge-status-ditolak">
                                                <i class="bi bi-x-circle-fill"></i>
                                                <span>Ditolak</span>
                                            </span>
                                            <?php if (!empty($transaksi['catatan_verifikasi'])): ?>
                                                <div class="small text-danger mt-1 cursor-pointer" data-bs-toggle="tooltip" title="<?= htmlspecialchars($transaksi['catatan_verifikasi']) ?>">
                                                    <i class="bi bi-info-circle me-1"></i><span class="text-xs">Catatan</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($st)) ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Aksi (primer terlihat; sekunder di drawer titik-tiga) -->
                                    <td class="text-center">
                                        <div class="trx-actions d-flex align-items-center justify-content-center gap-1">
                                            <?php if (($transaksi['status'] ?? 'diverifikasi') === 'diajukan'): ?>
                                                <a href="<?= base_url('transaksi/show/' . $transaksi['id']) ?>" 
                                                   class="btn-action btn-action-detail" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Lihat Detail Transaksi">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn-action btn-action-verif btn-verifikasi" 
                                                        data-id="<?= $transaksi['id'] ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Verifikasi Transaksi">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button type="button"
                                                        class="btn-action btn-action-more trx-more-btn"
                                                        aria-expanded="false"
                                                        aria-label="Aksi lainnya"
                                                        data-bs-toggle="tooltip"
                                                        title="Aksi lainnya">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <span class="trx-more-pane">
                                                    <button type="button" 
                                                            class="btn-action btn-action-reject btn-tolak" 
                                                            data-id="<?= $transaksi['id'] ?>"
                                                            data-bs-toggle="tooltip"
                                                            title="Tolak Transaksi">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </span>
                                            <?php else: ?>
                                                <a href="<?= base_url('transaksi/show/' . $transaksi['id']) ?>" 
                                                   class="btn-action btn-action-detail" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Lihat Detail Transaksi">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?= base_url('transaksi/edit/' . $transaksi['id']) ?>" 
                                                   class="btn-action btn-action-edit" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Edit Transaksi">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn-action btn-action-more trx-more-btn"
                                                        aria-expanded="false"
                                                        aria-label="Aksi lainnya"
                                                        data-bs-toggle="tooltip"
                                                        title="Aksi lainnya">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <span class="trx-more-pane">
                                                    <a href="<?= base_url('transaksi/delete/' . $transaksi['id']) ?>"
                                                       class="btn-action btn-action-delete"
                                                       data-bs-toggle="tooltip"
                                                       title="Hapus Transaksi"
                                                       onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Strip detail info anggaran (expand-on-click) -->
                                <tr class="trx-extra">
                                    <td colspan="10">
                                        <div class="trx-extra-grid">
                                            <div class="trx-extra-item">
                                                <span>Seksi</span>
                                                <strong><?= htmlspecialchars(($transaksi['kode_seksi'] ?? '-') . ' — ' . ($transaksi['nama_seksi'] ?? '-')) ?></strong>
                                            </div>
                                            <div class="trx-extra-item">
                                                <span>Program</span>
                                                <strong><code><?= htmlspecialchars($transaksi['kode_program'] ?? '-') ?></code> <?= htmlspecialchars($transaksi['nama_program'] ?? '') ?></strong>
                                            </div>
                                            <div class="trx-extra-item">
                                                <span>Kegiatan</span>
                                                <strong><code><?= htmlspecialchars($transaksi['kode_kegiatan'] ?? '-') ?></code> <?= htmlspecialchars($transaksi['nama_kegiatan'] ?? '') ?></strong>
                                            </div>
                                            <div class="trx-extra-item">
                                                <span>Sub Kegiatan</span>
                                                <strong><code><?= htmlspecialchars($transaksi['kode_sub_kegiatan'] ?? '-') ?></code> <?= htmlspecialchars($transaksi['nama_sub_kegiatan'] ?? '') ?></strong>
                                            </div>
                                            <div class="trx-extra-item">
                                                <span>Rekening</span>
                                                <strong><code><?= htmlspecialchars($transaksi['kode_rekening'] ?? '-') ?></code> <?= htmlspecialchars($transaksi['nama_rekening'] ?? '') ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="6" class="text-end py-3 fw-bold text-secondary">
                                    Total Nilai<?= $filterBulan !== null ? ' (' . $namaBulan[$filterBulan] . ' ' . $filterTahun . ')' : '' ?>:
                                </td>
                                <td class="text-end py-3 fw-bold text-dark font-monospace" style="font-size: 0.95rem;">
                                    Rp <?= number_format($totalNilai, 0, ',', '.') ?>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                    <div class="card-footer bg-white border-top py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="trx-page-info">
                            Menampilkan halaman <strong class="text-dark"><?= $pagination['page'] ?></strong> dari <strong class="text-dark"><?= $pagination['totalPages'] ?></strong> (Total <strong class="text-dark"><?= $pagination['total'] ?></strong> transaksi)
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $pagination['page'] <= 1 ? '#' : $pagination['baseUrl'] . '&page=' . ($pagination['page'] - 1) ?>" aria-label="Sebelumnya">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                                    <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $pagination['baseUrl'] . '&page=' . $p ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $pagination['page'] >= $pagination['totalPages'] ? '#' : $pagination['baseUrl'] . '&page=' . ($pagination['page'] + 1) ?>" aria-label="Berikutnya">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tolak -->
<div class="modal fade" id="modalTolak" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="formTolak" method="POST">
            <div class="modal-header trx-modal-head trx-modal-head-danger-soft">
                <h6 class="modal-title"><i class="bi bi-x-circle me-2"></i>Tolak Transaksi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label for="catatanVerifikasi" class="form-label trx-modal-label">
                        Catatan Penolakan <span class="text-danger">*</span>
                    </label>
                    <textarea name="catatan_verifikasi" id="catatanVerifikasi" class="form-control" rows="3" placeholder="Sebutkan alasan penolakan untuk diperbaiki oleh seksi pengaju..." required></textarea>
                </div>
            </div>
            <div class="modal-footer trx-modal-foot">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-danger px-3 fw-medium">Tolak Transaksi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Banyak -->
<div class="modal fade" id="modalBulkDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow" id="formBulkDelete" method="POST" action="<?= base_url('transaksi/delete-batch') ?>">
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? base_url('transaksi')) ?>">
            <div class="modal-header trx-modal-head trx-modal-head-danger">
                <h6 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Banyak Transaksi</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <p class="mb-2">Apakah Anda yakin ingin menghapus <strong id="modal-delete-count" class="text-danger">0</strong> transaksi yang dipilih?</p>
                <div class="alert alert-warning py-2 mb-0 small d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-circle text-warning fs-5"></i>
                    <div>Data transaksi yang dihapus tidak dapat dikembalikan.</div>
                </div>
                <div id="bulk-delete-inputs"></div>
            </div>
            <div class="modal-footer trx-modal-foot">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-danger px-3 fw-medium">
                    <i class="bi bi-trash me-1"></i> Ya, Hapus Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const VERIF_BASE = '<?= rtrim(base_url(), '/') ?>';

    // Inisialisasi Tooltips Bootstrap
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Verifikasi Transaksi
    document.querySelectorAll('.btn-verifikasi').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            if (confirm('Verifikasi transaksi ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = VERIF_BASE + '/transaksi/verifikasi/' + id;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Tolak Transaksi Modal
    const modalTolakEl = document.getElementById('modalTolak');
    document.querySelectorAll('.btn-tolak').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const form = document.getElementById('formTolak');
            if (form) form.action = VERIF_BASE + '/transaksi/tolak/' + id;
            if (modalTolakEl && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getInstance(modalTolakEl) || new bootstrap.Modal(modalTolakEl);
                modal.show();
            }
        });
    });

    // Bulk Delete Selection Logic
    const checkAll = document.getElementById('check-all-trx');
    const rowCheckboxes = document.querySelectorAll('.row-trx-checkbox');
    const bulkBar = document.getElementById('bulk-action-bar');
    const selectedCountEl = document.getElementById('selected-count');
    const modalDeleteCountEl = document.getElementById('modal-delete-count');
    const btnUncheck = document.getElementById('btn-uncheck-all');
    const btnBulkDelete = document.getElementById('btn-bulk-delete');
    const modalBulkDeleteEl = document.getElementById('modalBulkDelete');
    const inputsContainer = document.getElementById('bulk-delete-inputs');

    function updateSelectionState() {
        const checkedBoxes = document.querySelectorAll('.row-trx-checkbox:checked');
        const count = checkedBoxes.length;

        if (selectedCountEl) selectedCountEl.textContent = count;
        if (modalDeleteCountEl) modalDeleteCountEl.textContent = count;

        if (bulkBar) {
            if (count > 0) {
                bulkBar.classList.remove('d-none');
            } else {
                bulkBar.classList.add('d-none');
            }
        }

        if (checkAll && rowCheckboxes.length > 0) {
            checkAll.checked = count === rowCheckboxes.length;
            checkAll.indeterminate = count > 0 && count < rowCheckboxes.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowCheckboxes.forEach(cb => {
                cb.checked = checkAll.checked;
            });
            updateSelectionState();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectionState);
    });

    if (btnUncheck) {
        btnUncheck.addEventListener('click', function () {
            if (checkAll) {
                checkAll.checked = false;
                checkAll.indeterminate = false;
            }
            rowCheckboxes.forEach(cb => {
                cb.checked = false;
            });
            updateSelectionState();
        });
    }

    if (btnBulkDelete) {
        btnBulkDelete.addEventListener('click', function () {
            const checkedBoxes = document.querySelectorAll('.row-trx-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Pilih setidaknya satu transaksi terlebih dahulu.');
                return;
            }

            if (inputsContainer) {
                inputsContainer.innerHTML = '';
                checkedBoxes.forEach(cb => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'ids[]';
                    hiddenInput.value = cb.value;
                    inputsContainer.appendChild(hiddenInput);
                });
            }

            if (modalBulkDeleteEl && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getInstance(modalBulkDeleteEl) || new bootstrap.Modal(modalBulkDeleteEl);
                modal.show();
            }
        });
    }

    // Initial check state
    updateSelectionState();

    // Tombol Unduh BKU CDK
    const btnBku = document.getElementById('btn-unduh-bku');
    if (btnBku) {
        btnBku.addEventListener('click', function () {
            const selBulan = document.getElementById('filter-bulan').value;
            const selTahun = document.getElementById('filter-tahun').value;
            if (!selBulan || !selTahun) {
                alert('Pilih Bulan dan Tahun terlebih dahulu untuk mengunduh BKU');
                return;
            }
            const params = new URLSearchParams();
            params.set('bulan', selBulan);
            params.set('tahun', selTahun);
            const keg = document.getElementById('filter-kegiatan').value;
            if (keg) params.set('kegiatan_id', keg);
            const subKeg = document.getElementById('filter-sub-kegiatan').value;
            if (subKeg) params.set('sub_kegiatan_id', subKeg);
            const status = document.getElementById('filter-status').value;
            if (status) params.set('status', status);
            window.location.href = VERIF_BASE + '/transaksi/bku-cdk?' + params.toString();
        });
    }
});
</script>

<script>
// Tahap 3 — toggle strip detail & drawer aksi (class-based, tanpa ID; logic lama tak disentuh)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.trx-expand').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = btn.closest('tr');
            if (!row) return;
            const open = row.classList.toggle('trx-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
    document.querySelectorAll('.trx-more-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const box = btn.closest('.trx-actions');
            if (!box) return;
            const open = box.classList.toggle('trx-more-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
});
</script>
