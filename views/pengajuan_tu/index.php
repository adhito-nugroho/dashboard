<?php
/**
 * View: Tambah Uang (TU) — Daftar & Monitoring (Admin)
 * Variabel: $items, $stats, $pagination, $seksiList, $status, $tahun, $bulan, $seksiId
 */
$items     = $items     ?? [];
$stats     = $stats     ?? [];
$seksiList = $seksiList ?? [];
$status    = $status    ?? '';
$tahun     = $tahun     ?? (int) date('Y');
$bulan     = $bulan     ?? 0;

$namaBulanMap = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                 7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$statusBadge = [
    'diajukan'  => ['warning',  'Menunggu Persetujuan'],
    'disetujui' => ['info',     'Disetujui'],
    'cair'      => ['primary',  'SP2D Cair'],
    'selesai'   => ['success',  'Selesai'],
    'ditolak'   => ['danger',   'Ditolak'],
];

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<div class="container-fluid py-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><i class="bi bi-plus-circle-dotted text-primary me-2"></i>Tambah Uang (TU)</h2>
            <p class="text-muted mb-0">Monitoring siklus TU: pengajuan → persetujuan → pencairan SP2D → SPJ → setoran sisa</p>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <?php
        $kpis = [
            ['pending',    'bi-hourglass-split',    'warning', 'Menunggu Persetujuan', $stats['pending']    ?? 0],
            ['disetujui',  'bi-check-circle',       'info',    'Disetujui',            $stats['disetujui']  ?? 0],
            ['cair',       'bi-cash-coin',          'primary', 'SP2D Cair',            $stats['cair']       ?? 0],
            ['jatuh_tempo','bi-exclamation-triangle','danger', 'Jatuh Tempo SPJ',      $stats['jatuh_tempo']?? 0],
            ['selesai',    'bi-check2-all',         'success', 'Selesai',              $stats['selesai']    ?? 0],
        ];
        foreach ($kpis as [$key, $icon, $color, $label, $val]):
        ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= base_url('pengajuan-tu?status=' . $key . '&tahun=' . $tahun) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= $key === 'jatuh_tempo' && $val > 0 ? 'border-danger' : '' ?>">
                    <div class="card-body p-3 text-center">
                        <i class="bi <?= $icon ?> text-<?= $color ?> mb-2" style="font-size:1.5rem;"></i>
                        <div class="fw-bold" style="font-size:1.4rem;"><?= $val ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= $label ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        <!-- Total Cair -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 text-center">
                    <i class="bi bi-bank text-success mb-2" style="font-size:1.5rem;"></i>
                    <div class="fw-bold text-success" style="font-size:1rem;">
                        Rp <?= number_format($stats['total_cair'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <div class="text-muted" style="font-size:.75rem;">Total TU Cair <?= $tahun ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="<?= base_url('pengajuan-tu') ?>" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1">Tahun</label>
                    <select name="tahun" class="form-select form-select-sm" style="min-width:100px;">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--): ?>
                            <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1">Bulan</label>
                    <select name="bulan" class="form-select form-select-sm" style="min-width:140px;">
                        <option value="">-- Semua --</option>
                        <?php foreach ($namaBulanMap as $m => $nm): ?>
                            <option value="<?= $m ?>" <?= $bulan == $m ? 'selected' : '' ?>><?= $nm ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:180px;">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($statusBadge as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v[1] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1">Seksi</label>
                    <select name="seksi_id" class="form-select form-select-sm" style="min-width:180px;">
                        <option value="">-- Semua Seksi --</option>
                        <?php foreach ($seksiList as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (isset($seksiId) && $seksiId == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nama_seksi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="<?= base_url('pengajuan-tu') ?>" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-x-circle me-1"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
                    <p class="mt-2 mb-0">Tidak ada pengajuan TU untuk filter ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Nomor TU</th>
                                <th>Seksi</th>
                                <th>Periode</th>
                                <th class="text-end">Kebutuhan (Rp)</th>
                                <th class="text-end">Cair (Rp)</th>
                                <th class="text-end">Terpakai (Rp)</th>
                                <th>Batas SPJ</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item):
                                $bInfo = $statusBadge[$item['status']] ?? ['secondary', $item['status']];
                                $nominal_cair = (float) ($item['nominal_cair'] ?? 0);
                                $nominal_terpakai = (float) ($item['nominal_terpakai'] ?? 0);
                                $sisa = $nominal_cair - $nominal_terpakai;
                                $jatuhTempo = $item['status'] === 'cair' && !empty($item['batas_spj']) && $item['batas_spj'] < date('Y-m-d');
                            ?>
                                <tr class="<?= $jatuhTempo ? 'table-danger' : '' ?>">
                                    <td>
                                        <a href="<?= base_url('pengajuan-tu/show/' . $item['id']) ?>" class="fw-semibold text-decoration-none">
                                            <?= htmlspecialchars($item['nomor_pengajuan']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size:.75rem;"><?= date('d/m/Y', strtotime($item['tanggal_pengajuan'])) ?></div>
                                    </td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($item['kode_seksi'] ?? '-') ?></span> <?= htmlspecialchars($item['nama_seksi'] ?? '-') ?></td>
                                    <td style="white-space:nowrap;"><?= ($namaBulanMap[$item['bulan']] ?? $item['bulan']) . ' ' . $item['tahun'] ?></td>
                                    <td class="text-end"><?= number_format((float)$item['kebutuhan'], 0, ',', '.') ?></td>
                                    <td class="text-end <?= $nominal_cair > 0 ? 'text-primary fw-semibold' : 'text-muted' ?>">
                                        <?= $nominal_cair > 0 ? number_format($nominal_cair, 0, ',', '.') : '—' ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($nominal_cair > 0): ?>
                                            <?= number_format($nominal_terpakai, 0, ',', '.') ?>
                                            <div class="text-muted" style="font-size:.72rem;">sisa: <?= number_format(max(0, $sisa), 0, ',', '.') ?></div>
                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['batas_spj'])): ?>
                                            <span class="<?= $jatuhTempo ? 'text-danger fw-bold' : '' ?>">
                                                <?= date('d/m/Y', strtotime($item['batas_spj'])) ?>
                                                <?= $jatuhTempo ? ' <i class="bi bi-exclamation-circle"></i>' : '' ?>
                                            </span>
                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-<?= $bInfo[0] ?>"><?= $bInfo[1] ?></span></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('pengajuan-tu/show/' . $item['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                    <nav class="p-3">
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
