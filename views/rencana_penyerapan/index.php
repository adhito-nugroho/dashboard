<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$groups = $groups ?? [];
$belumDirencanakan = $belumDirencanakan ?? [];
$belumCount = $belumCount ?? count($belumDirencanakan);
$totalPagu = $totalPagu ?? 0;
$totalRencana = $totalRencana ?? 0;
$totalTuPlan = $totalTuPlan ?? 0;
$totalRealisasi = $totalRealisasi ?? 0;
$selesaiList = $selesaiList ?? [];
$tahun = $tahun ?? (int) date('Y');

$bulanShort = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$bulanFull = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$shortRupiah = function (float $value): string {
    if ($value >= 1_000_000_000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1_000_000_000, 1, ',', '.'), '0'), ',') . ' miliar';
    }
    if ($value >= 1_000_000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1_000_000, 1, ',', '.'), '0'), ',') . ' jt';
    }
    if ($value >= 1_000) {
        return 'Rp ' . number_format($value / 1_000, 0, ',', '.') . ' rb';
    }
    return 'Rp ' . number_format($value, 0, ',', '.');
};

$pctRencana = $totalPagu > 0 ? (($totalRencana + $totalTuPlan) / $totalPagu) * 100 : 0;
$pctRealisasi = $totalPagu > 0 ? ($totalRealisasi / $totalPagu) * 100 : 0;
$totalSisaKapasitas = $totalSisaKapasitas ?? ($totalPagu - $totalRealisasi - $totalRencana - $totalTuPlan);

$tahunInt = (int) $tahun;
$bulanSekarang = $tahunInt < (int) date('Y') ? 12 : ($tahunInt === (int) date('Y') ? (int) date('n') : 0);

$filterKegiatan = $filterKegiatan ?? null;
$filterSubKegiatan = $filterSubKegiatan ?? null;
$filterMekanisme = $filterMekanisme ?? null;
$filterStatus = $filterStatus ?? '';
$filterQ = $filterQ ?? '';
$isFiltered = $filterKegiatan !== null || $filterSubKegiatan !== null || $filterMekanisme !== null || $filterStatus !== '' || $filterQ !== '';

$paginationBase = base_url('rencana-penyerapan');
if (!empty($queryParams)) {
    $paginationBase .= '?' . http_build_query($queryParams);
}
$backQuery = !empty($queryParams) ? http_build_query($queryParams) : '';
$sep = str_contains($paginationBase, '?') ? '&' : '?';

$mekanismeMeta = [
    'GU' => ['label' => 'GU', 'desc' => 'Ganti Uang'],
    'TU' => ['label' => 'TU', 'desc' => 'Tambah Uang'],
    'LS' => ['label' => 'LS', 'desc' => 'Langsung'],
];
?>

