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
                    <i class="bi bi-receipt text-primary me-2"></i>Data Transaksi
                </h2>
                <p class="text-muted mb-0">Riwayat transaksi anggaran</p>
            </div>
            <a href="<?= base_url('transaksi/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Input Transaksi (Batch)
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="<?= base_url('transaksi') ?>" id="filterForm" class="row g-2 align-items-end">
                <!-- Bulan -->
                <div class="col-auto">
                    <label for="filter-bulan" class="form-label fw-semibold mb-1">
                        <i class="bi bi-calendar-month me-1 text-primary"></i>Bulan
                    </label>
                    <select name="bulan" id="filter-bulan" class="form-select" style="min-width:150px;">
                        <option value="">-- Semua Bulan --</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $filterBulan == $m ? 'selected' : '' ?>>
                                <?= $namaBulan[$m] ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Tahun -->
                <div class="col-auto">
                    <label for="filter-tahun" class="form-label fw-semibold mb-1">
                        <i class="bi bi-calendar-year me-1 text-primary"></i>Tahun
                    </label>
                    <select name="tahun" id="filter-tahun" class="form-select" style="min-width:110px;">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Kegiatan -->
                <div class="col-auto">
                    <label for="filter-kegiatan" class="form-label fw-semibold mb-1">
                        <i class="bi bi-layers me-1 text-primary"></i>Kegiatan
                    </label>
                    <select name="kegiatan_id" id="filter-kegiatan" class="form-select" style="min-width:210px;">
                        <option value="">-- Semua Kegiatan --</option>
                        <?php foreach ($filterKegiatanList as $kg): ?>
                            <option value="<?= $kg['id'] ?>" <?= $filterKegiatan == $kg['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kg['kode_kegiatan'] . ' - ' . $kg['nama_kegiatan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Sub Kegiatan -->
                <div class="col-auto">
                    <label for="filter-sub-kegiatan" class="form-label fw-semibold mb-1">
                        <i class="bi bi-diagram-3 me-1 text-primary"></i>Sub Kegiatan
                    </label>
                    <select name="sub_kegiatan_id" id="filter-sub-kegiatan" class="form-select" style="min-width:210px;">
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
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="btn-filter">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="<?= base_url('transaksi') ?>" class="btn btn-outline-secondary" id="btn-reset">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
                <?php if ($isFiltered): ?>
                    <div class="col-12 mt-2">
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
        cascade();
    })();
    </script>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($transaksis)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>
                        <?php if ($filterBulan !== null): ?>
                            Tidak ada transaksi pada <?= $namaBulan[$filterBulan] ?> <?= $filterTahun ?>
                        <?php else: ?>
                            Belum ada data transaksi
                        <?php endif; ?>
                    </p>
                    <?php if ($filterBulan === null): ?>
                        <a href="<?= base_url('transaksi/create') ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Input Transaksi Pertama (Batch)
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('transaksi') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Tampilkan Semua
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">No</th>
                                <th width="10%">Tanggal</th>
                                <th width="12%">Seksi</th>
                                <th width="10%">Rekening</th>
                                <th width="25%">Uraian</th>
                                <th width="15%" class="text-end">Nilai</th>
                                <th width="13%">Nomor Bukti</th>
                                <th width="10%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalNilai = 0;
                            foreach ($transaksis as $index => $transaksi): 
                                $totalNilai += (float) $transaksi['nilai'];
                            ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= date('d/m/Y', strtotime($transaksi['tanggal'])) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($transaksi['kode_seksi']) ?></span>
                                        <br>
                                        <small><?= htmlspecialchars($transaksi['nama_seksi']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark"><?= htmlspecialchars($transaksi['kode_rekening']) ?></span>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($transaksi['kode_program'] . ' / ' . $transaksi['kode_kegiatan']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($transaksi['uraian']) ?></td>
                                    <td class="text-end">
                                        <strong>Rp <?= number_format($transaksi['nilai'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($transaksi['nomor_bukti']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('transaksi/edit/' . $transaksi['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= base_url('transaksi/delete/' . $transaksi['id']) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Hapus"
                                               data-confirm-delete
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end">
                                    <strong>
                                        Total<?= $filterBulan !== null ? ' ' . $namaBulan[$filterBulan] . ' ' . $filterTahun : '' ?>:
                                    </strong>
                                </td>
                                <td class="text-end"><strong>Rp <?= number_format($totalNilai, 0, ',', '.') ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $pagination['page'] <= 1 ? '#' : $pagination['baseUrl'] . '&page=' . ($pagination['page'] - 1) ?>">«</a>
                            </li>
                            <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                                <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $pagination['baseUrl'] . '&page=' . $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $pagination['page'] >= $pagination['totalPages'] ? '#' : $pagination['baseUrl'] . '&page=' . ($pagination['page'] + 1) ?>">»</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

