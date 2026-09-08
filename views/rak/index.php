<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get flash message
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Data sudah dikelompokkan per rekening+tahun di controller (satu baris = 12 bulan)
$groupedRak = $raks ?? [];

// Resolve filter values
$filterTahun       = $filterTahun ?? null;
$filterKegiatan    = $filterKegiatan ?? null;
$filterSubKegiatan = $filterSubKegiatan ?? null;
$filterKegiatanList    = $filterKegiatanList ?? [];
$filterSubKegiatanList = $filterSubKegiatanList ?? [];

// Build active filter label
$activeFilterLabels = [];
if ($filterTahun !== null) $activeFilterLabels[] = 'Tahun ' . $filterTahun;
if ($filterSubKegiatan !== null) {
    foreach ($filterSubKegiatanList as $sk) {
        if ($sk['id'] == $filterSubKegiatan) { $activeFilterLabels[] = $sk['kode_sub_kegiatan'] . ' ' . $sk['nama_sub_kegiatan']; break; }
    }
} elseif ($filterKegiatan !== null) {
    foreach ($filterKegiatanList as $kg) {
        if ($kg['id'] == $filterKegiatan) { $activeFilterLabels[] = $kg['kode_kegiatan'] . ' ' . $kg['nama_kegiatan']; break; }
    }
}
$isFiltered = !empty($activeFilterLabels);
?>