<style>
    .rp-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .rp-kpi {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .rp-kpi__label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #64748b;
    }

    .rp-kpi__value {
        font-size: 1.12rem;
        font-weight: 800;
        color: #0f172a;
        margin-top: 4px;
        line-height: 1.25;
    }

    .rp-kpi__sub {
        font-size: .72rem;
        color: #64748b;
        margin-top: 2px;
    }

    .rp-rek-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
    }

    .rp-rek-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        padding: 16px 18px 14px;
        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        border-bottom: 1px solid #e2e8f0;
    }

    .rp-rek-title {
        font-size: .98rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.35;
    }

    .rp-rek-path {
        font-size: .74rem;
        color: #64748b;
        margin-top: 3px;
        line-height: 1.45;
    }

    .rp-rek-summary {
        display: flex;
        gap: 22px;
        flex-wrap: wrap;
        justify-content: flex-end;
        min-width: 300px;
    }

    .rp-sum-item small {
        display: block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #64748b;
    }

    .rp-sum-item strong {
        display: block;
        font-size: .92rem;
        color: #0f172a;
        white-space: nowrap;
    }

    .rp-sum-item .rp-pct {
        display: inline-block;
        font-size: .68rem;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
        margin-top: 2px;
    }

    .rp-rek-progress {
        padding: 0 18px 14px;
        background: #eef2ff;
        border-bottom: 1px solid #e2e8f0;
    }

    .rp-rek-progress .progress {
        height: 7px;
        border-radius: 999px;
        background: rgba(67, 56, 202, .12);
    }

    .rp-rek-progress .progress-bar {
        border-radius: 999px;
        background: linear-gradient(90deg, #059669, #34d399);
    }

    .rp-rek-progress-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        font-size: .72rem;
        color: #475569;
        margin-bottom: 5px;
    }

    .rp-mek-breakdown {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 10px 18px 0;
    }

    .rp-mek {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .04em;
        padding: 2px 7px;
        border-radius: 999px;
        line-height: 1.4;
        white-space: nowrap;
    }

    .rp-mek--GU { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
    .rp-mek--TU { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .rp-mek--LS { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .rp-mek--UP { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .rp-mek-amount {
        font-size: .7rem;
        color: #475569;
        padding: 2px 8px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .rp-timeline {
        padding: 10px 18px 16px;
    }

    .rp-timeline-head,
    .rp-timeline-row {
        display: grid;
        grid-template-columns: minmax(260px, 340px) minmax(0, 1fr);
        gap: 16px;
        align-items: center;
    }

    .rp-timeline-head {
        padding: 4px 0 6px;
        border-bottom: 1px solid #e2e8f0;
    }

    .rp-timeline-head__label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #64748b;
    }

    .rp-months-head {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
    }

    .rp-months-head span {
        text-align: center;
        font-size: .66rem;
        font-weight: 700;
        color: #64748b;
        padding: 4px 0;
        border-left: 1px solid #eef2f7;
    }

    .rp-months-head span.rp-month--past { color: #cbd5e1; }

    .rp-timeline-row {
        padding: 12px 0;
        border-bottom: 1px dashed #e2e8f0;
    }

    .rp-timeline-row:last-child {
        border-bottom: 0;
        padding-bottom: 2px;
    }

    .rp-plan-title {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        font-size: .84rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.4;
    }

    .rp-plan-uraian {
        font-size: .74rem;
        color: #64748b;
        margin-top: 3px;
        line-height: 1.45;
    }

    .rp-plan-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 12px;
        margin-top: 5px;
        font-size: .72rem;
        color: #475569;
    }

    .rp-plan-actions {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 7px;
    }

    .rp-plan-actions .btn {
        padding: .12rem .4rem;
        font-size: .72rem;
    }

    .rp-track-wrap {
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .rp-track {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        align-items: center;
        min-width: 540px;
        min-height: 38px;
        border-radius: 9px;
        background: #f8fafc;
        overflow: hidden;
        position: relative;
    }

    .rp-cell {
        grid-row: 1;
        align-self: stretch;
        min-height: 38px;
        border-left: 1px solid #eef2f7;
    }

    .rp-cell:nth-child(3n+1) { border-left: 2px solid #e2e8f0; }

    .rp-bar {
        grid-row: 1;
        align-self: center;
        z-index: 2;
        height: 24px;
        margin: 0 2px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        padding: 0 8px;
        color: #fff;
        font-size: .66rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(15, 23, 42, .18);
    }

    .rp-bar--GU { background: linear-gradient(90deg, #4338ca, #6366f1); }
    .rp-bar--TU { background: linear-gradient(90deg, #047857, #10b981); }
    .rp-bar--LS { background: linear-gradient(90deg, #b45309, #f59e0b); }

    .rp-empty-bar {
        grid-row: 1;
        grid-column: 1 / -1;
        align-self: center;
        text-align: center;
        font-size: .7rem;
        color: #94a3b8;
        z-index: 1;
    }

    .rp-cell--past { background: rgba(148, 163, 184, .13); }

    .rp-bar--lewat { opacity: .5; }

    .rp-timeline-row--real {
        background: #f8fafc;
        border-radius: 10px;
        padding: 10px 12px;
        margin-top: 6px;
    }

    .rp-timeline-row--tu {
        background: #f0fdfa;
        border-radius: 10px;
        padding: 10px 12px;
        margin-top: 6px;
    }

    .rp-cell--real { position: relative; }

    .rp-vbar {
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 62%;
        display: flex;
        flex-direction: column-reverse;
        border-radius: 3px 3px 0 0;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .25);
    }

    .rp-vseg--GU { background: #4338ca; }
    .rp-vseg--TU { background: #047857; }
    .rp-vseg--LS { background: #f59e0b; }
    .rp-vseg--UP { background: #94a3b8; }

    .rp-belum-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 14px 18px;
        border: 0;
        background: #fff;
        text-align: left;
        font-weight: 700;
        color: #0f172a;
    }

    .rp-belum-toggle:focus-visible { outline: 2px solid #4338ca; outline-offset: -2px; }

    .rp-rek-note {
        display: flex;
        align-items: flex-start;
        gap: 5px;
        font-size: .72rem;
        line-height: 1.35;
        color: #475569;
    }
    .rp-rek-note + .rp-rek-note { margin-top: 3px; }
    .rp-rek-note i { margin-top: 1px; }
    .rp-rek-note--real { color: #047857; }
    .rp-rek-note--tu { color: #b45309; }
    .rp-rek-note--tolak { color: #64748b; }

    @media (max-width: 991.98px) {
        .rp-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rp-rek-head { flex-direction: column; }
        .rp-rek-summary { justify-content: flex-start; min-width: 0; }
    }

    /* Panel entri rencana inline di bawah baris rekening */
    .rp-if-row > td { padding: 0 !important; border-top: 0; background: #f8fafc; }
    .rp-if-row--card { border-top: 1px dashed #c7d2fe; background: #f8fafc; }
    .rp-if { padding: 16px 18px; border-left: 3px solid #4338ca; }
    .rp-if-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
    }
    .rp-if-title { font-size: .86rem; font-weight: 800; color: #0f172a; line-height: 1.4; }
    .rp-if-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 18px;
        margin-top: 4px;
        font-size: .74rem;
        color: #475569;
    }
    .rp-if-meta strong { color: #0f172a; }
    .rp-if .form-label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #334155;
        margin-bottom: 4px;
    }
    .rp-if-mek {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .rp-if-mek-opt {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        margin: 0;
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
    }
    .rp-if-mek-opt:hover { border-color: #c7d2fe; }
    .rp-if-mek-opt:has(input:checked) {
        border-color: #4338ca;
        background: #eef2ff;
        box-shadow: 0 0 0 3px rgba(67, 56, 202, .1);
    }
    .rp-if-mek-opt input { margin-top: 3px; flex: 0 0 auto; }
    .rp-if-mek-opt strong { display: block; font-size: .78rem; color: #0f172a; }
    .rp-if-mek-opt small { display: block; font-size: .7rem; color: #475569; line-height: 1.3; }
    .rp-if-hint { margin-top: 3px; font-size: .7rem; color: #475569; }
    .rp-if-err { margin-top: 3px; font-size: .7rem; color: #b91c1c; }
    .rp-if-err:empty { display: none; }
    .rp-if-alert {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-bottom: 12px;
        padding: 9px 12px;
        border: 1px solid #fecaca;
        border-radius: 10px;
        background: #fef2f2;
        color: #991b1b;
        font-size: .78rem;
    }
    .rp-if-alert[hidden] { display: none; }
    .rp-if-foot {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
    }
    .rp-if-status { font-size: .72rem; color: #475569; }
    .rp-if-status.is-invalid { color: #b91c1c; font-weight: 600; }
    .rp-if .form-control.is-invalid,
    .rp-if .form-select.is-invalid { border-color: #dc2626; }

    @media (max-width: 767.98px) {
        .rp-timeline-head { display: none; }
        .rp-timeline-row { grid-template-columns: 1fr; gap: 9px; }
        .rp-timeline { padding: 8px 14px 14px; }
        .rp-track { min-width: 480px; }
        .rp-rek-head { padding: 14px; }
        .rp-rek-progress { padding: 0 14px 12px; }
        .rp-mek-breakdown { padding: 10px 14px 0; }
        .rp-if { padding: 14px; }
        .rp-if-mek { grid-template-columns: 1fr; }
        .rp-if-foot .btn { flex: 1 1 auto; }
    }
</style>

<div class="container-fluid py-4">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'warning' ? 'warning' : ($flashType === 'success' ? 'success' : 'info')) ?> alert-dismissible fade show" role="alert" data-auto-dismiss>
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-calendar3-range text-primary me-2"></i>Rencana Penyerapan
                </h2>
                <p class="text-muted mb-0">Timeline penyerapan anggaran per kode rekening berdasarkan bentuk kegiatan dan mekanisme pembayaran.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" data-rp-open-belum>
                    <i class="bi bi-plus-circle me-1"></i> Tambah Rencana
                </button>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <span class="text-muted small fw-semibold">Warna timeline:</span>
        <span class="small"><span class="rp-mek rp-mek--GU">GU</span> Ganti Uang</span>
        <span class="small"><span class="rp-mek rp-mek--TU">TU</span> Tambah Uang</span>
        <span class="small"><span class="rp-mek rp-mek--LS">LS</span> Langsung</span>
        <span class="small text-muted"><span class="rp-mek rp-mek--UP">UP</span> belanja UP yang belum masuk SPJ GU/TU</span>
        <span class="small text-muted"><span style="display:inline-block;width:11px;height:11px;background:rgba(148,163,184,.25);border-radius:3px;vertical-align:middle;"></span> bulan sudah lewat</span>
    </div>

    <!-- Ringkasan -->
    <div class="rp-kpi-grid mb-4">
        <div class="rp-kpi">
            <div class="rp-kpi__label"><i class="bi bi-cash-stack"></i> Pagu <?= (int) $tahun ?></div>
            <div class="rp-kpi__value">Rp <?= number_format($totalPagu, 0, ',', '.') ?></div>
            <div class="rp-kpi__sub">Pagu seluruh rekening pada filter ini</div>
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi__label"><i class="bi bi-calendar3-range"></i> Total Rencana</div>
            <div class="rp-kpi__value text-primary">Rp <?= number_format($totalRencana + $totalTuPlan, 0, ',', '.') ?></div>
            <div class="rp-kpi__sub">
                <?= number_format($pctRencana, 1, ',', '.') ?>% dari pagu<?= $isFiltered ? ' (sesuai filter)' : '' ?>
                <?php if ($totalTuPlan > 0): ?>
                    <br>Rp <?= number_format($totalRencana, 0, ',', '.') ?> manual · Rp <?= number_format($totalTuPlan, 0, ',', '.') ?> dari TU
                <?php endif; ?>
            </div>
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi__label"><i class="bi bi-check2-circle"></i> Realisasi Terverifikasi</div>
            <div class="rp-kpi__value text-success">Rp <?= number_format($totalRealisasi, 0, ',', '.') ?></div>
            <div class="rp-kpi__sub"><?= number_format($pctRealisasi, 1, ',', '.') ?>% dari pagu</div>
        </div>
        <div class="rp-kpi">
            <div class="rp-kpi__label"><i class="bi bi-pie-chart"></i> Sisa Kapasitas</div>
            <div class="rp-kpi__value <?= $totalSisaKapasitas < 0 ? 'text-danger' : '' ?>">Rp <?= number_format($totalSisaKapasitas, 0, ',', '.') ?></div>
            <div class="rp-kpi__sub <?= $totalSisaKapasitas < 0 ? 'text-danger fw-semibold' : '' ?>">
                <?= $totalSisaKapasitas < 0 ? 'Rencana + realisasi melebihi pagu' : 'Pagu − realisasi − rencana (termasuk TU)' ?>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="<?= base_url('rencana-penyerapan') ?>" id="rpFilterForm">
                <div class="row g-3">
                    <div class="col-6 col-md-1">
                        <label for="filter-tahun" class="form-label fw-semibold mb-1">
                            <i class="bi bi-calendar-year me-1 text-primary"></i>Tahun
                        </label>
                        <select name="tahun" id="filter-tahun" class="form-select">
                            <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--): ?>
                                <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="filter-kegiatan" class="form-label fw-semibold mb-1">
                            <i class="bi bi-layers me-1 text-primary"></i>Kegiatan
                        </label>
                        <select name="kegiatan_id" id="filter-kegiatan" class="form-select">
                            <option value="">-- Semua Kegiatan --</option>
                            <?php foreach ($filterKegiatanList as $kg): ?>
                                <option value="<?= $kg['id'] ?>" <?= $filterKegiatan == $kg['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kg['kode_kegiatan'] . ' - ' . $kg['nama_kegiatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="filter-sub-kegiatan" class="form-label fw-semibold mb-1">
                            <i class="bi bi-diagram-3 me-1 text-primary"></i>Sub Kegiatan
                        </label>
                        <select name="sub_kegiatan_id" id="filter-sub-kegiatan" class="form-select">
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
                    <div class="col-6 col-md-2">
                        <label for="filter-mekanisme" class="form-label fw-semibold mb-1">
                            <i class="bi bi-wallet2 me-1 text-primary"></i>Mekanisme
                        </label>
                        <select name="mekanisme" id="filter-mekanisme" class="form-select">
                            <option value="">-- Semua --</option>
                            <option value="GU" <?= $filterMekanisme === 'GU' ? 'selected' : '' ?>>GU — Ganti Uang</option>
                            <option value="TU" <?= $filterMekanisme === 'TU' ? 'selected' : '' ?>>TU — Tambah Uang</option>
                            <option value="LS" <?= $filterMekanisme === 'LS' ? 'selected' : '' ?>>LS — Langsung</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="filter-status" class="form-label fw-semibold mb-1">
                            <i class="bi bi-check2-square me-1 text-primary"></i>Status
                        </label>
                        <select name="status" id="filter-status" class="form-select">
                            <option value="">-- Semua Status --</option>
                            <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            <option value="sudah" <?= $filterStatus === 'sudah' ? 'selected' : '' ?>>Sudah ada rencana</option>
                            <option value="belum" <?= $filterStatus === 'belum' ? 'selected' : '' ?>>Belum ada rencana</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="filter-q" class="form-label fw-semibold mb-1">
                            <i class="bi bi-search me-1 text-primary"></i>Cari
                        </label>
                        <input type="text" name="q" id="filter-q" class="form-select" value="<?= htmlspecialchars($filterQ) ?>" placeholder="Kegiatan / rekening">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="<?= base_url('rencana-penyerapan') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Reset
                        </a>
                    </div>
                </div>
                <?php if ($isFiltered): ?>
                    <div class="mt-2">
                        <span class="badge bg-primary px-3 py-2 fs-6">
                            <i class="bi bi-filter-circle me-1"></i>Filter aktif
                        </span>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const kegiatanSel = document.getElementById('filter-kegiatan');
        const subKegSel = document.getElementById('filter-sub-kegiatan');
        if (!kegiatanSel || !subKegSel) return;

        const allSubOptions = Array.from(subKegSel.querySelectorAll('option[data-kegiatan-id]'));

        function cascade() {
            const selectedKeg = kegiatanSel.value;
            allSubOptions.forEach(function (opt) {
                const match = !selectedKeg || opt.dataset.kegiatanId === selectedKeg;
                opt.style.display = match ? '' : 'none';
                if (!match && opt.selected) {
                    opt.selected = false;
                    subKegSel.value = '';
                }
            });
        }

        kegiatanSel.addEventListener('change', cascade);
        cascade();
    })();
    </script>

    <?php if (empty($groups) && $filterStatus !== 'belum'): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <i class="bi bi-calendar3-range"></i>
                    <p>
                        <?php if ($filterStatus === 'selesai'): ?>
                            Tidak ada rekening berstatus selesai dengan filter yang dipilih.
                        <?php elseif ($filterStatus === 'sudah'): ?>
                            Tidak ada rekening yang sudah punya rencana dengan filter yang dipilih.
                        <?php elseif ($isFiltered): ?>
                            Tidak ada rencana penyerapan dengan filter yang dipilih.
                        <?php else: ?>
                            Belum ada rencana penyerapan untuk tahun <?= (int) $tahun ?>.
                        <?php endif; ?>
                    </p>
                    <button type="button" class="btn btn-primary" data-rp-open-belum>
                        <i class="bi bi-plus-circle me-1"></i> Tambah Rencana Pertama
                    </button>
                </div>
            </div>
        </div>
    <?php elseif (!empty($groups)): ?>
        <div class="d-grid gap-3">
            <?php foreach ($groups as $group): ?>
                <?php
                    $pagu = (float) $group['pagu'];
                    $rencana = (float) $group['total_rencana'];
                    $tuPlan = (float) ($group['tu_plan'] ?? 0);
                    $tuBulanan = $group['tu_bulanan'] ?? [];
                    $rencanaTotal = $rencana + $tuPlan;
                    $real = $group['realisasi'];
                    $pctRencanaRek = $pagu > 0 ? ($rencanaTotal / $pagu) * 100 : null;
                    $pctRealRek = $rencanaTotal > 0 ? min(100, ($real['total'] / $rencanaTotal) * 100) : 0;
                    $sisaKapasitasRek = (float) ($group['sisa_kapasitas'] ?? ($pagu - (float) $real['total'] - $rencanaTotal));
                    $overPagu = $pagu > 0 && ($rencanaTotal + (float) $real['total']) > $pagu;
                    $selesaiManual = !empty($group['selesai_manual']);
                    $selesaiOtomatis = !empty($group['selesai_otomatis']);

                    $realBulanan = [];
                    $maxBulanReal = 0.0;
                    for ($b = 1; $b <= 12; $b++) {
                        $rowBulan = $group['realisasi_bulanan'][$b] ?? ['total' => 0, 'GU' => 0, 'TU' => 0, 'LS' => 0, 'UP' => 0];
                        $realBulanan[$b] = $rowBulan;
                        $maxBulanReal = max($maxBulanReal, (float) $rowBulan['total']);
                    }
                ?>
                <div class="rp-rek-card" id="rek-<?= (int) $group['rekening_id'] ?>">
                    <div class="rp-rek-head">
                        <div class="flex-grow-1">
                            <div class="rp-rek-title">
                                <span class="badge bg-warning text-dark me-1"><?= htmlspecialchars($group['kode_rekening']) ?></span>
                                <?= htmlspecialchars($group['nama_rekening']) ?>
                                <?php if ($selesaiManual): ?>
                                    <span class="badge bg-success ms-1"><i class="bi bi-check-circle me-1"></i>Selesai</span>
                                <?php elseif ($selesaiOtomatis): ?>
                                    <span class="badge bg-success ms-1" title="Pagu sudah habis terpakai realisasi, rencana, dan TU"><i class="bi bi-check-circle me-1"></i>Selesai (sisa 0)</span>
                                <?php endif; ?>
                                <?php if ($overPagu): ?>
                                    <span class="badge bg-danger ms-1" title="Rencana ditambah realisasi melebihi pagu rekening">Rencana + Realisasi &gt; Pagu</span>
                                <?php endif; ?>
                            </div>
                            <div class="rp-rek-path">
                                <i class="bi bi-bezier2 me-1"></i><?= htmlspecialchars($group['kode_program'] . ' / ' . $group['kode_kegiatan'] . ' / ' . $group['kode_sub_kegiatan']) ?>
                                &middot; <?= htmlspecialchars($group['nama_sub_kegiatan']) ?>
                            </div>
                            <div class="rp-rek-path">
                                <i class="bi bi-building me-1"></i><?= htmlspecialchars($group['nama_seksi'] ?: 'Seksi belum ditentukan') ?>
                                &middot; Tahun <?= (int) $group['tahun'] ?>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                <?php if (!$selesaiManual && !$selesaiOtomatis && $sisaKapasitasRek > 0.005): ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-rp-create
                                            data-rek-id="<?= (int) $group['rekening_id'] ?>"
                                            data-tahun="<?= (int) $group['tahun'] ?>"
                                            data-kode="<?= htmlspecialchars($group['kode_rekening']) ?>"
                                            data-nama="<?= htmlspecialchars($group['nama_rekening']) ?>"
                                            data-pagu="<?= $pagu ?>"
                                            data-sisa="<?= $sisaKapasitasRek ?>">
                                        <i class="bi bi-plus-lg me-1"></i>Tambah Bentuk Kegiatan
                                    </button>
                                <?php endif; ?>
                                <?php if ($selesaiManual): ?>
                                    <form method="POST" action="<?= base_url('rencana-penyerapan/selesai/batal') ?>" class="m-0">
                                        <input type="hidden" name="rekening_id" value="<?= (int) $group['rekening_id'] ?>">
                                        <input type="hidden" name="tahun" value="<?= (int) $group['tahun'] ?>">
                                        <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Batal selesai
                                        </button>
                                    </form>
                                <?php elseif (!$selesaiOtomatis): ?>
                                    <form method="POST" action="<?= base_url('rencana-penyerapan/selesai') ?>" class="m-0"
                                          onsubmit="return confirm('Tandai rekening ini selesai? Tidak perlu input rencana lagi.');">
                                        <input type="hidden" name="rekening_id" value="<?= (int) $group['rekening_id'] ?>">
                                        <input type="hidden" name="tahun" value="<?= (int) $group['tahun'] ?>">
                                        <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-check2-circle me-1"></i>Tandai selesai
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rp-rek-summary">
                            <div class="rp-sum-item">
                                <small>Pagu</small>
                                <strong><?= $pagu > 0 ? 'Rp ' . number_format($pagu, 0, ',', '.') : 'Belum ditetapkan' ?></strong>
                            </div>
                            <div class="rp-sum-item">
                                <small>Rencana</small>
                                <strong class="text-primary">Rp <?= number_format($rencanaTotal, 0, ',', '.') ?></strong>
                                <?php if ($pctRencanaRek !== null): ?>
                                    <span class="rp-pct"><?= number_format($pctRencanaRek, 1, ',', '.') ?>% pagu</span>
                                <?php endif; ?>
                                <?php if ($tuPlan > 0): ?>
                                    <span class="rp-pct">termasuk TU Rp <?= number_format($tuPlan, 0, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="rp-sum-item">
                                <small>Realisasi</small>
                                <strong class="text-success">Rp <?= number_format($real['total'], 0, ',', '.') ?></strong>
                            </div>
                            <div class="rp-sum-item">
                                <small>Sisa kapasitas</small>
                                <strong class="<?= $sisaKapasitasRek < 0 ? 'text-danger' : '' ?>">Rp <?= number_format($sisaKapasitasRek, 0, ',', '.') ?></strong>
                            </div>
                        </div>
                    </div>

                    <?php if ($rencanaTotal > 0): ?>
                        <div class="rp-rek-progress">
                            <div class="rp-rek-progress-label">
                                <span>Realisasi terhadap rencana</span>
                                <span><?= number_format($pctRealRek, 1, ',', '.') ?>%</span>
                            </div>
                            <div class="progress" role="progressbar" aria-valuenow="<?= (int) $pctRealRek ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?= number_format($pctRealRek, 2, '.', '') ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="rp-mek-breakdown">
                        <?php if ($real['GU'] > 0): ?>
                            <span class="rp-mek rp-mek--GU">GU</span><span class="rp-mek-amount">Rp <?= number_format($real['GU'], 0, ',', '.') ?></span>
                        <?php endif; ?>
                        <?php if ($real['TU'] > 0): ?>
                            <span class="rp-mek rp-mek--TU">TU</span><span class="rp-mek-amount">Rp <?= number_format($real['TU'], 0, ',', '.') ?></span>
                        <?php endif; ?>
                        <?php if ($real['LS'] > 0): ?>
                            <span class="rp-mek rp-mek--LS">LS</span><span class="rp-mek-amount">Rp <?= number_format($real['LS'], 0, ',', '.') ?></span>
                        <?php endif; ?>
                        <?php if ($real['UP'] > 0): ?>
                            <span class="rp-mek rp-mek--UP" title="Belanja UP yang belum masuk pengajuan GU/TU">UP</span><span class="rp-mek-amount">Rp <?= number_format($real['UP'], 0, ',', '.') ?></span>
                        <?php endif; ?>
                        <?php if ($real['total'] <= 0): ?>
                            <span class="text-muted" style="font-size:.72rem;"><i class="bi bi-hourglass-split me-1"></i>Belum ada realisasi terverifikasi pada tahun ini</span>
                        <?php endif; ?>
                    </div>

                    <div class="rp-timeline">
                        <div class="rp-timeline-head">
                            <div class="rp-timeline-head__label">Bentuk kegiatan &amp; mekanisme</div>
                            <div class="rp-months-head">
                                <?php for ($b = 1; $b <= 12; $b++): ?>
                                    <span class="<?= $b <= $bulanSekarang ? 'rp-month--past' : '' ?>"><?= $bulanShort[$b] ?></span>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <?php foreach ($group['plans'] as $plan): ?>
                            <?php
                                $mulai = max(1, min(12, (int) $plan['bulan_mulai']));
                                $selesai = max($mulai, min(12, (int) $plan['bulan_selesai']));
                                $span = $selesai - $mulai + 1;
                                $mek = $plan['mekanisme'];
                                $nilaiPlan = (float) $plan['nilai_rencana'];
                                $periodeLabel = $bulanFull[$mulai] . ($span > 1 ? ' – ' . $bulanFull[$selesai] : '');
                                $periodeLewat = $bulanSekarang > 0 && $selesai < $bulanSekarang;
                                $tooltip = $plan['bentuk_kegiatan'] . ' · ' . $mek . ' · ' . $periodeLabel . ' · Rp ' . number_format($nilaiPlan, 0, ',', '.')
                                    . ($periodeLewat ? ' · periode sudah lewat' : '');
                            ?>
                            <div class="rp-timeline-row">
                                <div>
                                    <div class="rp-plan-title">
                                        <span class="rp-mek rp-mek--<?= htmlspecialchars($mek) ?>"><?= htmlspecialchars($mek) ?></span>
                                        <span><?= htmlspecialchars($plan['bentuk_kegiatan']) ?></span>
                                    </div>
                                    <div class="rp-plan-meta">
                                        <span><i class="bi bi-calendar-range me-1"></i><?= htmlspecialchars($periodeLabel) ?></span>
                                        <span class="fw-bold text-dark">Rp <?= number_format($nilaiPlan, 0, ',', '.') ?></span>
                                        <?php if (!empty($plan['keterangan'])): ?>
                                            <span class="text-muted" title="<?= htmlspecialchars($plan['keterangan']) ?>"><i class="bi bi-info-circle me-1"></i>Catatan</span>
                                        <?php endif; ?>
                                        <?php if ($periodeLewat): ?>
                                            <span class="badge bg-secondary" title="Periode rencana sudah terlewati">Sudah lewat</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rp-plan-actions">
                                        <button type="button" class="btn btn-outline-primary" title="Edit rencana"
                                                data-rp-edit
                                                data-plan-id="<?= (int) $plan['id'] ?>"
                                                data-rek-id="<?= (int) $group['rekening_id'] ?>"
                                                data-tahun="<?= (int) $group['tahun'] ?>"
                                                data-kode="<?= htmlspecialchars($group['kode_rekening']) ?>"
                                                data-nama="<?= htmlspecialchars($group['nama_rekening']) ?>"
                                                data-pagu="<?= $pagu ?>"
                                                data-sisa="<?= $sisaKapasitasRek ?>"
                                                data-bentuk="<?= htmlspecialchars($plan['bentuk_kegiatan']) ?>"
                                                data-keterangan="<?= htmlspecialchars($plan['keterangan'] ?? '') ?>"
                                                data-mek="<?= htmlspecialchars($mek) ?>"
                                                data-mulai="<?= (int) $plan['bulan_mulai'] ?>"
                                                data-selesai="<?= (int) $plan['bulan_selesai'] ?>"
                                                data-nilai="<?= (float) $plan['nilai_rencana'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="<?= base_url('rencana-penyerapan/delete/' . (int) $plan['id']) ?>" onsubmit="return confirm('Hapus rencana <?= htmlspecialchars(addslashes($plan['bentuk_kegiatan'])) ?>?');" style="display:inline;">
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus rencana">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="rp-track-wrap">
                                    <div class="rp-track">
                                        <?php for ($c = 1; $c <= 12; $c++): ?><div class="rp-cell<?= $c <= $bulanSekarang ? ' rp-cell--past' : '' ?>"></div><?php endfor; ?>
                                        <div class="rp-bar rp-bar--<?= htmlspecialchars($mek) ?><?= $periodeLewat ? ' rp-bar--lewat' : '' ?>"
                                             style="grid-column: <?= $mulai ?> / span <?= $span ?>;"
                                             title="<?= htmlspecialchars($tooltip) ?>">
                                            <?= $span >= 2 ? htmlspecialchars($shortRupiah($nilaiPlan)) : '' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($tuPlan > 0): ?>
                            <div class="rp-timeline-row rp-timeline-row--tu">
                                <div>
                                    <div class="rp-plan-title">
                                        <span class="rp-mek rp-mek--TU">TU</span>
                                        <span>Rencana otomatis dari pengajuan TU</span>
                                    </div>
                                    <div class="rp-plan-uraian">Pengajuan TU berjalan dihitung sebagai rencana rekening ini, tidak perlu entri ulang.</div>
                                    <div class="rp-plan-meta">
                                        <span class="fw-bold text-dark">Rp <?= number_format($tuPlan, 0, ',', '.') ?></span>
                                        <span class="text-muted"><i class="bi bi-lightning-charge me-1"></i>Otomatis</span>
                                    </div>
                                </div>
                                <div class="rp-track-wrap">
                                    <div class="rp-track">
                                        <?php for ($c = 1; $c <= 12; $c++): ?><div class="rp-cell<?= $c <= $bulanSekarang ? ' rp-cell--past' : '' ?>"></div><?php endfor; ?>
                                        <?php foreach ($tuBulanan as $bulanTu => $nilaiTu): ?>
                                            <?php if ((float) $nilaiTu <= 0) continue; ?>
                                            <div class="rp-bar rp-bar--TU"
                                                 style="grid-column: <?= max(1, min(12, (int) $bulanTu)) ?> / span 1;"
                                                 title="<?= htmlspecialchars($bulanFull[$bulanTu] . ': Rp ' . number_format((float) $nilaiTu, 0, ',', '.') . ' (pengajuan TU)') ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="rp-timeline-row rp-timeline-row--real">
                            <div>
                                <div class="rp-plan-title">
                                    <span class="rp-mek rp-mek--UP">REAL</span>
                                    <span>Realisasi terverifikasi per bulan</span>
                                </div>
                                <div class="rp-plan-uraian">Transaksi berstatus terverifikasi, dikelompokkan per bulan dan mekanisme.</div>
                                <div class="rp-plan-meta">
                                    <?php foreach (['GU', 'TU', 'LS', 'UP'] as $mekReal): ?>
                                        <?php if ($real[$mekReal] > 0): ?>
                                            <span class="rp-mek rp-mek--<?= $mekReal ?>"><?= $mekReal ?></span>
                                            <span class="fw-semibold text-dark">Rp <?= number_format($real[$mekReal], 0, ',', '.') ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ($real['total'] > 0): ?>
                                        <span class="text-muted">Total Rp <?= number_format($real['total'], 0, ',', '.') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rp-track-wrap">
                                <div class="rp-track rp-track--real">
                                    <?php for ($c = 1; $c <= 12; $c++): ?>
                                        <?php
                                            $rowBulan = $realBulanan[$c];
                                            $totalBulan = (float) $rowBulan['total'];
                                        ?>
                                        <div class="rp-cell rp-cell--real<?= $c <= $bulanSekarang ? ' rp-cell--past' : '' ?>">
                                            <?php if ($totalBulan > 0): ?>
                                                <?php
                                                    $tinggi = $maxBulanReal > 0 ? max(4, ($totalBulan / $maxBulanReal) * 100) : 0;
                                                    $rincian = [];
                                                    foreach (['GU', 'TU', 'LS', 'UP'] as $mekBulan) {
                                                        if ((float) $rowBulan[$mekBulan] > 0) {
                                                            $rincian[] = $mekBulan . ' Rp ' . number_format((float) $rowBulan[$mekBulan], 0, ',', '.');
                                                        }
                                                    }
                                                    $tooltipBulan = $bulanFull[$c] . ': Rp ' . number_format($totalBulan, 0, ',', '.')
                                                        . ($rincian ? ' (' . implode(' · ', $rincian) . ')' : '');
                                                ?>
                                                <div class="rp-vbar" style="height: <?= number_format($tinggi, 2, '.', '') ?>%;" title="<?= htmlspecialchars($tooltipBulan) ?>">
                                                    <?php foreach (['GU', 'TU', 'LS', 'UP'] as $mekBulan): ?>
                                                        <?php if ((float) $rowBulan[$mekBulan] > 0): ?>
                                                            <span class="rp-vseg--<?= $mekBulan ?>" style="flex: <?= (float) $rowBulan[$mekBulan] ?> 1 0;"></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endfor; ?>
                                    <?php if ($real['total'] <= 0): ?>
                                        <span class="rp-empty-bar">Belum ada realisasi terverifikasi pada tahun ini</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $pagination['page'] <= 1 ? '#' : $paginationBase . $sep . 'page=' . ($pagination['page'] - 1) ?>">«</a>
                    </li>
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                        <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $paginationBase . $sep . 'page=' . $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $pagination['page'] >= $pagination['totalPages'] ? '#' : $paginationBase . $sep . 'page=' . ($pagination['page'] + 1) ?>">»</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($filterStatus === '' || $filterStatus === 'belum'): ?>
    <!-- Rekening ber-pagu yang belum punya rencana -->
    <div class="card mt-4" id="rpBelumCard">
        <button class="rp-belum-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#rpBelumBox" aria-expanded="<?= $filterStatus === 'belum' ? 'true' : 'false' ?>" aria-controls="rpBelumBox">
            <i class="bi bi-exclamation-circle text-warning"></i>
            Rekening ber-pagu yang belum direncanakan
            <span class="badge <?= $belumCount > 0 ? 'bg-warning text-dark' : 'bg-success' ?>"><?= (int) $belumCount ?></span>
            <i class="bi bi-chevron-down ms-auto"></i>
        </button>
        <div class="collapse <?= $filterStatus === 'belum' ? 'show' : '' ?>" id="rpBelumBox">
            <div class="card-body pt-0">
                <?php if (empty($belumDirencanakan)): ?>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle me-1"></i>Semua rekening yang memiliki pagu pada filter ini sudah punya rencana, tertutup TU, atau ditandai selesai.
                    </div>
                <?php else: ?>
                    <?php if ($belumCount > count($belumDirencanakan)): ?>
                        <p class="text-muted small mb-2">Menampilkan <?= count($belumDirencanakan) ?> dari <?= (int) $belumCount ?> rekening.</p>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rekening</th>
                                    <th>Kegiatan / Sub Kegiatan</th>
                                    <th class="text-end">Pagu</th>
                                    <th class="text-end">Sisa</th>
                                    <th>Keterangan</th>
                                    <th class="text-center" style="width:190px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($belumDirencanakan as $rek): ?>
                                    <?php
                                        $sisaRek = (float) $rek['nilai_pagu'] - (float) ($rek['total_realisasi'] ?? 0);
                                        $realisasiRek = (float) ($rek['total_realisasi'] ?? 0);
                                        $tuSelesaiCount = (int) ($rek['tu_selesai_count'] ?? 0);
                                        $tuDitolakCount = (int) ($rek['tu_ditolak_count'] ?? 0);
                                        $tuSelesaiTotal = (float) ($rek['tu_selesai_total'] ?? 0);
                                        $tuDitolakTotal = (float) ($rek['tu_ditolak_total'] ?? 0);
                                        $adaKeterangan = $realisasiRek > 0.005 || $tuSelesaiCount > 0 || $tuDitolakCount > 0;
                                    ?>
                                    <tr id="rek-<?= (int) $rek['id'] ?>" data-rek-row="<?= (int) $rek['id'] ?>">
                                        <td>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($rek['kode_rekening']) ?></span>
                                            <div class="small"><?= htmlspecialchars($rek['nama_rekening']) ?></div>
                                        </td>
                                        <td class="small text-muted">
                                            <?= htmlspecialchars($rek['kode_kegiatan'] . ' / ' . $rek['kode_sub_kegiatan']) ?>
                                            <div><?= htmlspecialchars($rek['nama_sub_kegiatan']) ?></div>
                                        </td>
                                        <td class="text-end fw-semibold">Rp <?= number_format((float) $rek['nilai_pagu'], 0, ',', '.') ?></td>
                                        <td class="text-end">Rp <?= number_format($sisaRek, 0, ',', '.') ?></td>
                                        <td>
                                            <?php if ($realisasiRek > 0.005): ?>
                                                <div class="rp-rek-note rp-rek-note--real" title="Transaksi terverifikasi tahun ini; nilainya sudah mengurangi sisa kapasitas.">
                                                    <i class="bi bi-check2-circle"></i>
                                                    <span>Realisasi <strong>Rp <?= number_format($realisasiRek, 0, ',', '.') ?></strong></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($tuSelesaiCount > 0): ?>
                                                <div class="rp-rek-note rp-rek-note--tu" title="Pengajuan TU dengan SPJ selesai; nilainya sudah tercatat sebagai realisasi.">
                                                    <i class="bi bi-lightning-charge"></i>
                                                    <span>TU selesai<?= $tuSelesaiCount > 1 ? ' (' . $tuSelesaiCount . ' pengajuan)' : '' ?> <strong>Rp <?= number_format($tuSelesaiTotal, 0, ',', '.') ?></strong></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($tuDitolakCount > 0): ?>
                                                <div class="rp-rek-note rp-rek-note--tolak" title="Pengajuan TU ditolak; tidak dihitung sebagai rencana maupun realisasi.">
                                                    <i class="bi bi-x-circle"></i>
                                                    <span>TU ditolak<?= $tuDitolakCount > 1 ? ' (' . $tuDitolakCount . ' pengajuan)' : '' ?> <strong>Rp <?= number_format($tuDitolakTotal, 0, ',', '.') ?></strong></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!$adaKeterangan): ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-rp-create
                                                        data-rek-id="<?= (int) $rek['id'] ?>"
                                                        data-tahun="<?= (int) $tahun ?>"
                                                        data-kode="<?= htmlspecialchars($rek['kode_rekening']) ?>"
                                                        data-nama="<?= htmlspecialchars($rek['nama_rekening']) ?>"
                                                        data-pagu="<?= (float) $rek['nilai_pagu'] ?>"
                                                        data-sisa="<?= $sisaRek ?>">
                                                    <i class="bi bi-plus-circle me-1"></i>Buat
                                                </button>
                                                <form method="POST" action="<?= base_url('rencana-penyerapan/selesai') ?>" class="m-0"
                                                      onsubmit="return confirm('Tandai rekening ini selesai? Tidak perlu input rencana lagi.');">
                                                    <input type="hidden" name="rekening_id" value="<?= (int) $rek['id'] ?>">
                                                    <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
                                                    <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-check2-circle me-1"></i>Selesai
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($filterStatus !== 'belum'): ?>
    <div class="card mt-4">
        <button class="rp-belum-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#rpSelesaiBox" aria-expanded="<?= $filterStatus === 'selesai' ? 'true' : 'false' ?>" aria-controls="rpSelesaiBox">
            <i class="bi bi-check2-circle text-success"></i>
            Rekening ditandai selesai
            <span class="badge bg-success"><?= count($selesaiList) ?></span>
            <i class="bi bi-chevron-down ms-auto"></i>
        </button>
        <div class="collapse <?= $filterStatus === 'selesai' ? 'show' : '' ?>" id="rpSelesaiBox">
            <div class="card-body pt-0">
                <?php if (empty($selesaiList)): ?>
                    <p class="text-muted small mb-0">Belum ada rekening yang ditandai selesai. Rekening dengan sisa kapasitas 0 otomatis dianggap selesai tanpa perlu ditandai.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rekening</th>
                                    <th>Sub Kegiatan</th>
                                    <th class="text-end">Pagu</th>
                                    <th class="text-end">Sisa kapasitas</th>
                                    <th class="text-center" style="width:130px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selesaiList as $rek): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($rek['kode_rekening']) ?></span>
                                            <div class="small"><?= htmlspecialchars($rek['nama_rekening']) ?></div>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars($rek['kode_sub_kegiatan'] . ' · ' . $rek['nama_sub_kegiatan']) ?></td>
                                        <td class="text-end">Rp <?= number_format((float) $rek['total_pagu'], 0, ',', '.') ?></td>
                                        <td class="text-end <?= (float) $rek['sisa_kapasitas'] < 0 ? 'text-danger' : '' ?>">Rp <?= number_format((float) $rek['sisa_kapasitas'], 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <form method="POST" action="<?= base_url('rencana-penyerapan/selesai/batal') ?>" class="m-0">
                                                <input type="hidden" name="rekening_id" value="<?= (int) $rek['id'] ?>">
                                                <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
                                                <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Batal
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<datalist id="rpBentukList">
    <?php foreach ([
        'Pengadaan Barang', 'Pengadaan Bibit', 'Pengadaan Jasa', 'Pengadaan Jasa Konsultansi',
        'Belanja Modal', 'Perjalanan Dinas', 'Honorarium / Narasumber', 'Belanja ATK',
        'Sewa', 'Pemeliharaan', 'Lainnya',
    ] as $bentukOpt): ?>
        <option value="<?= htmlspecialchars($bentukOpt) ?>"></option>
    <?php endforeach; ?>
</datalist>

<template id="rpInlineFormTpl">
    <form class="rp-if" method="post" novalidate>
        <div class="rp-if-head">
            <div class="min-w-0">
                <div class="rp-if-title">
                    <span class="badge bg-warning text-dark me-1 rp-if-kode"></span>
                    <span class="rp-if-nama"></span>
                </div>
                <div class="rp-if-meta">
                    <span>Pagu <strong class="rp-if-pagu">-</strong></span>
                    <span>Sisa kapasitas <strong class="rp-if-sisa">menghitung…</strong></span>
                </div>
            </div>
            <button type="button" class="btn-close rp-if-close" aria-label="Tutup form rencana"></button>
        </div>

        <div class="rp-if-alert" data-err="umum" hidden>
            <i class="bi bi-exclamation-triangle"></i><span class="rp-if-alert-text"></span>
        </div>

        <input type="hidden" name="rekening_id" value="">
        <input type="hidden" name="tahun" value="">
        <input type="hidden" name="id" value="">

        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <label class="form-label">Bentuk kegiatan</label>
                <input type="text" name="bentuk_kegiatan" class="form-control" list="rpBentukList"
                       maxlength="150" placeholder="Contoh: Pengadaan Bibit" autocomplete="off">
                <div class="rp-if-err" data-err="bentuk_kegiatan"></div>
            </div>
            <div class="col-12 col-lg-7">
                <label class="form-label">Mekanisme pembayaran</label>
                <div class="rp-if-mek">
                    <label class="rp-if-mek-opt">
                        <input type="radio" name="mekanisme" value="GU">
                        <span><strong>GU</strong><small>Ganti Uang</small></span>
                    </label>
                    <label class="rp-if-mek-opt">
                        <input type="radio" name="mekanisme" value="TU">
                        <span><strong>TU</strong><small>Tambah Uang</small></span>
                    </label>
                    <label class="rp-if-mek-opt">
                        <input type="radio" name="mekanisme" value="LS">
                        <span><strong>LS</strong><small>Langsung</small></span>
                    </label>
                </div>
                <div class="rp-if-err" data-err="mekanisme"></div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Bulan mulai</label>
                <select name="bulan_mulai" class="form-select"></select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Bulan selesai</label>
                <select name="bulan_selesai" class="form-select"></select>
                <div class="rp-if-err" data-err="bulan"></div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Nilai rencana</span>
                    <button type="button" class="btn btn-link btn-sm p-0 rp-if-fill" hidden>Isi sisa</button>
                </label>
                <input type="text" name="nilai_rencana" class="form-control text-end" inputmode="numeric"
                       placeholder="0" autocomplete="off">
                <div class="rp-if-hint rp-if-limit"></div>
                <div class="rp-if-err" data-err="nilai_rencana"></div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Keterangan (opsional)</label>
                <input type="text" name="keterangan" class="form-control" maxlength="255"
                       placeholder="Nama rekanan / catatan" autocomplete="off">
                <div class="rp-if-err" data-err="keterangan"></div>
            </div>
        </div>

        <div class="rp-if-foot">
            <div class="rp-if-status" aria-live="polite"></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary rp-if-cancel">Batal</button>
                <button type="submit" class="btn btn-primary rp-if-save" disabled>
                    <i class="bi bi-check-circle me-1"></i><span class="rp-if-save-label">Simpan Rencana</span>
                </button>
            </div>
        </div>
    </form>
</template>

<script>
window.RP_BASE = <?= json_encode(base_url()) ?>;
window.RP_BULAN = <?= json_encode($bulanFull, JSON_UNESCAPED_UNICODE) ?>;
window.RP_INLINE = <?= json_encode([
    'form'   => $inlineForm,
    'errors' => $inlineErrors,
    'action' => $inlineAction,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: 'null' ?>;
</script>

<script>
(function () {
    const tpl = document.getElementById('rpInlineFormTpl');
    if (!tpl) return;

    const BASE = window.RP_BASE || '';
    const BULAN = window.RP_BULAN || {};
    let current = null;

    const parseNilai = function (raw) {
        const digits = String(raw || '').replace(/[^\d]/g, '');
        return digits ? Number(digits) : 0;
    };
    const rp = function (value) {
        return 'Rp ' + Math.round(Number(value) || 0).toLocaleString('id-ID');
    };

    function closeForm() {
        if (!current) return;
        if (current.host && current.host.parentNode) {
            current.host.parentNode.removeChild(current.host);
        }
        current = null;
    }

    function insertHost(btn) {
        const row = btn.closest('tr[data-rek-row]');
        if (row) {
            const host = document.createElement('tr');
            host.className = 'rp-if-row';
            const cell = document.createElement('td');
            cell.colSpan = 6;
            host.appendChild(cell);
            row.after(host);
            return { host: host, root: cell };
        }

        const timelineRow = btn.closest('.rp-timeline-row');
        if (timelineRow) {
            const host = document.createElement('div');
            host.className = 'rp-if-row rp-if-row--card';
            timelineRow.after(host);
            return { host: host, root: host };
        }

        const head = btn.closest('.rp-rek-card') && btn.closest('.rp-rek-card').querySelector('.rp-rek-head');
        if (head) {
            const host = document.createElement('div');
            host.className = 'rp-if-row rp-if-row--card';
            head.after(host);
            return { host: host, root: host };
        }

        return null;
    }

    function showBelumBox(anchor) {
        if (!anchor || !anchor.closest('#rpBelumBox')) return;
        const box = document.getElementById('rpBelumBox');
        if (!box || box.classList.contains('show')) return;
        if (window.bootstrap && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(box).show();
        } else {
            box.classList.add('show');
        }
    }

    function openForm(btn, opts) {
        opts = opts || {};
        closeForm();

        const formData = opts.form || null;
        const rekId = Number((formData && formData.rekening_id) || btn.dataset.rekId || 0);
        const tahun = Number((formData && formData.tahun) || btn.dataset.tahun || 0);
        const planId = Number((formData && formData.id) || btn.dataset.planId || 0);
        if (rekId <= 0 || tahun <= 0) return;

        showBelumBox(btn);
        const placed = insertHost(btn);
        if (!placed) return;

        placed.root.appendChild(tpl.content.cloneNode(true));
        const form = placed.root.querySelector('form');
        const d = btn.dataset;

        const inst = {
            form: form,
            host: placed.host,
            rekId: rekId,
            tahun: tahun,
            planId: planId || null,
            limit: null,
            seq: 0,
            touched: { bentuk: false, mekanisme: false, nilai: false, bulan: false },
        };

        form.action = planId
            ? BASE + 'rencana-penyerapan/update/' + planId
            : BASE + 'rencana-penyerapan/store';

        form.querySelector('[name="rekening_id"]').value = String(rekId);
        form.querySelector('[name="tahun"]').value = String(tahun);
        form.querySelector('[name="id"]').value = planId ? String(planId) : '';

        form.querySelector('.rp-if-kode').textContent = d.kode || '';
        form.querySelector('.rp-if-nama').textContent = d.nama || '';
        if (d.pagu !== undefined && d.pagu !== '') {
            form.querySelector('.rp-if-pagu').textContent = rp(d.pagu);
        }
        if (d.sisa !== undefined && d.sisa !== '') {
            form.querySelector('.rp-if-sisa').textContent = rp(d.sisa);
        }
        form.querySelector('.rp-if-save-label').textContent = planId ? 'Simpan Perubahan' : 'Simpan Rencana';

        const mulaiSel = form.querySelector('[name="bulan_mulai"]');
        const selesaiSel = form.querySelector('[name="bulan_selesai"]');
        const nowMonth = new Date().getMonth() + 1;
        for (let i = 1; i <= 12; i++) {
            const opt = document.createElement('option');
            opt.value = String(i);
            opt.textContent = BULAN[i] || String(i);
            mulaiSel.appendChild(opt);
            selesaiSel.appendChild(opt.cloneNode(true));
        }

        const source = formData || {
            bentuk_kegiatan: d.bentuk || '',
            keterangan: d.keterangan || '',
            mekanisme: d.mek || '',
            bulan_mulai: Number(d.mulai) || nowMonth,
            bulan_selesai: Number(d.selesai) || nowMonth,
            nilai_rencana: d.nilai || '',
        };

        const bentukInput = form.querySelector('[name="bentuk_kegiatan"]');
        const ketInput = form.querySelector('[name="keterangan"]');
        const nilaiInput = form.querySelector('[name="nilai_rencana"]');

        bentukInput.value = source.bentuk_kegiatan || '';
        ketInput.value = source.keterangan || '';
        if (source.mekanisme) {
            const radio = form.querySelector('input[name="mekanisme"][value="' + source.mekanisme + '"]');
            if (radio) radio.checked = true;
        }
        mulaiSel.value = String(source.bulan_mulai || nowMonth);
        selesaiSel.value = String(source.bulan_selesai || nowMonth);
        const nilaiAwal = parseNilai(source.nilai_rencana);
        nilaiInput.value = nilaiAwal > 0 ? nilaiAwal.toLocaleString('id-ID') : '';

        const errors = opts.errors || {};
        Object.keys(errors).forEach(function (key) {
            if (key === 'umum' || key === 'rekening_id' || key === 'tahun') {
                const alert = form.querySelector('[data-err="umum"]');
                if (alert) {
                    alert.hidden = false;
                    alert.querySelector('.rp-if-alert-text').textContent = errors[key];
                }
                return;
            }
            if (key === 'bentuk_kegiatan') inst.touched.bentuk = true;
            if (key === 'mekanisme') inst.touched.mekanisme = true;
            if (key === 'nilai_rencana') inst.touched.nilai = true;
            if (key === 'bulan_mulai' || key === 'bulan_selesai') inst.touched.bulan = true;
        });

        form.querySelector('.rp-if-close').addEventListener('click', closeForm);
        form.querySelector('.rp-if-cancel').addEventListener('click', closeForm);

        bentukInput.addEventListener('input', function () {
            inst.touched.bentuk = true;
            refresh(inst);
        });
        Array.prototype.forEach.call(form.querySelectorAll('input[name="mekanisme"]'), function (radio) {
            radio.addEventListener('change', function () {
                inst.touched.mekanisme = true;
                refresh(inst);
            });
        });
        [mulaiSel, selesaiSel].forEach(function (sel) {
            sel.addEventListener('change', function () {
                inst.touched.bulan = true;
                refresh(inst);
            });
        });
        nilaiInput.addEventListener('input', function () {
            inst.touched.nilai = true;
            const n = parseNilai(nilaiInput.value);
            nilaiInput.value = n > 0 ? n.toLocaleString('id-ID') : '';
            refresh(inst);
        });
        ketInput.addEventListener('input', function () { refresh(inst); });

        form.querySelector('.rp-if-fill').addEventListener('click', function () {
            if (inst.limit === null || inst.limit <= 0.005) return;
            inst.touched.nilai = true;
            nilaiInput.value = Math.floor(inst.limit).toLocaleString('id-ID');
            refresh(inst);
            nilaiInput.focus();
        });

        current = inst;
        refresh(inst);
        fetchInfo(inst);

        placed.host.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function () { bentukInput.focus({ preventScroll: true }); }, 220);
    }

    function fieldError(inst, key) {
        return inst.form.querySelector('[data-err="' + key + '"]');
    }

    function setError(inst, key, message, active) {
        const el = fieldError(inst, key);
        if (el) el.textContent = active ? message : '';
    }

    function refresh(inst) {
        const form = inst.form;
        const bentukInput = form.querySelector('[name="bentuk_kegiatan"]');
        const bentuk = bentukInput.value.trim();
        const mekanisme = form.querySelector('input[name="mekanisme"]:checked');
        const mulaiSel = form.querySelector('[name="bulan_mulai"]');
        const selesaiSel = form.querySelector('[name="bulan_selesai"]');
        const nilaiInput = form.querySelector('[name="nilai_rencana"]');
        const mulai = Number(mulaiSel.value || 0);
        const selesai = Number(selesaiSel.value || 0);
        const nilai = parseNilai(nilaiInput.value);
        const bulanBad = mulai > 0 && selesai > 0 && selesai < mulai;

        let nilaiMessage = '';
        if (nilai <= 0) {
            nilaiMessage = 'Nilai rencana harus lebih dari 0.';
        } else if (inst.limit !== null && nilai > inst.limit + 0.005) {
            nilaiMessage = 'Nilai melebihi sisa kapasitas (' + rp(Math.max(0, inst.limit)) + ').';
        }
        const nilaiBad = nilaiMessage !== '';

        setError(inst, 'bentuk_kegiatan', 'Bentuk kegiatan wajib diisi.', inst.touched.bentuk && !bentuk);
        bentukInput.classList.toggle('is-invalid', inst.touched.bentuk && !bentuk);

        setError(inst, 'mekanisme', 'Pilih salah satu mekanisme pembayaran.', inst.touched.mekanisme && !mekanisme);

        setError(inst, 'bulan', 'Bulan selesai tidak boleh sebelum bulan mulai.', inst.touched.bulan && bulanBad);
        mulaiSel.classList.toggle('is-invalid', inst.touched.bulan && bulanBad);
        selesaiSel.classList.toggle('is-invalid', inst.touched.bulan && bulanBad);

        setError(inst, 'nilai_rencana', nilaiMessage, inst.touched.nilai && nilaiBad);
        nilaiInput.classList.toggle('is-invalid', inst.touched.nilai && nilaiBad);

        const issues = [];
        if (!bentuk) issues.push('isi bentuk kegiatan');
        if (!mekanisme) issues.push('pilih mekanisme');
        if (bulanBad) issues.push('bulan selesai ≥ bulan mulai');
        if (nilaiBad) issues.push(nilai <= 0 ? 'isi nilai rencana' : 'nilai ≤ sisa kapasitas');

        const invalid = issues.length > 0;
        const saveBtn = form.querySelector('.rp-if-save');
        const status = form.querySelector('.rp-if-status');
        saveBtn.disabled = invalid;
        status.textContent = invalid ? 'Lengkapi: ' + issues.join(' · ') : 'Siap disimpan.';
        status.classList.toggle('is-invalid', invalid);
        const reason = invalid ? 'Tidak bisa disimpan: ' + issues.join(', ') + '.' : '';
        saveBtn.title = reason;
    }

    function fetchInfo(inst) {
        const seq = ++inst.seq;
        const limitEl = inst.form.querySelector('.rp-if-limit');
        const fillBtn = inst.form.querySelector('.rp-if-fill');
        limitEl.textContent = 'Menghitung sisa kapasitas…';

        let url = BASE + 'rencana-penyerapan/rekening-info?rekening_id=' + encodeURIComponent(inst.rekId)
            + '&tahun=' + encodeURIComponent(inst.tahun);
        if (inst.planId) url += '&exclude_id=' + encodeURIComponent(inst.planId);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (inst !== current || seq !== inst.seq) return;
                if (!data || !data.ok) {
                    limitEl.textContent = 'Sisa kapasitas gagal dimuat.';
                    fillBtn.hidden = true;
                    refresh(inst);
                    return;
                }
                inst.limit = data.sisa_kapasitas === null ? null : Number(data.sisa_kapasitas);
                if (data.pagu !== null && data.pagu !== undefined) {
                    inst.form.querySelector('.rp-if-pagu').textContent = rp(data.pagu);
                }
                if (inst.limit !== null) {
                    inst.form.querySelector('.rp-if-sisa').textContent = rp(inst.limit);
                    limitEl.textContent = 'Maks: ' + rp(Math.max(0, inst.limit)) + ' (sisa kapasitas)';
                    fillBtn.hidden = !(inst.limit > 0.005);
                } else {
                    limitEl.textContent = 'Sisa kapasitas belum tersedia.';
                    fillBtn.hidden = true;
                }
                refresh(inst);
            })
            .catch(function () {
                if (inst !== current || seq !== inst.seq) return;
                limitEl.textContent = 'Sisa kapasitas gagal dimuat.';
                fillBtn.hidden = true;
                refresh(inst);
            });
    }

    document.addEventListener('click', function (event) {
        const createBtn = event.target.closest('[data-rp-create]');
        if (createBtn) {
            event.preventDefault();
            openForm(createBtn, {});
            return;
        }
        const editBtn = event.target.closest('[data-rp-edit]');
        if (editBtn) {
            event.preventDefault();
            openForm(editBtn, {});
            return;
        }
        const openBelum = event.target.closest('[data-rp-open-belum]');
        if (openBelum) {
            event.preventDefault();
            const box = document.getElementById('rpBelumBox');
            if (box && !box.classList.contains('show')) {
                if (window.bootstrap && window.bootstrap.Collapse) {
                    window.bootstrap.Collapse.getOrCreateInstance(box).show();
                } else {
                    box.classList.add('show');
                }
            }
            const card = document.getElementById('rpBelumCard');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    const pending = window.RP_INLINE;
    if (pending && pending.form && pending.form.rekening_id) {
        const data = pending.form;
        let anchor = null;
        if (pending.action === 'update' && data.id) {
            anchor = document.querySelector('[data-rp-edit][data-plan-id="' + data.id + '"]');
        }
        if (!anchor) {
            anchor = document.querySelector('[data-rek-row="' + data.rekening_id + '"] [data-rp-create]');
        }
        if (!anchor) {
            const card = document.getElementById('rek-' + data.rekening_id);
            anchor = card ? card.querySelector('[data-rp-create]') : null;
        }
        if (anchor) {
            showBelumBox(anchor);
            openForm(anchor, { form: data, errors: pending.errors || {}, action: pending.action });
        }
    }
})();
</script>
