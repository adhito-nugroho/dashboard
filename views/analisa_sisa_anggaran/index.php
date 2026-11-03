<?php
$rp = static fn($value): string => number_format((float) $value, 0, ',', '.');
$rpShort = static function (float $value): string {
    $abs = abs($value);
    if ($abs >= 1_000_000_000) {
        return number_format($value / 1_000_000_000, 2, ',', '.') . ' M';
    }
    if ($abs >= 1_000_000) {
        return number_format($value / 1_000_000, 1, ',', '.') . ' jt';
    }
    if ($abs >= 1_000) {
        return number_format($value / 1_000, 0, ',', '.') . ' rb';
    }
    return (string) (int) $value;
};

$baseUrl = base_url('analisa-sisa-anggaran');
$q = (string) ($filters['q'] ?? '');
$tahun = (int) ($filters['tahun'] ?? date('Y'));
$seksiFilter = (int) ($filters['seksi'] ?? 0);
$minJuta = (float) ($filters['min_juta'] ?? 0);
$sort = (string) ($filters['sort'] ?? 'sisa_desc');

$queryBase = ['tahun' => $tahun];
if ($seksiFilter > 0) { $queryBase['seksi'] = $seksiFilter; }
if ($q !== '') { $queryBase['q'] = $q; }
if ($sort !== 'sisa_desc') { $queryBase['sort'] = $sort; }

$presetJuta = [
    ['label' => 'Semua sisa', 'value' => 0],
    ['label' => '≥ 100 jt', 'value' => 100],
    ['label' => '≥ 250 jt', 'value' => 250],
    ['label' => '≥ 500 jt', 'value' => 500],
    ['label' => '≥ 1 M', 'value' => 1000],
];

