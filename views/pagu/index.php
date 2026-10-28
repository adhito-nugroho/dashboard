<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get flash message
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$tahunAktif = isset($_GET['tahun']) && is_numeric($_GET['tahun']) ? (int) $_GET['tahun'] : null;
$totalAwal = (float) ($totalPaguAwal ?? $totalPaguKeseluruhan ?? 0);
$totalAkhir = (float) ($totalPaguKeseluruhan ?? 0);
$selisihTotal = $totalAkhir - $totalAwal;
$persenSelisih = $totalAwal > 0 ? ($selisihTotal / $totalAwal * 100) : null;
$jumlahPerubahan = (int) ($ringkasanPerubahan['jumlah_perubahan'] ?? 0);
$jumlahRekeningBerubah = (int) ($ringkasanPerubahan['jumlah_rekening_berubah'] ?? 0);
$toneSelisih = $selisihTotal > 0 ? 'up' : ($selisihTotal < 0 ? 'down' : 'flat');

$totalData = (int) ($pagination['total'] ?? count($pagus));
$showAll = !empty($pagination['showAll']);
$currentPage = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['totalPages'] ?? 1);
$perPage = (int) ($pagination['perPage'] ?? 10);
$rangeStart = $totalData > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$rangeEnd = min($totalData, $rangeStart + count($pagus) - 1);

$showAllUrl = base_url('pagu') . '?' . http_build_query(array_filter([
    'tahun' => $tahunAktif,
    'show_all' => 1,
]));
$paginatedUrl = base_url('pagu') . ($tahunAktif !== null ? '?tahun=' . $tahunAktif : '');
$laporanUrl = base_url('pagu/laporan?tahun=' . ($tahunAktif ?? (int) date('Y')));

$pageUrl = static function (int $target) use ($pagination, $tahunAktif): string {
    $query = [];
    if ($tahunAktif !== null) {
        $query['tahun'] = $tahunAktif;
    }
    $query['page'] = $target;
    return $pagination['baseUrl'] . '?' . http_build_query($query);
};

$rp = static fn($value): string => number_format((float) $value, 0, ',', '.');
$windowStart = max(1, $currentPage - 2);
$windowEnd = min($totalPages, $currentPage + 2);
?>

