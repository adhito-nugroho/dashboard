<?php
/**
 * View: Tambah Uang (TU) — Daftar & Monitoring (Admin)
 * Variabel: $items, $stats, $totals, $pagination, $seksiList, $status, $tahun, $bulan, $seksiId
 */
$items     = $items     ?? [];
$stats     = $stats     ?? [];
$totals    = $totals    ?? [];
$seksiList = $seksiList ?? [];
$status    = $status    ?? '';
$tahun     = $tahun     ?? (int) date('Y');
$bulan     = $bulan     ?? 0;

$exportParams = array_filter([
    'status'   => $status,
    'tahun'    => $tahun,
    'bulan'    => $bulan ?: null,
    'seksi_id' => $seksiId ?? null,
    'q'        => trim((string) ($_GET['q'] ?? '')),
], fn($v) => $v !== null && $v !== '');
$exportUrl = base_url('pengajuan-tu/export' . ($exportParams ? '?' . http_build_query($exportParams) : ''));

$namaBulanMap = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                 7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$namaBulanShort = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
                   7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];

// badge CSS, label pendek (tabel), label lengkap (filter & tooltip)
$statusMeta = [
    'diajukan'  => ['tu-badge--wait',     'Menunggu',  'Menunggu Cair'],
    'disetujui' => ['tu-badge--approved', 'Disetujui', 'Disetujui (lama)'],
    'cair'      => ['tu-badge--cair',     'SP2D Cair', 'SP2D Cair'],
    'selesai'   => ['tu-badge--done',     'Selesai',   'Selesai'],
    'ditolak'   => ['tu-badge--rejected', 'Ditolak',   'Ditolak'],
];

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<div class="container-fluid py-4 tu-page">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1">Tambah Uang (TU)</h2>
            <p class="text-muted mb-0">Siklus TU: input pengajuan → pencairan SP2D → pembayaran/realisasi → setoran sisa</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= $exportUrl ?>" id="tuExportLink" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
            </a>
            <a href="<?= base_url('pengajuan-tu/create') ?>" class="btn btn-primary btn-tu-primary">
                <i class="bi bi-plus-circle me-1"></i>Input Pengajuan TU
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <?php
        $kpis = [
            ['pending',    'bi-hourglass-split',    'warning', 'Menunggu Cair',        $stats['pending']    ?? 0],
            ['disetujui',  'bi-check-circle',       'info',    'Disetujui (lama)',     $stats['disetujui']  ?? 0],
            ['cair',       'bi-cash-coin',          'success', 'SP2D Cair',            $stats['cair']       ?? 0],
            ['jatuh_tempo','bi-exclamation-triangle','danger', 'Jatuh Tempo SPJ',      $stats['jatuh_tempo']?? 0],
            ['selesai',    'bi-check2-all',         'success', 'Selesai',              $stats['selesai']    ?? 0],
        ];
        foreach ($kpis as [$key, $icon, $color, $label, $val]):
        ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= base_url('pengajuan-tu?status=' . $key . '&tahun=' . $tahun) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 tu-kpi <?= (int) $val === 0 ? 'tu-kpi-zero' : '' ?> <?= $key === 'jatuh_tempo' && $val > 0 ? 'border-danger' : '' ?>">
                    <div class="card-body">
                        <span class="tu-kpi-icon tu-kpi-icon--<?= $color ?>"><i class="bi <?= $icon ?>"></i></span>
                        <span class="tu-kpi-body">
                            <span class="tu-kpi-value"><?= $val ?></span>
                            <span class="tu-kpi-label"><?= $label ?></span>
                        </span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        <!-- Total Cair -->
        <?php
        $totalCair     = (float) ($stats['total_cair'] ?? 0);
        $totalCairFull = 'Rp ' . number_format($totalCair, 0, ',', '.');
        if ($totalCair >= 1000000000) {
            $totalCairShort = number_format($totalCair / 1000000000, 1, ',', '.') . ' M';
        } elseif ($totalCair >= 1000000) {
            $totalCairShort = number_format($totalCair / 1000000, 1, ',', '.') . ' jt';
        } else {
            $totalCairShort = number_format($totalCair, 0, ',', '.');
        }
        ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 tu-kpi <?= $totalCair == 0 ? 'tu-kpi-zero' : '' ?>">
                <div class="card-body">
                    <span class="tu-kpi-icon tu-kpi-icon--success"><i class="bi bi-bank"></i></span>
                    <span class="tu-kpi-body">
                        <span class="tu-kpi-value tu-kpi-value--money text-success" title="<?= $totalCairFull ?>" aria-label="<?= $totalCairFull ?>"><?= $totalCairShort ?></span>
                        <span class="tu-kpi-label">Total TU Cair <?= $tahun ?> (Rp)</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="<?= base_url('pengajuan-tu') ?>" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1" for="tuSearch">Cari Nomor TU</label>
                    <input type="search" id="tuSearch" name="q" class="form-control form-control-sm" placeholder="Cari Nomor TU..."
                        style="min-width:190px;" autocomplete="off" title="Menyaring baris nomor TU pada halaman ini">
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1" for="tuFilterTahun">Tahun</label>
                    <select name="tahun" id="tuFilterTahun" class="form-select form-select-sm" style="min-width:100px;">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--): ?>
                            <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1" for="tuFilterBulan">Bulan</label>
                    <select name="bulan" id="tuFilterBulan" class="form-select form-select-sm" style="min-width:140px;">
                        <option value="">-- Semua --</option>
                        <?php foreach ($namaBulanMap as $m => $nm): ?>
                            <option value="<?= $m ?>" <?= $bulan == $m ? 'selected' : '' ?>><?= $nm ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1" for="tuFilterStatus">Status</label>
                    <select name="status" id="tuFilterStatus" class="form-select form-select-sm" style="min-width:180px;">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($statusMeta as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= htmlspecialchars($v[2]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1" for="tuFilterSeksi">Seksi</label>
                    <select name="seksi_id" id="tuFilterSeksi" class="form-select form-select-sm" style="min-width:180px;">
                        <option value="">-- Semua Seksi --</option>
                        <?php foreach ($seksiList as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (isset($seksiId) && $seksiId == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nama_seksi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-tu-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="<?= base_url('pengajuan-tu') ?>" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-x-circle me-1"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Ringkasan total sesuai filter aktif -->
    <?php
    $totalJumlah    = (int)   ($totals['jumlah']          ?? 0);
    $totalPengajuan = (float) ($totals['total_pengajuan'] ?? 0);
    $totalCairSum   = (float) ($totals['total_cair']      ?? 0);
    $totalTerpakai  = (float) ($totals['total_terpakai']  ?? 0);
    $totalSisa      = max(0, $totalCairSum - $totalTerpakai);
    ?>
    <div class="card border-0 shadow-sm mb-4 tu-summary">
        <div class="card-body">
            <div class="tu-summary-head">
                <span class="tu-summary-title"><i class="bi bi-calculator me-1"></i>Total Sesuai Filter</span>
                <span class="tu-summary-count"><?= $totalJumlah ?> pengajuan</span>
            </div>
            <div class="tu-summary-grid">
                <div class="tu-summary-item">
                    <div class="tu-summary-label"><i class="bi bi-file-earmark-text"></i>Total Pengajuan</div>
                    <div class="tu-summary-value">Rp <?= number_format($totalPengajuan, 0, ',', '.') ?></div>
                </div>
                <div class="tu-summary-item">
                    <div class="tu-summary-label"><i class="bi bi-bank"></i>Total Cair (SP2D)</div>
                    <div class="tu-summary-value">Rp <?= number_format($totalCairSum, 0, ',', '.') ?></div>
                </div>
                <div class="tu-summary-item tu-summary-item--used">
                    <div class="tu-summary-label"><i class="bi bi-cart-check"></i>Total Terpakai</div>
                    <div class="tu-summary-value">Rp <?= number_format($totalTerpakai, 0, ',', '.') ?></div>
                </div>
                <div class="tu-summary-item tu-summary-item--left">
                    <div class="tu-summary-label"><i class="bi bi-piggy-bank"></i>Sisa Belum Terpakai</div>
                    <div class="tu-summary-value">Rp <?= number_format($totalSisa, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
                <div class="text-center py-5 tu-empty">
                    <span class="tu-empty-icon"><i class="bi bi-inbox"></i></span>
                    <p class="tu-empty-title mb-1">Belum ada pengajuan TU</p>
                    <p class="tu-empty-sub">Tidak ada pengajuan untuk filter yang dipilih. Ubah filter atau buat pengajuan baru.</p>
                    <a href="<?= base_url('pengajuan-tu/create') ?>" class="btn btn-primary btn-tu-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Input Pengajuan TU
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive tu-table-wrap">
                    <table id="tuTable" class="table tu-table mb-0">
                        <colgroup>
                            <col class="tu-col-sub">
                            <col class="tu-col-period">
                            <col class="tu-col-needed">
                            <col class="tu-col-cair">
                            <col class="tu-col-used">
                            <col class="tu-col-status">
                            <col class="tu-col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Sub Kegiatan</th>
                                <th scope="col">Periode</th>
                                <th scope="col" class="tu-num">Kebutuhan (Rp)</th>
                                <th scope="col" class="tu-num">Cair (Rp)</th>
                                <th scope="col" class="tu-num">Terpakai (Rp)</th>
                                <th scope="col" class="text-center">Status</th>
                                <th scope="col" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tuTableBody">
                            <?php foreach ($items as $item):
                                $stMeta = $statusMeta[$item['status']] ?? ['tu-badge--done', $item['status'], $item['status']];
                                $nominal_cair = (float) ($item['nominal_cair'] ?? 0);
                                $nominal_terpakai = (float) ($item['nominal_terpakai'] ?? 0);
                                $sisa = max(0, $nominal_cair - $nominal_terpakai);
                                $pct = $nominal_cair > 0 ? min(100, (int) round($nominal_terpakai / $nominal_cair * 100)) : 0;
                                $subName = (string) ($item['nama_sub_kegiatan'] ?? '-');
                                $subKode = (string) ($item['kode_sub_kegiatan'] ?? '');
                                $detailUrl = base_url('pengajuan-tu/show/' . $item['id']);
                            ?>
                                <tr data-href="<?= $detailUrl ?>"
                                    data-nomor="<?= htmlspecialchars(strtolower((string) $item['nomor_pengajuan'])) ?>">
                                    <td class="tu-cell-sub">
                                        <a href="<?= $detailUrl ?>" class="tu-sub-link" title="<?= htmlspecialchars($subName) ?>">
                                            <span class="tu-sub-name"><?= htmlspecialchars($subName) ?></span>
                                        </a>
                                        <div class="tu-sub-meta">
                                            <?php if ($subKode !== ''): ?>
                                                <span class="tu-kode" title="Kode sub kegiatan"><?= htmlspecialchars($subKode) ?></span>
                                                <span class="tu-meta-dot" aria-hidden="true">•</span>
                                            <?php endif; ?>
                                            <span class="tu-date"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= date('d/m/Y', strtotime($item['tanggal_pengajuan'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="tu-period" data-label="Periode"
                                        title="<?= htmlspecialchars(($namaBulanMap[$item['bulan']] ?? $item['bulan']) . ' ' . $item['tahun']) ?>">
                                        <?= ($namaBulanShort[$item['bulan']] ?? $item['bulan']) . ' ' . $item['tahun'] ?>
                                    </td>
                                    <td class="tu-num" data-label="Kebutuhan (Rp)">
                                        <div class="tu-cell-stack">
                                            <span class="tu-amount"><?= number_format((float) $item['kebutuhan'], 0, ',', '.') ?></span>
                                            <?php if (!empty($item['jumlah_rekening'])): ?>
                                                <span class="tu-sub-note"><?= (int) $item['jumlah_rekening'] ?> rekening</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="tu-num" data-label="Cair (Rp)">
                                        <div class="tu-cell-stack">
                                            <?php if ($nominal_cair > 0): ?>
                                                <span class="tu-amount"><?= number_format($nominal_cair, 0, ',', '.') ?></span>
                                            <?php else: ?>
                                                <span class="tu-amount text-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="tu-num" data-label="Terpakai (Rp)">
                                        <div class="tu-cell-stack">
                                            <?php if ($nominal_cair > 0): ?>
                                                <span class="tu-amount"><?= number_format($nominal_terpakai, 0, ',', '.') ?></span>
                                                <div class="tu-progress" role="img"
                                                     aria-label="Terpakai <?= $pct ?>% dari dana cair"
                                                     title="Terpakai <?= $pct ?>% dari dana cair">
                                                    <span style="width:<?= $pct ?>%"></span>
                                                </div>
                                                <span class="tu-sisa <?= $sisa > 0.005 ? 'tu-sisa--left' : 'tu-sisa--none' ?>">
                                                    sisa: <?= number_format($sisa, 0, ',', '.') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="tu-amount text-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center" data-label="Status">
                                        <span class="tu-badge <?= $stMeta[0] ?>" title="<?= htmlspecialchars($stMeta[2]) ?>"><?= htmlspecialchars($stMeta[1]) ?></span>
                                    </td>
                                    <td class="tu-cell-action" data-label="Aksi">
                                        <a href="<?= $detailUrl ?>" class="tu-action-btn" title="Lihat Detail"
                                           aria-label="Lihat detail pengajuan <?= htmlspecialchars($subName) ?>">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="tuSearchEmpty" style="display:none;">
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-search me-1"></i>Tidak ada nomor TU yang cocok.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                    <nav class="p-3" aria-label="Navigasi halaman pengajuan TU">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $pagination['baseUrl'] ?>?tahun=<?= $tahun ?>&status=<?= urlencode($status) ?>&page=<?= $pagination['page'] - 1 ?>">«</a>
                            </li>
                            <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                                <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $pagination['baseUrl'] ?>?tahun=<?= $tahun ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $pagination['baseUrl'] ?>?tahun=<?= $tahun ?>&status=<?= urlencode($status) ?>&page=<?= $pagination['page'] + 1 ?>">»</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    // ── Filter nomor TU (hanya baris pada halaman aktif) ──────────────────
    var input = document.getElementById('tuSearch');
    var table = document.getElementById('tuTable');
    if (input && table) {
        var rows  = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-nomor]'));
        var empty = document.getElementById('tuSearchEmpty');
        var exportLink = document.getElementById('tuExportLink');

        var initial = new URLSearchParams(window.location.search).get('q');
        if (initial) input.value = initial;

        function syncExportLink(rawQuery) {
            if (!exportLink) return;
            var url = new URL(exportLink.href, window.location.href);
            if (rawQuery) {
                url.searchParams.set('q', rawQuery);
            } else {
                url.searchParams.delete('q');
            }
            exportLink.href = url.toString();
        }

        function applyFilter() {
            var q = input.value.trim().toLowerCase();
            var visible = 0;

            rows.forEach(function (row) {
                var match = !q || (row.getAttribute('data-nomor') || '').indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (empty) empty.style.display = (q !== '' && visible === 0) ? '' : 'none';

            syncExportLink(input.value.trim());
        }

        input.addEventListener('input', applyFilter);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });

        applyFilter();
    }

    // ── Seluruh baris dapat diklik untuk membuka detail ───────────────────
    document.querySelectorAll('#tuTable tbody tr[data-href]').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('a, button, form, input, select, textarea, label')) return;
            window.location.href = tr.getAttribute('data-href');
        });
    });

    // ── Loading skeleton saat filter/pagination diproses ──────────────────
    function showSkeleton() {
        var body = document.getElementById('tuTableBody');
        if (!body) return;
        var html = '';
        for (var i = 0; i < 5; i++) {
            html += '<tr class="tu-skeleton-row" aria-hidden="true">';
            for (var j = 0; j < 7; j++) {
                html += '<td><div class="tu-sk-line"' + (j === 0 ? ' style="width:78%"' : '') + '></div></td>';
            }
            html += '</tr>';
        }
        body.innerHTML = html;
    }
    var filterForm = document.querySelector('.tu-page form[method="GET"]');
    if (filterForm) filterForm.addEventListener('submit', showSkeleton);
    document.querySelectorAll('.tu-page .pagination a').forEach(function (a) {
        a.addEventListener('click', showSkeleton);
    });
})();
</script>