$pageUrl = static function (int $target) use ($queryBase, $minJuta, $baseUrl): string {
    $query = $queryBase;
    if ($minJuta > 0) { $query['min_juta'] = $minJuta; }
    $query['page'] = $target;
    return $baseUrl . '?' . http_build_query($query);
};
$chartJson = json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<style>
    .asa-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .asa-header__title { display: flex; align-items: center; gap: .9rem; min-width: 0; }

    .asa-header__icon {
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

    .asa-header__heading {
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -.03em;
        color: var(--gray-900);
        margin: 0 0 .15rem;
    }

    .asa-header__sub { margin: 0; color: var(--gray-500); font-size: .85rem; }

    .asa-btn {
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

    /* Filter bar */
    .asa-filter {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xs);
        padding: 1rem 1.15rem;
        margin-bottom: 1.25rem;
    }

    .asa-filter__row {
        display: flex;
        flex-wrap: wrap;
        gap: .65rem;
        align-items: flex-end;
    }

    .asa-field { display: flex; flex-direction: column; gap: .3rem; }
    .asa-field--grow { flex: 1 1 260px; }

    .asa-field label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--gray-500);
    }

    .asa-control {
        height: 42px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        background: #fff;
        padding: 0 .75rem;
        font-size: .82rem;
        font-weight: 600;
        color: var(--gray-700);
        min-width: 150px;
    }

    .asa-control:focus {
        outline: none;
        border-color: var(--primary-100);
        box-shadow: 0 0 0 3px var(--primary-50);
    }

    .asa-search { position: relative; flex: 1 1 240px; }
    .asa-search i {
        position: absolute;
        left: .85rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        font-size: .9rem;
    }
    .asa-search input {
        width: 100%;
        height: 42px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 0 .9rem 0 2.35rem;
        font-size: .82rem;
        color: var(--gray-700);
        background: #fff;
    }
    .asa-search input:focus {
        outline: none;
        border-color: var(--primary-100);
        box-shadow: 0 0 0 3px var(--primary-50);
    }

    .asa-presets {
        display: flex;
        align-items: center;
        gap: .4rem;
        flex-wrap: wrap;
        margin-top: .85rem;
        padding-top: .85rem;
        border-top: 1px dashed var(--gray-200);
    }

    .asa-presets__label {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--gray-400);
        margin-right: .25rem;
    }

    .asa-chip {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .32rem .7rem;
        border-radius: 999px;
        border: 1px solid var(--gray-200);
        background: var(--gray-50);
        color: var(--gray-600);
        font-size: .75rem;
        font-weight: 700;
        text-decoration: none;
        transition: all var(--transition-fast);
    }
    .asa-chip:hover { border-color: var(--primary-100); color: var(--primary); background: var(--primary-50); }
    .asa-chip.is-active { background: var(--primary); border-color: var(--primary); color: #fff; }

    /* Stat cards */
    .asa-stat {
        position: relative;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        padding: 1rem 1.15rem 1rem 1.35rem;
        box-shadow: var(--shadow-xs);
        height: 100%;
        overflow: hidden;
        transition: transform var(--transition-base), box-shadow var(--transition-base);
    }
    .asa-stat:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .asa-stat::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: var(--gray-300);
    }
    .asa-stat--pagu::before { background: var(--primary); }
    .asa-stat--realisasi::before { background: var(--info); }
    .asa-stat--sisa::before { background: var(--success); }
    .asa-stat--serapan::before { background: var(--warning); }

    .asa-stat__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
    }
    .asa-stat__label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--gray-500);
    }
    .asa-stat__icon {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 9px;
        background: var(--gray-100);
        color: var(--gray-500);
        font-size: .9rem;
    }
    .asa-stat--pagu .asa-stat__icon { background: var(--primary-50); color: var(--primary); }
    .asa-stat--realisasi .asa-stat__icon { background: #e0f2fe; color: var(--info); }
    .asa-stat--sisa .asa-stat__icon { background: var(--status-success-bg); color: var(--success); }
    .asa-stat--serapan .asa-stat__icon { background: var(--status-warning-bg); color: var(--warning); }

    .asa-stat__value {
        font-size: 1.4rem;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.25;
        color: var(--gray-900);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .asa-stat__value--sisa { color: var(--success); }
    .asa-stat__value--serapan { color: var(--warning); }
    .asa-stat__cur { font-size: .8rem; font-weight: 600; color: var(--gray-400); margin-right: .2rem; }
    .asa-stat__meta {
        display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
        margin-top: .35rem; font-size: .73rem; color: var(--gray-500);
    }

    /* Panels */
    .asa-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xs);
        height: 100%;
        overflow: hidden;
    }
    .asa-card__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        padding: 1rem 1.15rem .35rem;
    }
    .asa-card__title {
        font-size: .95rem;
        font-weight: 800;
        letter-spacing: -.01em;
        color: var(--gray-900);
        margin: 0;
    }
    .asa-card__sub { font-size: .74rem; color: var(--gray-500); margin: .1rem 0 0; }
    .asa-card__body { padding: .75rem 1.15rem 1.15rem; }

    .asa-chart-wrap { position: relative; width: 100%; height: 330px; }
    .asa-chart-wrap--donut { height: 300px; }

    .asa-legend-empty {
        display: flex; align-items: center; justify-content: center;
        height: 100%; color: var(--gray-400); font-size: .82rem;
    }

    /* Bucket cards */
    .asa-bucket {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: .8rem .9rem;
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    .asa-bucket__label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--gray-500);
        display: flex;
        align-items: center;
        gap: .35rem;
    }
    .asa-bucket__dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gray-300); }
    .asa-bucket--over .asa-bucket__dot { background: var(--danger); }
    .asa-bucket--a .asa-bucket__dot { background: var(--gray-400); }
    .asa-bucket--b .asa-bucket__dot { background: var(--info); }
    .asa-bucket--c .asa-bucket__dot { background: var(--primary); }
    .asa-bucket--d .asa-bucket__dot { background: var(--success); }
    .asa-bucket__count {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--gray-900);
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
        margin-top: .25rem;
    }
    .asa-bucket__count small { font-size: .7rem; font-weight: 600; color: var(--gray-400); }
    .asa-bucket__total { font-size: .74rem; color: var(--gray-500); font-variant-numeric: tabular-nums; }
    .asa-bucket__bar {
        height: 3px; border-radius: 999px; background: var(--gray-100);
        margin-top: .5rem; overflow: hidden;
    }
    .asa-bucket__bar span { display: block; height: 100%; border-radius: 999px; background: var(--gray-400); }
    .asa-bucket--over .asa-bucket__bar span { background: var(--danger); }
    .asa-bucket--b .asa-bucket__bar span { background: var(--info); }
    .asa-bucket--c .asa-bucket__bar span { background: var(--primary); }
    .asa-bucket--d .asa-bucket__bar span { background: var(--success); }

    /* Table */
    .asa-table { margin: 0; font-size: .82rem; }
    .asa-table thead th {
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--gray-500);
        padding: .7rem .75rem;
        white-space: nowrap;
        vertical-align: middle;
    }
    .asa-table tbody td {
        padding: .65rem .75rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--gray-100);
    }
    .asa-table tbody tr:hover { background: var(--gray-50); }
    .asa-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .asa-num--muted { color: var(--gray-500); }
    .asa-num--sisa { font-weight: 800; color: var(--success); }
    .asa-num--over { font-weight: 800; color: var(--danger); }

    .asa-chip-kode {
        display: inline-block;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .7rem;
        font-weight: 700;
        color: var(--gray-700);
        background: var(--gray-100);
        border-radius: 6px;
        padding: .1rem .4rem;
    }
    .asa-rek__name { display: block; font-weight: 600; color: var(--gray-800); margin-top: .2rem; }
    .asa-meta { font-size: .72rem; color: var(--gray-500); }
    .asa-meta strong { color: var(--gray-700); font-weight: 700; }

    .asa-progress {
        display: flex; align-items: center; gap: .5rem;
        min-width: 120px;
    }
    .asa-progress__track {
        flex: 1; height: 6px; border-radius: 999px; background: var(--gray-100); overflow: hidden;
    }
    .asa-progress__fill { height: 100%; border-radius: 999px; background: var(--primary); }
    .asa-progress__fill--good { background: var(--success); }
    .asa-progress__fill--warn { background: var(--warning); }
    .asa-progress__fill--over { background: var(--danger); }
    .asa-progress__val {
        font-size: .72rem; font-weight: 700; color: var(--gray-600);
        font-variant-numeric: tabular-nums; min-width: 42px; text-align: right;
    }

    .asa-badge {
        display: inline-flex; align-items: center; gap: .2rem;
        padding: .12rem .45rem; border-radius: 999px;
        font-size: .68rem; font-weight: 700;
    }
    .asa-badge--danger { background: var(--status-danger-bg); color: var(--danger); }
    .asa-badge--success { background: var(--status-success-bg); color: var(--success); }
    .asa-badge--warning { background: var(--status-warning-bg); color: var(--warning); }
    .asa-badge--neutral { background: var(--gray-100); color: var(--gray-500); }

    .asa-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--gray-500);
    }
    .asa-empty i { font-size: 2.4rem; color: var(--gray-300); display: block; margin-bottom: .75rem; }
    .asa-empty h5 { font-size: 1rem; font-weight: 800; color: var(--gray-700); margin-bottom: .35rem; }
    .asa-empty p { font-size: .84rem; margin-bottom: 1rem; }

    .asa-pager {
        display: flex; align-items: center; justify-content: space-between;
        gap: .75rem; flex-wrap: wrap;
        padding: .9rem 1.15rem;
        border-top: 1px solid var(--gray-100);
    }
    .asa-pager__info { font-size: .76rem; color: var(--gray-500); }
    .asa-pager__links { display: flex; align-items: center; gap: .3rem; }
    .asa-pager__link {
        min-width: 34px; height: 34px;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0 .55rem;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        background: #fff;
        color: var(--gray-600);
        font-size: .78rem;
        font-weight: 700;
        text-decoration: none;
    }
    .asa-pager__link:hover { border-color: var(--primary-100); color: var(--primary); background: var(--primary-50); }
    .asa-pager__link.is-active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .asa-pager__link.is-disabled { opacity: .45; pointer-events: none; }

    @media (max-width: 575.98px) {
        .asa-header__heading { font-size: 1.3rem; }
        .asa-stat__value { font-size: 1.2rem; }
        .asa-chart-wrap { height: 280px; }
    }