<style>
    .pagu-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .pagu-header__title {
        display: flex;
        align-items: center;
        gap: .9rem;
        min-width: 0;
    }

    .pagu-header__icon {
        width: 48px;
        height: 48px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: var(--primary-50);
        color: var(--primary);
        font-size: 1.35rem;
        box-shadow: inset 0 0 0 1px var(--primary-100);
    }

    .pagu-header__heading {
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -.03em;
        color: var(--gray-900);
        margin: 0 0 .15rem;
    }

    .pagu-header__sub {
        margin: 0;
        color: var(--gray-500);
        font-size: .85rem;
    }

    .pagu-header__actions {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .pagu-btn {
        height: 42px;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-weight: 600;
        font-size: .82rem;
        border-radius: 10px;
        padding: 0 1rem;
        white-space: nowrap;
    }

    .pagu-year {
        display: flex;
        align-items: center;
        gap: .35rem;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: .22rem .35rem .22rem .65rem;
        height: 42px;
    }

    .pagu-year i {
        color: var(--gray-400);
        font-size: .9rem;
    }

    .pagu-year input {
        border: 0;
        outline: none;
        width: 92px;
        font-size: .82rem;
        font-weight: 600;
        color: var(--gray-700);
        background: transparent;
    }

    .pagu-year input::-webkit-outer-spin-button,
    .pagu-year input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .pagu-year input[type=number] {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    .pagu-year__btn {
        height: 32px;
        padding: 0 .7rem;
        border-radius: 7px;
        border: 0;
        background: var(--primary-50);
        color: var(--primary);
        font-size: .75rem;
        font-weight: 700;
    }

    .pagu-year__btn:hover {
        background: var(--primary-100);
    }

    /* Summary cards */
    .pagu-stat {
        position: relative;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 1.1rem 1.25rem 1.05rem 1.4rem;
        box-shadow: var(--shadow-xs);
        overflow: hidden;
        height: 100%;
        transition: transform var(--transition-base), box-shadow var(--transition-base);
    }

    .pagu-stat::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--gray-300);
    }

    .pagu-stat--awal::before { background: var(--gray-400); }
    .pagu-stat--papbd::before { background: var(--primary); }
    .pagu-stat--up::before { background: var(--success); }
    .pagu-stat--down::before { background: var(--danger); }

    .pagu-stat:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .pagu-stat__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .45rem;
    }

    .pagu-stat__label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--gray-500);
    }

    .pagu-stat__icon {
        width: 34px;
        height: 34px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        background: var(--gray-100);
        color: var(--gray-500);
        font-size: .95rem;
    }

    .pagu-stat--papbd .pagu-stat__icon {
        background: var(--primary-50);
        color: var(--primary);
    }

    .pagu-stat--up .pagu-stat__icon {
        background: var(--status-success-bg);
        color: var(--success);
    }

    .pagu-stat--down .pagu-stat__icon {
        background: var(--status-danger-bg);
        color: var(--danger);
    }

    .pagu-stat__value {
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.25;
        color: var(--gray-900);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pagu-stat__value--primary { color: var(--primary); }
    .pagu-stat__value--up { color: var(--success); }
    .pagu-stat__value--down { color: var(--danger); }

    .pagu-stat__cur {
        font-size: .85rem;
        font-weight: 600;
        color: var(--gray-400);
        margin-right: .2rem;
    }

    .pagu-stat__meta {
        display: flex;
        align-items: center;
        gap: .45rem;
        flex-wrap: wrap;
        margin-top: .4rem;
        font-size: .74rem;
        color: var(--gray-500);
    }

    .pagu-trend {
        display: inline-flex;
        align-items: center;
        gap: .15rem;
        padding: .1rem .4rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
    }

    .pagu-trend--up { background: var(--status-success-bg); color: var(--success); }
    .pagu-trend--down { background: var(--status-danger-bg); color: var(--danger); }
    .pagu-trend--flat { background: var(--gray-100); color: var(--gray-500); }

    /* Table card */
    .pagu-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xs);
        overflow: hidden;
    }

    .pagu-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--gray-100);
    }

    .pagu-toolbar__search {
        flex: 1 1 320px;
        max-width: 560px;
    }

    .pagu-search {
        position: relative;
    }

    .pagu-search > i {
        position: absolute;
        left: .95rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        font-size: .9rem;
        pointer-events: none;
    }

    .pagu-search input {
        width: 100%;
        height: 40px;
        border: 1px solid var(--gray-200);
        border-radius: 999px;
        background: var(--gray-50);
        padding: 0 1rem 0 2.4rem;
        font-size: .82rem;
        color: var(--gray-700);
        outline: none;
        transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
    }

    .pagu-search input::placeholder { color: var(--gray-400); }

    .pagu-search input:focus {
        background: #fff;
        border-color: var(--primary-light);
        box-shadow: 0 0 0 3px var(--primary-50);
    }

    .pagu-toolbar__hint {
        display: flex;
        align-items: center;
        gap: .3rem;
        margin-top: .4rem;
        padding-left: .35rem;
        font-size: .7rem;
        color: var(--gray-400);
    }

    .pagu-toolbar__right {
        display: flex;
        align-items: center;
        gap: .65rem;
        flex-wrap: wrap;
        padding-top: .35rem;
    }

    .pagu-count {
        font-size: .75rem;
        color: var(--gray-500);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pagu-showall {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: .75rem;
        font-weight: 600;
        color: var(--primary);
        background: #fff;
        padding: .35rem .65rem;
    }

    .pagu-showall:hover {
        background: var(--primary-50);
        border-color: var(--primary-100);
        color: var(--primary-dark);
    }

    /* Table */
    .pagu-table {
        min-width: 1080px;
        margin: 0;
    }

    .pagu-table thead th {
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        border-top: 0;
        padding: .65rem .85rem;
        font-size: .64rem;
        font-weight: 700;
        letter-spacing: .055em;
        color: var(--gray-500);
        vertical-align: bottom;
    }

    .pagu-table thead th.pagu-th-num {
        text-align: right;
    }

    .pagu-th-unit {
        display: block;
        font-size: .58rem;
        font-weight: 600;
        letter-spacing: .04em;
        color: var(--gray-400);
        text-transform: none;
    }

    .pagu-table tbody td {
        padding: .7rem .85rem;
        border-bottom: 1px solid var(--gray-100);
        font-size: .82rem;
        color: var(--gray-700);
        vertical-align: middle;
    }

    .pagu-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .pagu-row-no {
        width: 40px;
        text-align: center;
        font-size: .74rem !important;
        color: var(--gray-400) !important;
        font-variant-numeric: tabular-nums;
    }

    .pagu-codes {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: .2rem;
    }

    .pagu-chip {
        display: inline-block;
        padding: .12rem .4rem;
        border-radius: 6px;
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .015em;
        line-height: 1.4;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pagu-chip--program {
        background: var(--primary-50);
        color: #3730a3;
    }

    .pagu-chip--kegiatan {
        background: var(--gray-100);
        color: var(--gray-600);
    }

    .pagu-chip--rek {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .pagu-sub {
        min-width: 210px;
        max-width: 290px;
        color: var(--gray-700);
        line-height: 1.45;
    }

    .pagu-rek {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: .25rem;
        min-width: 190px;
        max-width: 240px;
    }

    .pagu-rek__name {
        line-height: 1.4;
        color: var(--gray-600);
    }

    .pagu-num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        font-size: .82rem;
    }

    .pagu-num--muted { color: var(--gray-500) !important; }
    .pagu-num--danger { color: var(--danger) !important; font-weight: 700; }
    .pagu-num__main { font-weight: 700; color: var(--gray-800); }

    .pagu-delta {
        display: inline-flex;
        align-items: center;
        gap: .1rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pagu-delta--up { color: var(--success); }
    .pagu-delta--down { color: var(--danger); }
    .pagu-delta--flat { color: var(--gray-300); }

    .pagu-badge {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
        margin-top: .2rem;
        padding: .1rem .4rem;
        border-radius: 999px;
        font-size: .62rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .pagu-badge--danger {
        background: var(--status-danger-bg);
        color: var(--danger);
        border: 1px solid var(--status-danger-border);
    }

    .pagu-table thead th.col-aksi,
    .pagu-table tbody td.col-aksi {
        position: sticky;
        right: 0;
        background: #fff;
        box-shadow: -10px 0 14px -14px rgba(15, 23, 42, .45);
        z-index: 1;
    }

    .pagu-table thead th.col-aksi {
        background: var(--gray-50);
    }

    .pagu-table tbody tr:hover td {
        background-color: var(--primary-50) !important;
    }

    .pagu-actions {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .pagu-act {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        background: #fff;
        color: var(--gray-500);
        font-size: .82rem;
        transition: all var(--transition-fast);
    }

    .pagu-act:hover {
        color: var(--primary);
        border-color: var(--primary-100);
        background: var(--primary-50);
    }

    .pagu-act--danger:hover {
        color: var(--danger);
        border-color: var(--status-danger-border);
        background: var(--status-danger-bg);
    }

    /* Footer & pagination */
    .pagu-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: .8rem 1.15rem;
        border-top: 1px solid var(--gray-100);
        background: var(--gray-50);
    }

    .pagu-footer__meta {
        font-size: .75rem;
        color: var(--gray-500);
        font-variant-numeric: tabular-nums;
    }

    .pagu-pagination .page-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 .45rem;
        margin: 0 1px;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        font-size: .75rem;
        font-weight: 600;
        color: var(--gray-600);
        background: #fff;
    }

    .pagu-pagination .page-link:hover {
        background: var(--primary-50);
        border-color: var(--primary-100);
        color: var(--primary);
    }

    .pagu-pagination .page-item.active .page-link {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    .pagu-pagination .page-item.disabled .page-link {
        color: var(--gray-300);
        background: var(--gray-50);
        pointer-events: none;
    }

    /* Empty & no-result states */
    .pagu-empty {
        padding: 3.25rem 1rem;
        text-align: center;
    }

    .pagu-empty__icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: var(--gray-100);
        color: var(--gray-400);
        font-size: 1.7rem;
    }

    .pagu-empty h5 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: .3rem;
    }

    .pagu-empty p {
        font-size: .82rem;
        color: var(--gray-500);
        margin-bottom: 1.1rem;
    }

    .pagu-noresult {
        padding: 2.5rem 1rem !important;
        text-align: center;
        color: var(--gray-500);
    }

    .pagu-noresult i {
        display: block;
        font-size: 1.6rem;
        color: var(--gray-300);
        margin-bottom: .5rem;
    }

    @media (max-width: 991.98px) {
        .pagu-header__actions { width: 100%; }
    }

    @media (max-width: 575.98px) {
        .pagu-header__heading { font-size: 1.3rem; }
        .pagu-header__actions > * { flex: 1 1 auto; justify-content: center; }
        .pagu-year { width: 100%; justify-content: space-between; }
        .pagu-year input { width: 100%; }
        .pagu-stat__value { font-size: 1.3rem; }
        .pagu-toolbar__right { width: 100%; justify-content: space-between; }
    }
</style>

<div class="container-fluid py-4">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert" data-auto-dismiss>
            <i class="bi <?= $flashType === 'error' ? 'bi-exclamation-circle' : ($flashType === 'success' ? 'bi-check-circle' : 'bi-info-circle') ?> mt-1"></i>
            <div><?= htmlspecialchars($flashMessage) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="pagu-header">
        <div class="pagu-header__title">
            <div class="pagu-header__icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <h2 class="pagu-header__heading">Data Pagu</h2>
                <p class="pagu-header__sub">Kelola alokasi anggaran tahunan dan perubahan PAPBD</p>
            </div>
        </div>
        <div class="pagu-header__actions">
            <form method="GET" action="<?= base_url('pagu') ?>" class="pagu-year">
                <label class="visually-hidden" for="filterTahun">Tahun anggaran</label>
                <i class="bi bi-calendar3"></i>
                <input type="number" id="filterTahun" name="tahun" min="2000" max="2100" value="<?= $tahunAktif ?? '' ?>" placeholder="Semua tahun">
                <?php if ($showAll): ?>
                    <input type="hidden" name="show_all" value="1">
                <?php endif; ?>
                <button class="pagu-year__btn" type="submit">Tampil</button>
            </form>
            <a href="<?= $laporanUrl ?>" class="btn btn-outline-secondary pagu-btn">
                <i class="bi bi-clipboard-data"></i><span>Laporan APBD vs PAPBD</span>
            </a>
            <a href="<?= base_url('pagu/create') ?>" class="btn btn-primary pagu-btn">
                <i class="bi bi-plus-lg"></i><span>Tambah Pagu</span>
            </a>
        </div>
    </div>

    <?php if (!empty($pagus)): ?>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="pagu-stat pagu-stat--awal">
                    <div class="pagu-stat__top">
                        <span class="pagu-stat__label">Total APBD Awal</span>
                        <span class="pagu-stat__icon"><i class="bi bi-wallet2"></i></span>
                    </div>
                    <div class="pagu-stat__value"><span class="pagu-stat__cur">Rp</span><?= $rp($totalAwal) ?></div>
                    <div class="pagu-stat__meta">
                        <span>Nilai sebelum perubahan PAPBD</span>
                        <span>·</span>
                        <span><?= $totalData ?> rekening</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="pagu-stat pagu-stat--papbd">
                    <div class="pagu-stat__top">
                        <span class="pagu-stat__label">Total PAPBD (Saat Ini)</span>
                        <span class="pagu-stat__icon"><i class="bi bi-clipboard-check"></i></span>
                    </div>
                    <div class="pagu-stat__value pagu-stat__value--primary"><span class="pagu-stat__cur">Rp</span><?= $rp($totalAkhir) ?></div>
                    <div class="pagu-stat__meta">
                        <?php if ($jumlahPerubahan > 0): ?>
                            <span><?= $jumlahPerubahan ?>x perubahan</span>
                            <span>·</span>
                            <span><?= $jumlahRekeningBerubah ?> rekening berubah</span>
                        <?php else: ?>
                            <span>Belum ada perubahan anggaran</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="pagu-stat pagu-stat--<?= $toneSelisih ?>">
                    <div class="pagu-stat__top">
                        <span class="pagu-stat__label">Selisih Perubahan</span>
                        <span class="pagu-stat__icon">
                            <i class="bi <?= $toneSelisih === 'up' ? 'bi-arrow-up-right' : ($toneSelisih === 'down' ? 'bi-arrow-down-right' : 'bi-dash-lg') ?>"></i>
                        </span>
                    </div>
                    <div class="pagu-stat__value <?= $toneSelisih === 'up' ? 'pagu-stat__value--up' : ($toneSelisih === 'down' ? 'pagu-stat__value--down' : '') ?>">
                        <?php if ($toneSelisih !== 'flat'): ?>
                            <span class="pagu-stat__cur"><?= $toneSelisih === 'up' ? '+' : '−' ?> Rp</span>
                        <?php else: ?>
                            <span class="pagu-stat__cur">Rp</span>
                        <?php endif; ?>
                        <?= $rp(abs($selisihTotal)) ?>
                    </div>
                    <div class="pagu-stat__meta">
                        <?php if ($persenSelisih !== null): ?>
                            <span class="pagu-trend pagu-trend--<?= $toneSelisih ?>">
                                <?= $persenSelisih > 0 ? '+' : ($persenSelisih < 0 ? '−' : '') ?><?= number_format(abs($persenSelisih), 1, ',', '.') ?>%
                            </span>
                            <span>dari APBD awal</span>
                        <?php else: ?>
                            <span>Belum ada APBD awal sebagai pembanding</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="pagu-card">
        <?php if (empty($pagus)): ?>
            <div class="pagu-empty">
                <div class="pagu-empty__icon"><i class="bi bi-inbox"></i></div>
                <h5>Belum ada data pagu<?= $tahunAktif !== null ? ' untuk tahun ' . $tahunAktif : '' ?></h5>
                <p>Tambahkan alokasi anggaran pertama untuk mulai mengelola pagu.</p>
                <a href="<?= base_url('pagu/create') ?>" class="btn btn-primary pagu-btn">
                    <i class="bi bi-plus-lg"></i> Tambah Pagu
                </a>
            </div>
        <?php else: ?>
            <div class="pagu-toolbar">
                <div class="pagu-toolbar__search">
                    <div class="pagu-search">
                        <i class="bi bi-search"></i>
                        <input type="text"
                               id="searchInput"
                               placeholder="Cari kode rekening, nama rekening, program, kegiatan, atau sub kegiatan..."
                               autocomplete="off"
                               aria-label="Cari data pagu">
                    </div>
                    <div class="pagu-toolbar__hint">
                        <i class="bi bi-info-circle"></i>
                        <span>Pencarian real-time pada data yang sedang ditampilkan</span>
                    </div>
                </div>
                <div class="pagu-toolbar__right">
                    <span id="searchInfo" class="pagu-count">Menampilkan <?= $rangeStart ?>–<?= $rangeEnd ?> dari <?= $totalData ?> rekening</span>
                    <?php if ($showAll): ?>
                        <a href="<?= $paginatedUrl ?>" class="pagu-showall">
                            <i class="bi bi-list-ul"></i> Tampilkan per halaman
                        </a>
                    <?php elseif ($totalPages > 1): ?>
                        <a href="<?= $showAllUrl ?>" class="pagu-showall">
                            <i class="bi bi-arrows-expand"></i> Tampilkan semua
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table pagu-table">
                    <thead>
                        <tr>
                            <th class="pagu-row-no">#</th>
                            <th>Program / Kegiatan</th>
                            <th>Sub Kegiatan</th>
                            <th>Rekening</th>
                            <th class="pagu-th-num">APBD Awal<span class="pagu-th-unit">(Rp)</span></th>
                            <th class="pagu-th-num">PAPBD<span class="pagu-th-unit">(Rp)</span></th>
                            <th class="pagu-th-num">Selisih<span class="pagu-th-unit">(Rp)</span></th>
                            <th class="pagu-th-num">Sisa<span class="pagu-th-unit">(Rp)</span></th>
                            <th class="text-center col-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagus as $index => $pagu): ?>
                            <?php
                            $paguAwal = (float) ($pagu['pagu_awal'] ?? $pagu['nilai_pagu']);
                            $paguAkhir = (float) $pagu['nilai_pagu'];
                            $selisih = (float) ($pagu['selisih'] ?? 0);
                            $sisa = (float) ($pagu['sisa_baru'] ?? ($pagu['nilai_pagu'] - ($pagu['realisasi'] ?? 0)));
                            ?>
                            <tr>
                                <td class="pagu-row-no"><?= $rangeStart + $index ?></td>
                                <td>
                                    <div class="pagu-codes">
                                        <span class="pagu-chip pagu-chip--program" title="Kode Program"><?= htmlspecialchars($pagu['kode_program']) ?></span>
                                        <span class="pagu-chip pagu-chip--kegiatan" title="Kode Kegiatan"><?= htmlspecialchars($pagu['kode_kegiatan']) ?></span>
                                    </div>
                                </td>
                                <td class="pagu-sub"><?= htmlspecialchars($pagu['nama_sub_kegiatan']) ?></td>
                                <td>
                                    <div class="pagu-rek">
                                        <span class="pagu-chip pagu-chip--rek"><?= htmlspecialchars($pagu['kode_rekening']) ?></span>
                                        <span class="pagu-rek__name"><?= htmlspecialchars($pagu['nama_rekening']) ?></span>
                                    </div>
                                </td>
                                <td class="text-end pagu-num pagu-num--muted"><?= $rp($paguAwal) ?></td>
                                <td class="text-end pagu-num">
                                    <div class="pagu-num__main"><?= $rp($paguAkhir) ?></div>
                                    <?php if (!empty($pagu['over_realisasi'])): ?>
                                        <span class="pagu-badge pagu-badge--danger" title="Realisasi melebihi pagu">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Over realisasi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($selisih > 0): ?>
                                        <span class="pagu-delta pagu-delta--up"><i class="bi bi-arrow-up-short"></i><?= $rp($selisih) ?></span>
                                    <?php elseif ($selisih < 0): ?>
                                        <span class="pagu-delta pagu-delta--down"><i class="bi bi-arrow-down-short"></i><?= $rp(abs($selisih)) ?></span>
                                    <?php else: ?>
                                        <span class="pagu-delta pagu-delta--flat">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pagu-num <?= $sisa < 0 ? 'pagu-num--danger' : ($sisa == 0 ? 'pagu-num--muted' : '') ?>">
                                    <?= $rp($sisa) ?>
                                </td>
                                <td class="text-center col-aksi">
                                    <div class="pagu-actions">
                                        <a href="<?= base_url('pagu/riwayat/' . $pagu['id']) ?>" class="pagu-act" title="Riwayat PAPBD" aria-label="Riwayat PAPBD">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="<?= base_url('pagu/edit/' . $pagu['id']) ?>" class="pagu-act" title="Edit pagu" aria-label="Edit pagu">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?= base_url('pagu/delete/' . $pagu['id']) ?>"
                                           class="pagu-act pagu-act--danger"
                                           title="Hapus pagu"
                                           aria-label="Hapus pagu"
                                           data-confirm-delete
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus pagu ini?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalData > 0): ?>
                <div class="pagu-footer">
                    <span class="pagu-footer__meta">
                        Menampilkan <strong><?= $rangeStart ?>–<?= $rangeEnd ?></strong> dari <strong><?= $totalData ?></strong> rekening
                        <?= $tahunAktif !== null ? '· tahun ' . $tahunAktif : '' ?>
                    </span>
                    <?php if ($totalPages > 1): ?>
                        <nav id="paguPagination" aria-label="Navigasi halaman pagu">
                            <ul class="pagination pagination-sm mb-0 pagu-pagination">
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $currentPage <= 1 ? '#' : $pageUrl(1) ?>" aria-label="Halaman pertama">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $currentPage <= 1 ? '#' : $pageUrl($currentPage - 1) ?>" aria-label="Halaman sebelumnya">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php if ($windowStart > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= $pageUrl(1) ?>">1</a></li>
                                    <?php if ($windowStart > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $pageUrl($p) ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($windowEnd < $totalPages): ?>
                                    <?php if ($windowEnd < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="<?= $pageUrl($totalPages) ?>"><?= $totalPages ?></a></li>
                                <?php endif; ?>
                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : $pageUrl($currentPage + 1) ?>" aria-label="Halaman berikutnya">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : $pageUrl($totalPages) ?>" aria-label="Halaman terakhir">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const searchInfo = document.getElementById('searchInfo');
    const tbody = document.querySelector('.pagu-table tbody');
    const paginationNav = document.getElementById('paguPagination');

    if (!searchInput || !tbody) {
        return;
    }

    const rows = Array.from(tbody.querySelectorAll('tr')).filter((row) => row.id !== 'noResultsRow');
    const totalRows = rows.length;
    const defaultInfo = searchInfo ? searchInfo.textContent : '';

    function setNoResults(visible, searching) {
        let row = document.getElementById('noResultsRow');

        if (searching && visible === 0) {
            if (!row) {
                row = document.createElement('tr');
                row.id = 'noResultsRow';
                row.innerHTML = `<td colspan="9" class="pagu-noresult">
                    <i class="bi bi-search"></i>
                    <div class="fw-semibold">Tidak ada data yang cocok</div>
                    <div class="small text-muted">Coba kata kunci lain atau tekan Esc untuk mereset pencarian.</div>
                </td>`;
                tbody.appendChild(row);
            }
            row.style.display = '';
        } else if (row) {
            row.style.display = 'none';
        }
    }

    searchInput.addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        let visible = 0;

        rows.forEach((row) => {
            const match = term === '' || row.textContent.toLowerCase().includes(term);
            row.style.display = match ? '' : 'none';
            if (match) {
                visible++;
            }
        });

        if (paginationNav) {
            paginationNav.style.display = term === '' ? '' : 'none';
        }
        if (searchInfo) {
            searchInfo.textContent = term === ''
                ? defaultInfo
                : `${visible} dari ${totalRows} data cocok`;
        }

        setNoResults(visible, term !== '');
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            this.value = '';
            this.dispatchEvent(new Event('input'));
            this.blur();
        }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });
});
</script>