<div class="container-fluid py-4">
    <!-- Flash Message -->
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert" data-auto-dismiss>
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-calendar-month text-primary me-2"></i>Data RAK
                </h2>
                <p class="text-muted mb-0">Kelola rencana anggaran kas bulanan</p>
            </div>
            <a href="<?= base_url('rak/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Tambah RAK
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="<?= base_url('rak') ?>" id="filterForm">
                <div class="row g-3">
                    <!-- Tahun -->
                    <div class="col-12 col-md-4">
                        <label for="filter-tahun" class="form-label fw-semibold mb-1">
                            <i class="bi bi-calendar-year me-1 text-primary"></i>Tahun
                        </label>
                        <select name="tahun" id="filter-tahun" class="form-select w-100">
                            <option value="">-- Semua --</option>
                            <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
                                <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <!-- Kegiatan -->
                    <div class="col-12 col-md-4">
                        <label for="filter-kegiatan" class="form-label fw-semibold mb-1">
                            <i class="bi bi-layers me-1 text-primary"></i>Kegiatan
                        </label>
                        <select name="kegiatan_id" id="filter-kegiatan" class="form-select w-100">
                            <option value="">-- Semua Kegiatan --</option>
                            <?php foreach ($filterKegiatanList as $kg): ?>
                                <option value="<?= $kg['id'] ?>" <?= $filterKegiatan == $kg['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kg['kode_kegiatan'] . ' - ' . $kg['nama_kegiatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Sub Kegiatan -->
                    <div class="col-12 col-md-4">
                        <label for="filter-sub-kegiatan" class="form-label fw-semibold mb-1">
                            <i class="bi bi-diagram-3 me-1 text-primary"></i>Sub Kegiatan
                        </label>
                        <select name="sub_kegiatan_id" id="filter-sub-kegiatan" class="form-select w-100">
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
                    <!-- Buttons -->
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="btn-filter">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="<?= base_url('rak') ?>" class="btn btn-outline-secondary" id="btn-reset">
                            <i class="bi bi-x-circle me-1"></i>Reset
                        </a>
                    </div>
                </div>
                <?php if ($isFiltered): ?>
                    <div class="mt-2">
                        <span class="badge bg-primary px-3 py-2 fs-6">
                            <i class="bi bi-filter-circle me-1"></i>
                            Filter aktif: <?= htmlspecialchars(implode(' | ', $activeFilterLabels)) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <script>
    // Cascade: filter sub_kegiatan options based on selected kegiatan
    (function() {
        const kegiatanSel   = document.getElementById('filter-kegiatan');
        const subKegSel     = document.getElementById('filter-sub-kegiatan');
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
        cascade(); // run on page load to restore state
    })();
    </script>

    <!-- RAK Table -->
    <style>
        .rak-scroll { overflow-x: auto; }
        .rak-scroll table { min-width: 1240px; }
        .rak-scroll th.rak-month, .rak-scroll td.rak-month { min-width: 80px; }
        .rak-scroll th.rak-c-no, .rak-scroll td.rak-c-no {
            position: sticky; left: 0; z-index: 3; min-width: 46px;
            background: #fff;
        }
        .rak-scroll thead th.rak-c-no { background: #f8f9fa; }
        .rak-scroll th.rak-c-rek, .rak-scroll td.rak-c-rek {
            position: sticky; left: 46px; z-index: 3; min-width: 230px;
            background: #fff;
            box-shadow: 4px 0 6px -4px rgba(0,0,0,.25);
        }
        .rak-scroll thead th.rak-c-rek { background: #f8f9fa; }
        .rak-scroll th.rak-c-total, .rak-scroll td.rak-c-total,
        .rak-scroll tfoot td.rak-c-total {
            position: sticky; right: 88px; z-index: 3;
            background: #fff;
            box-shadow: -4px 0 6px -4px rgba(0,0,0,.25);
        }
        .rak-scroll thead th.rak-c-total, .rak-scroll tfoot td.rak-c-total { background: #f8f9fa; }
        .rak-scroll th.rak-c-aksi, .rak-scroll td.rak-c-aksi {
            position: sticky; right: 0; z-index: 3; min-width: 88px;
            background: #fff;
        }
        .rak-scroll thead th.rak-c-aksi { background: #f8f9fa; }
    </style>
    <div class="card">
        <?php if (!empty($groupedRak)): ?>
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="m-0 fw-bold text-primary">Daftar Tabel RAK</h6>
            <span class="badge bg-light text-secondary border d-md-none">
                <i class="bi bi-arrows-expand me-1"></i>Geser tabel ke kiri untuk melihat Total
            </span>
        </div>
        <div class="px-3 pt-3">
            <div class="alert alert-success d-flex justify-content-between align-items-center py-2 px-3 mb-0">
                <span class="fw-semibold">
                    <i class="bi bi-calculator me-1"></i>Total RAK <?= $isFiltered ? '(Sesuai Filter)' : '(Semua Data)' ?>
                </span>
                <span class="fw-bold fs-5">Rp <?= number_format($globalTotal ?? 0, 0, ',', '.') ?></span>
            </div>
        </div>
        <?php endif; ?>
        <div class="card-body">
            <?php if (empty($groupedRak)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>
                        <?php if ($isFiltered): ?>
                            Tidak ada data RAK dengan filter yang dipilih
                        <?php else: ?>
                            Belum ada data RAK
                        <?php endif; ?>
                    </p>
                    <?php if (!$isFiltered): ?>
                        <a href="<?= base_url('rak/create') ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Tambah RAK Pertama
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('rak') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Tampilkan Semua
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="rak-scroll">
                    <table class="table table-hover align-middle table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="align-middle rak-c-no">No</th>
                                <th rowspan="2" class="align-middle rak-c-rek">Rekening</th>
                                <th colspan="12" class="text-center">Bulan</th>
                                <th rowspan="2" class="align-middle text-end rak-c-total">Total</th>
                                <th rowspan="2" class="align-middle text-center rak-c-aksi">Aksi</th>
                            </tr>
                            <tr>
                                <th class="text-center rak-month">Jan</th>
                                <th class="text-center rak-month">Feb</th>
                                <th class="text-center rak-month">Mar</th>
                                <th class="text-center rak-month">Apr</th>
                                <th class="text-center rak-month">Mei</th>
                                <th class="text-center rak-month">Jun</th>
                                <th class="text-center rak-month">Jul</th>
                                <th class="text-center rak-month">Agu</th>
                                <th class="text-center rak-month">Sep</th>
                                <th class="text-center rak-month">Okt</th>
                                <th class="text-center rak-month">Nov</th>
                                <th class="text-center rak-month">Des</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 0;
                            $grandTotal = 0;
                            foreach ($groupedRak as $rak): 
                                $index++;
                                $grandTotal += $rak['total'];
                            ?>
                                <tr>
                                    <td class="rak-c-no"><?= $index ?></td>
                                    <td class="rak-c-rek">
                                        <div>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($rak['kode_rekening']) ?></span>
                                            <br>
                                            <small><?= htmlspecialchars($rak['nama_rekening']) ?></small>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($rak['kode_program'] . ' / ' . $rak['kode_kegiatan'] . ' / ' . $rak['kode_sub_kegiatan']) ?></small>
                                            <br>
                                            <small class="text-muted">Tahun: <?= htmlspecialchars($rak['tahun']) ?></small>
                                        </div>
                                    </td>
                                    <?php for ($bulan = 1; $bulan <= 12; $bulan++): ?>
                                        <td class="text-end rak-month">
                                            <?php if ($rak['months'][$bulan] > 0): ?>
                                                <?= number_format($rak['months'][$bulan], 0, ',', '.') ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                    <td class="text-end rak-c-total"><strong>Rp <?= number_format($rak['total'], 0, ',', '.') ?></strong></td>
                                    <td class="text-center rak-c-aksi">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('rak/edit/' . $rak['rekening_id'] . '/' . $rak['tahun']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= base_url('rak/delete/' . $rak['rekening_id'] . '/' . $rak['tahun']) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Hapus"
                                               data-confirm-delete
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus RAK ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="14" class="text-end">
                                    <strong>Total (Halaman Ini):</strong>
                                </td>
                                <td class="text-end rak-c-total"><strong>Rp <?= number_format($grandTotal, 0, ',', '.') ?></strong></td>
                                <td class="rak-c-aksi"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                                <?php
                                    $prevBase = $pagination['baseUrl'];
                                    $sep = str_contains($prevBase, '?') ? '&' : '?';
                                ?>
                                <a class="page-link" href="<?= $pagination['page'] <= 1 ? '#' : $prevBase . $sep . 'page=' . ($pagination['page'] - 1) ?>">«</a>
                            </li>
                            <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                                <?php $sep = str_contains($pagination['baseUrl'], '?') ? '&' : '?'; ?>
                                <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $pagination['baseUrl'] . $sep . 'page=' . $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                                <?php $sep = str_contains($pagination['baseUrl'], '?') ? '&' : '?'; ?>
                                <a class="page-link" href="<?= $pagination['page'] >= $pagination['totalPages'] ? '#' : $pagination['baseUrl'] . $sep . 'page=' . ($pagination['page'] + 1) ?>">»</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