</style>

<div class="container-fluid py-4">
    <div class="asa-header">
        <div class="asa-header__title">
            <div class="asa-header__icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <h2 class="asa-header__heading">Analisa Sisa Anggaran</h2>
                <p class="asa-header__sub">Pantau rekening dengan sisa anggaran besar &amp; prioritas realisasi</p>
            </div>
        </div>
        <div class="asa-header__actions d-flex gap-2 flex-wrap">
            <?php
            $exportParams = $queryBase;
            if ($minJuta > 0) {
                $exportParams['min_juta'] = $minJuta;
            }
            ?>
            <a href="<?= $baseUrl . '/export?' . http_build_query($exportParams) ?>" class="btn btn-primary asa-btn">
                <i class="bi bi-file-earmark-excel"></i><span>Unduh Excel</span>
            </a>
            <button type="button" class="btn btn-outline-secondary asa-btn" id="asaExportBtn">
                <i class="bi bi-filetype-csv"></i><span>Unduh CSV</span>
            </button>
        </div>
    </div>

    <form method="GET" action="<?= $baseUrl ?>" class="asa-filter" id="asaFilterForm">
        <div class="asa-filter__row">
            <div class="asa-field">
                <label for="asaTahun">Tahun anggaran</label>
                <select name="tahun" id="asaTahun" class="asa-control">
                    <?php if (empty($tahunTersedia)): ?>
                        <option value="<?= $tahun ?>"><?= $tahun ?></option>
                    <?php else: ?>
                        <?php foreach ($tahunTersedia as $t): ?>
                            <option value="<?= (int) $t ?>" <?= (int) $t === $tahun ? 'selected' : '' ?>><?= (int) $t ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="asa-field">
                <label for="asaSeksi">Seksi</label>
                <select name="seksi" id="asaSeksi" class="asa-control">
                    <option value="0">Semua seksi</option>
                    <?php foreach ($seksiMap as $sid => $snama): ?>
                        <option value="<?= (int) $sid ?>" <?= (int) $sid === $seksiFilter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($snama) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="asa-field">
                <label for="asaMinJuta">Sisa minimal (juta)</label>
                <input type="number" step="10" min="0" name="min_juta" id="asaMinJuta" class="asa-control"
                       value="<?= $minJuta > 0 ? htmlspecialchars((string) (float) $minJuta) : '' ?>"
                       placeholder="cth. 100" style="min-width:130px;">
            </div>

            <div class="asa-field">
                <label for="asaSort">Urutkan</label>
                <select name="sort" id="asaSort" class="asa-control">
                    <option value="sisa_desc" <?= $sort === 'sisa_desc' ? 'selected' : '' ?>>Sisa terbesar</option>
                    <option value="sisa_asc" <?= $sort === 'sisa_asc' ? 'selected' : '' ?>>Sisa terkecil</option>
                    <option value="pagu_desc" <?= $sort === 'pagu_desc' ? 'selected' : '' ?>>Pagu terbesar</option>
                    <option value="serapan_asc" <?= $sort === 'serapan_asc' ? 'selected' : '' ?>>Serapan terendah</option>
                    <option value="serapan_desc" <?= $sort === 'serapan_desc' ? 'selected' : '' ?>>Serapan tertinggi</option>
                    <option value="nama_asc" <?= $sort === 'nama_asc' ? 'selected' : '' ?>>Nama rekening (A–Z)</option>
                </select>
            </div>

            <div class="asa-field asa-field--grow">
                <label for="asaSearch">Cari</label>
                <div class="asa-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" id="asaSearch" value="<?= htmlspecialchars($q) ?>"
                           placeholder="Kode / nama rekening, sub kegiatan, program..." autocomplete="off">
                </div>
            </div>

            <div class="asa-field">
                <label class="visually-hidden">Terapkan</label>
                <button type="submit" class="btn btn-primary asa-btn">
                    <i class="bi bi-funnel"></i><span>Terapkan</span>
                </button>
            </div>
        </div>

        <div class="asa-presets">
            <span class="asa-presets__label">Filter cepat</span>
            <?php foreach ($presetJuta as $preset): ?>
                <?php
                $pq = $queryBase;
                if ($preset['value'] > 0) { $pq['min_juta'] = $preset['value']; }
                $presetUrl = $baseUrl . '?' . http_build_query($pq);
                $isActive = (float) $preset['value'] === (float) $minJuta;
                ?>
                <a href="<?= $presetUrl ?>" class="asa-chip <?= $isActive ? 'is-active' : '' ?>">
                    <?= $preset['value'] > 0 ? '<i class="bi bi-arrow-up-right"></i>' : '<i class="bi bi-grid"></i>' ?>
                    <?= htmlspecialchars($preset['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="asa-stat asa-stat--pagu">
                <div class="asa-stat__top">
                    <span class="asa-stat__label">Total Pagu</span>
                    <span class="asa-stat__icon"><i class="bi bi-cash-stack"></i></span>
                </div>
                <div class="asa-stat__value"><span class="asa-stat__cur">Rp</span><?= $rp($totalPagu) ?></div>
                <div class="asa-stat__meta">
                    <span><?= number_format($total, 0, ',', '.') ?> rekening sesuai filter</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="asa-stat asa-stat--realisasi">
                <div class="asa-stat__top">
                    <span class="asa-stat__label">Realisasi</span>
                    <span class="asa-stat__icon"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="asa-stat__value"><span class="asa-stat__cur">Rp</span><?= $rp($totalRealisasi) ?></div>
                <div class="asa-stat__meta">
                    <span>Transaksi terverifikasi tahun <?= $tahun ?></span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="asa-stat asa-stat--sisa">
                <div class="asa-stat__top">
                    <span class="asa-stat__label">Sisa Anggaran</span>
                    <span class="asa-stat__icon"><i class="bi bi-piggy-bank"></i></span>
                </div>
                <div class="asa-stat__value asa-stat__value--sisa"><span class="asa-stat__cur">Rp</span><?= $rp($totalSisa) ?></div>
                <div class="asa-stat__meta">
                    <span><?= number_format($sisaCount, 0, ',', '.') ?> rekening masih bersisa</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="asa-stat asa-stat--serapan">
                <div class="asa-stat__top">
                    <span class="asa-stat__label">Serapan</span>
                    <span class="asa-stat__icon"><i class="bi bi-speedometer2"></i></span>
                </div>
                <div class="asa-stat__value asa-stat__value--serapan"><?= number_format($serapanPersen, 1, ',', '.') ?>%</div>
                <div class="asa-stat__meta">
                    <?php if ($overCount > 0): ?>
                        <span class="asa-badge asa-badge--danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $overCount ?> over realisasi</span>
                    <?php else: ?>
                        <span>Realisasi terhadap pagu</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="asa-card">
                <div class="asa-card__head">
                    <div>
                        <h3 class="asa-card__title">10 Rekening dengan Sisa Terbesar</h3>
                        <p class="asa-card__sub">Kandidat prioritas percepatan realisasi</p>
                    </div>
                </div>
                <div class="asa-card__body">
                    <div class="asa-chart-wrap">
                        <canvas id="asaTopChart" aria-label="Grafik sisa anggaran per rekening" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="asa-card">
                <div class="asa-card__head">
                    <div>
                        <h3 class="asa-card__title">Sebaran Sisa per Seksi</h3>
                        <p class="asa-card__sub">Total sisa anggaran menurut penanggung jawab</p>
                    </div>
                </div>
                <div class="asa-card__body">
                    <div class="asa-chart-wrap asa-chart-wrap--donut">
                        <canvas id="asaSeksiChart" aria-label="Grafik sebaran sisa per seksi" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $bucketMeta = [
        'over' => ['cls' => 'asa-bucket--over'],
        '0-100' => ['cls' => 'asa-bucket--a'],
        '100-500' => ['cls' => 'asa-bucket--b'],
        '500-1000' => ['cls' => 'asa-bucket--c'],
        '1000+' => ['cls' => 'asa-bucket--d'],
    ];
    $maxBucketCount = 1;
    foreach ($chartData['buckets'] as $b) {
        $maxBucketCount = max($maxBucketCount, (int) $b['count']);
    }
    ?>
    <div class="row g-2 mb-3">
        <?php foreach ($chartData['buckets'] as $key => $bucket): ?>
            <?php
            $meta = $bucketMeta[$key] ?? ['cls' => '', 'icon' => 'bi-circle'];
            $pct = ((int) $bucket['count'] / $maxBucketCount) * 100;
            ?>
            <div class="col-6 col-md-4 col-xl">
                <div class="asa-bucket <?= $meta['cls'] ?>">
                    <div class="asa-bucket__label">
                        <span class="asa-bucket__dot"></span><?= htmlspecialchars($bucket['label']) ?>
                    </div>
                    <div class="asa-bucket__count"><?= number_format((int) $bucket['count'], 0, ',', '.') ?> <small>rekening</small></div>
                    <div class="asa-bucket__total">Rp <?= $rp($bucket['total']) ?></div>
                    <div class="asa-bucket__bar"><span style="width: <?= number_format($pct, 1, '.', '') ?>%"></span></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="asa-card">
        <div class="asa-card__head" style="align-items:center;">
            <div>
                <h3 class="asa-card__title">Rincian Sisa Anggaran per Rekening</h3>
                <p class="asa-card__sub">
                    Sisa = pagu − realisasi terverifikasi.
                    <?php if ($minJuta > 0): ?>
                        Menampilkan sisa ≥ Rp <?= $rp($minJuta * 1_000_000) ?>.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div class="asa-empty">
                <i class="bi bi-inbox"></i>
                <h5>Tidak ada rekening yang cocok</h5>
                <p>Coba turunkan ambang sisa minimal, ubah seksi, atau hapus kata kunci pencarian.</p>
                <a href="<?= $baseUrl . '?tahun=' . $tahun ?>" class="btn btn-primary asa-btn">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table asa-table" id="asaTable">
                    <thead>
                        <tr>
                            <th style="width:44px;">#</th>
                            <th>Rekening</th>
                            <th>Sub Kegiatan / Seksi</th>
                            <th class="text-end">Pagu (Rp)</th>
                            <th class="text-end">Realisasi (Rp)</th>
                            <th class="text-end">Sisa (Rp)</th>
                            <th style="min-width:140px;">Serapan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageRows as $i => $row): ?>
                            <?php
                            $sisa = (float) $row['sisa'];
                            $serapan = (float) $row['serapan'];
                            $serapanBar = max(0, min(100, $serapan));
                            $barClass = $sisa < 0 ? 'asa-progress__fill--over'
                                : ($serapan >= 80 ? 'asa-progress__fill--good'
                                    : ($serapan >= 40 ? 'asa-progress__fill--warn' : ''));
                            ?>
                            <tr>
                                <td class="asa-num--muted"><?= $rangeStart + $i ?></td>
                                <td>
                                    <span class="asa-chip-kode"><?= htmlspecialchars($row['kode_rekening']) ?></span>
                                    <span class="asa-rek__name"><?= htmlspecialchars($row['nama_rekening']) ?></span>
                                    <span class="asa-meta"><?= htmlspecialchars($row['nama_program']) ?></span>
                                </td>
                                <td>
                                    <div class="asa-meta"><strong><?= htmlspecialchars($row['kode_sub_kegiatan']) ?></strong> · <?= htmlspecialchars($row['nama_sub_kegiatan']) ?></div>
                                    <span class="asa-badge asa-badge--neutral mt-1"><i class="bi bi-building"></i> <?= htmlspecialchars($row['nama_seksi']) ?></span>
                                </td>
                                <td class="asa-num"><?= $rp($row['pagu']) ?></td>
                                <td class="asa-num asa-num--muted"><?= $rp($row['realisasi']) ?></td>
                                <td class="asa-num <?= $sisa < 0 ? 'asa-num--over' : 'asa-num--sisa' ?>">
                                    <?= $rp($sisa) ?>
                                    <?php if ($sisa >= 500_000_000): ?>
                                        <span class="asa-badge asa-badge--success d-block mt-1">Sisa besar</span>
                                    <?php elseif ($sisa < 0): ?>
                                        <span class="asa-badge asa-badge--danger d-block mt-1">Over realisasi</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="asa-progress">
                                        <div class="asa-progress__track">
                                            <div class="asa-progress__fill <?= $barClass ?>" style="width: <?= number_format($serapanBar, 1, '.', '') ?>%"></div>
                                        </div>
                                        <span class="asa-progress__val"><?= number_format($serapan, 1, ',', '.') ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="asa-pager">
                <div class="asa-pager__info">
                    Menampilkan <?= $rangeStart ?>–<?= $rangeEnd ?> dari <?= number_format($total, 0, ',', '.') ?> rekening
                </div>
                <?php if ($totalPages > 1): ?>
                    <div class="asa-pager__links">
                        <a class="asa-pager__link <?= $page <= 1 ? 'is-disabled' : '' ?>"
                           href="<?= $pageUrl(max(1, $page - 1)) ?>"><i class="bi bi-chevron-left"></i></a>
                        <?php
                        $winStart = max(1, $page - 2);
                        $winEnd = min($totalPages, $page + 2);
                        if ($winStart > 1) {
                            echo '<a class="asa-pager__link" href="' . $pageUrl(1) . '">1</a>';
                            if ($winStart > 2) {
                                echo '<span class="asa-pager__info">…</span>';
                            }
                        }
                        for ($pg = $winStart; $pg <= $winEnd; $pg++) {
                            echo '<a class="asa-pager__link ' . ($pg === $page ? 'is-active' : '') . '" href="' . $pageUrl($pg) . '">' . $pg . '</a>';
                        }
                        if ($winEnd < $totalPages) {
                            if ($winEnd < $totalPages - 1) {
                                echo '<span class="asa-pager__info">…</span>';
                            }
                            echo '<a class="asa-pager__link" href="' . $pageUrl($totalPages) . '">' . $totalPages . '</a>';
                        }
                        ?>
                        <a class="asa-pager__link <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                           href="<?= $pageUrl(min($totalPages, $page + 1)) ?>"><i class="bi bi-chevron-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const ASA = <?= $chartJson ?>;
    const rupiah = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v || 0));
    const shortRupiah = (v) => {
        const abs = Math.abs(v);
        if (abs >= 1e9) return (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
        if (abs >= 1e6) return (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' jt';
        return rupiah(v);
    };

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    // Grafik top 10 rekening (horizontal bar)
    const topEl = document.getElementById('asaTopChart');
    if (topEl) {
        if (!ASA.topRekening.length) {
            topEl.parentElement.innerHTML = '<div class="asa-legend-empty">Belum ada rekening dengan sisa positif pada filter ini.</div>';
        } else {
            const labels = ASA.topRekening.map((r) => {
                const name = r.label.split(' · ')[1] || r.label;
                return name.length > 34 ? name.slice(0, 34) + '…' : name;
            });
            const codes = ASA.topRekening.map((r) => r.label.split(' · ')[0]);
            new Chart(topEl, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Sisa anggaran',
                        data: ASA.topRekening.map((r) => r.sisa),
                        backgroundColor: 'rgba(67, 56, 202, 0.85)',
                        hoverBackgroundColor: '#312e81',
                        borderRadius: 6,
                        barThickness: 18,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            callbacks: {
                                title: (items) => codes[items[0].dataIndex] + ' · ' + labels[items[0].dataIndex],
                                label: (ctx) => ' Sisa: ' + rupiah(ctx.raw),
                                afterLabel: (ctx) => ' Pagu: ' + rupiah(ASA.topRekening[ctx.dataIndex].pagu),
                            },
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { callback: (v) => shortRupiah(v), font: { size: 10 } },
                        },
                        y: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    },
                },
            });
        }
    }

    // Sebaran sisa per seksi (doughnut)
    const seksiEl = document.getElementById('asaSeksiChart');
    if (seksiEl) {
        if (!ASA.perSeksi.length) {
            seksiEl.parentElement.innerHTML = '<div class="asa-legend-empty">Belum ada sisa anggaran per seksi.</div>';
        } else {
            const palette = ['#4338ca', '#059669', '#0284c7', '#d97706', '#db2777', '#7c3aed', '#0891b2', '#65a30d', '#ea580c', '#475569'];
            new Chart(seksiEl, {
                type: 'doughnut',
                data: {
                    labels: ASA.perSeksi.map((s) => s.label),
                    datasets: [{
                        data: ASA.perSeksi.map((s) => s.sisa),
                        backgroundColor: ASA.perSeksi.map((_, i) => palette[i % palette.length]),
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 10 }, padding: 12 },
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            callbacks: {
                                label: (ctx) => ' ' + ctx.label + ': ' + rupiah(ctx.raw) + ' (' + ASA.perSeksi[ctx.dataIndex].count + ' rek)',
                            },
                        },
                    },
                },
            });
        }
    }

    // Unduh CSV (seluruh hasil filter, bukan hanya halaman aktif)
    const exportBtn = document.getElementById('asaExportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            const headers = ['Kode Rekening', 'Nama Rekening', 'Sub Kegiatan', 'Seksi', 'Pagu', 'Realisasi', 'Sisa', 'Serapan (%)'];
            const lines = [headers.join(';')];
            ASA.rows.forEach((r) => {
                lines.push([
                    r.kode_rekening,
                    r.nama_rekening,
                    r.kode_sub_kegiatan + ' - ' + r.nama_sub_kegiatan,
                    r.nama_seksi,
                    Math.round(r.pagu),
                    Math.round(r.realisasi),
                    Math.round(r.sisa),
                    r.serapan.toFixed(2),
                ].map((v) => '"' + String(v).replace(/"/g, '""') + '"').join(';'));
            });
            const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'analisa-sisa-anggaran-<?= $tahun ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
})();
</script>
