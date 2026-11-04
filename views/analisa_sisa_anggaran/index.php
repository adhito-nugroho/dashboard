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
$tagarFilter = (string) ($filters['tagar'] ?? '');
$minJuta = (float) ($filters['min_juta'] ?? 0);
$sort = (string) ($filters['sort'] ?? 'sisa_desc');
$showZero = !empty($filters['show_zero']);
$sortLabels = [
    'sisa_desc' => 'Sisa terbesar',
    'sisa_asc' => 'Sisa terkecil',
    'pagu_desc' => 'Pagu terbesar',
    'serapan_asc' => 'Serapan terendah',
    'serapan_desc' => 'Serapan tertinggi',
    'nama_asc' => 'Nama rekening (A–Z)',
];
$sortLabel = $sortLabels[$sort] ?? $sortLabels['sisa_desc'];

$queryBase = ['tahun' => $tahun];
if ($seksiFilter > 0) { $queryBase['seksi'] = $seksiFilter; }
if ($tagarFilter !== '') { $queryBase['tagar'] = $tagarFilter; }
if ($q !== '') { $queryBase['q'] = $q; }
if ($sort !== 'sisa_desc') { $queryBase['sort'] = $sort; }
if ($showZero) { $queryBase['show_zero'] = 1; }

$presetJuta = [
    ['label' => 'Semua sisa', 'value' => 0],
    ['label' => '≥ 100 jt', 'value' => 100],
    ['label' => '≥ 250 jt', 'value' => 250],
    ['label' => '≥ 500 jt', 'value' => 500],
    ['label' => '≥ 1 M', 'value' => 1000],
];

$resetUrl = $baseUrl . '?tahun=' . $tahun;
$toggleQuery = $queryBase;
if ($minJuta > 0) { $toggleQuery['min_juta'] = $minJuta; }
$toggleQuery['show_zero'] = $showZero ? 0 : 1;
$toggleZeroUrl = $baseUrl . '?' . http_build_query($toggleQuery);

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
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .65rem;
        align-items: end;
    }

    .asa-field { display: flex; flex-direction: column; gap: .3rem; min-width: 0; }
    .asa-field--grow { width: 100%; }

    /* Dua baris filter yang seimbang: 4 field di atas, cari + opsi + aksi di bawah. */
    .asa-filter__row > .asa-field:nth-child(1) { grid-column: span 3; }
    .asa-filter__row > .asa-field:nth-child(2) { grid-column: span 4; }
    .asa-filter__row > .asa-field:nth-child(3) { grid-column: span 2; }
    .asa-filter__row > .asa-field:nth-child(4) { grid-column: span 3; }
    .asa-filter__row > .asa-field:nth-child(5) { grid-column: span 4; }
    .asa-filter__row > .asa-field:nth-child(6) { grid-column: span 3; }
    .asa-filter__row > .asa-field:nth-child(7) { grid-column: span 3; }
    .asa-filter__row > .asa-field:nth-child(8) { grid-column: span 2; }

    .asa-field label {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--gray-600);
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

    .asa-search { position: relative; width: 100%; }
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
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--gray-600);
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
        font-family: inherit;
        font-size: .75rem;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
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
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--gray-600);
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
    .asa-stat__cur { font-size: .8rem; font-weight: 600; color: var(--gray-500); margin-right: .2rem; }
    .asa-stat__meta {
        display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
        margin-top: .35rem; font-size: .75rem; color: var(--gray-600);
    }

    /* Panels */
    .asa-card {
        position: relative;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xs);
        height: 100%;
        overflow: hidden;
    }
    /* Indikator loading di area tabel/chart: konten lama tidak dibiarkan tanpa tanda. */
    .asa-card.is-loading::after {
        content: '';
        position: absolute; inset: 0;
        background: rgba(255, 255, 255, .68);
        z-index: 5;
    }
    .asa-card.is-loading::before {
        content: '';
        position: absolute; top: 50%; left: 50%;
        width: 28px; height: 28px; margin: -14px 0 0 -14px;
        border: 3px solid var(--primary-100);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: asaSpin .7s linear infinite;
        z-index: 6;
    }
    @keyframes asaSpin { to { transform: rotate(360deg); } }
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
    .asa-card__sub { font-size: .75rem; color: var(--gray-600); margin: .1rem 0 0; }
    .asa-card__body { padding: .75rem 1.15rem 1.15rem; }

    .asa-chart-wrap { position: relative; width: 100%; height: 380px; }
    .asa-chart-wrap--donut { height: 260px; }

    .asa-legend-empty {
        display: flex; align-items: center; justify-content: center;
        height: 100%; color: var(--gray-600); font-size: .82rem;
    }

    /* Legenda donut dibuat HTML agar nominal + persentase tetap terbaca di layar sempit. */
    .asa-donut-legend {
        list-style: none; margin: .5rem 0 0; padding: 0;
        display: flex; flex-direction: column; gap: .3rem;
    }
    .asa-donut-legend__item {
        display: grid;
        grid-template-columns: 10px minmax(0, 1fr) auto auto;
        align-items: center;
        gap: .45rem;
        font-size: .75rem;
        color: var(--gray-700);
    }
    .asa-donut-legend__dot { width: 10px; height: 10px; border-radius: 50%; }
    .asa-donut-legend__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
    .asa-donut-legend__value { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .asa-donut-legend__pct { font-weight: 700; color: var(--gray-900); white-space: nowrap; }

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
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--gray-600);
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
    .asa-bucket__value {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--gray-900);
        font-variant-numeric: tabular-nums;
        line-height: 1.25;
        margin-top: .3rem;
        white-space: nowrap;
    }
    .asa-bucket__meta { font-size: .75rem; color: var(--gray-600); font-variant-numeric: tabular-nums; }
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
    .asa-table { margin: 0; font-size: .84rem; }
    .asa-table thead th {
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--gray-600);
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
        font-size: .75rem;
        font-weight: 700;
        color: var(--gray-700);
        background: var(--gray-100);
        border-radius: 6px;
        padding: .1rem .4rem;
    }
    .asa-rek__name { display: block; font-weight: 600; color: var(--gray-800); margin-top: .2rem; }
    .asa-meta { font-size: .75rem; color: var(--gray-600); }
    .asa-meta strong { color: var(--gray-800); font-weight: 700; }

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
        font-size: .75rem; font-weight: 700; color: var(--gray-700);
        font-variant-numeric: tabular-nums; min-width: 42px; text-align: right;
    }

    .asa-badge {
        display: inline-flex; align-items: center; gap: .2rem;
        padding: .12rem .45rem; border-radius: 999px;
        font-size: .75rem; font-weight: 700;
    }
    .asa-badge--danger { background: var(--status-danger-bg); color: #b91c1c; }
    .asa-badge--success { background: var(--status-success-bg); color: #047857; }
    .asa-badge--warning { background: var(--status-warning-bg); color: #92400e; }
    .asa-badge--neutral { background: var(--gray-100); color: var(--gray-500); }
    .asa-badge--tag { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .asa-tag-link { text-decoration: none; }
    .asa-tag-link:hover .asa-badge--tag { filter: brightness(.96); }
    .asa-tag-total td { font-weight: 800; background: var(--gray-50); }

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

    .asa-filter__actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
    .asa-auto-note {
        display: inline-flex; align-items: center; gap: .3rem;
        margin-left: auto;
        font-size: .75rem; font-weight: 600; color: var(--gray-600);
    }
    .asa-auto-note i { color: var(--primary); }
    .asa-switch {
        display: inline-flex; align-items: center; gap: .5rem;
        height: 42px; padding: 0 .75rem; white-space: nowrap;
        border: 1px solid var(--gray-200); border-radius: 10px; background: #fff;
        font-size: .78rem; font-weight: 600; color: var(--gray-700);
        cursor: pointer; user-select: none;
    }
    .asa-switch input { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
    /* Label switch jangan ikut gaya label filter (uppercase kecil). */
    .asa-field label.asa-switch {
        text-transform: none;
        letter-spacing: 0;
        font-size: .78rem;
        color: var(--gray-700);
    }

    /* Status hasil */
    .asa-resultbar {
        display: flex; align-items: center; gap: .45rem; flex-wrap: wrap;
        padding: .65rem 1.15rem;
        border-bottom: 1px solid var(--gray-100);
        background: var(--gray-50);
        font-size: .78rem; color: var(--gray-700);
    }
    .asa-resultbar strong { color: var(--gray-900); font-weight: 800; }
    .asa-resultbar__link {
        color: var(--primary); font-weight: 700; text-decoration: underline;
        text-underline-offset: 2px;
    }
    .asa-resultbar__sep { color: var(--gray-400); }

    /* Indikator loading, hanya tampil saat navigasi/filter diproses */
    .asa-loading-bar {
        position: fixed; top: 0; left: 0; z-index: 2000;
        height: 3px; width: 0;
        background: var(--primary);
        box-shadow: 0 0 6px rgba(67, 56, 202, .45);
        transition: width .35s ease;
    }
    .asa-loading-bar.is-loading { width: 85%; }
    [hidden] { display: none !important; }

    /* Ringkasan saat hanya ada satu seksi (pengganti donut) */
    .asa-single {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        height: 100%; gap: .3rem; text-align: center;
    }
    .asa-single__value { font-size: 1.55rem; font-weight: 800; color: var(--success); font-variant-numeric: tabular-nums; }
    .asa-single__label { font-size: .78rem; color: var(--gray-600); }
    .asa-single__meta { font-size: .78rem; color: var(--gray-700); }
    .asa-single__meta strong { color: var(--gray-900); }

    @media (max-width: 767.98px) {
        .asa-header__actions { width: 100%; }
        .asa-header__actions .asa-btn { flex: 1 1 0; min-width: 0; justify-content: center; padding: 0 .6rem; }
        .asa-filter__row { grid-template-columns: 1fr; align-items: stretch; }
        /* :nth-child(n) menyamakan spesifisitas dengan aturan span di atas agar override menang. */
        .asa-filter__row > .asa-field:nth-child(n) { grid-column: auto; width: 100%; }
        .asa-control, .asa-search input { width: 100%; min-width: 0; }
        .asa-filter__actions { width: 100%; }
        .asa-filter__actions .asa-btn { flex: 1 1 auto; justify-content: center; }
        .asa-card__head { flex-direction: column; }
        .asa-table { min-width: 880px; }
        .asa-resultbar { align-items: flex-start; }
    }

    @media (max-width: 575.98px) {
        .asa-header__heading { font-size: 1.3rem; }
        .asa-stat__value { font-size: 1.2rem; }
        .asa-chart-wrap { height: 340px; }
    }
</style>

<div class="container-fluid py-4">
    <!-- Indikator loading: hanya tampil saat filter/navigasi diproses -->
    <div class="asa-loading-bar" id="asaLoadingBar" aria-hidden="true" hidden></div>

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
                <label for="asaTagar">Tagar</label>
                <select name="tagar" id="asaTagar" class="asa-control">
                    <option value="">Semua tagar</option>
                    <?php foreach (($tagarList ?? []) as $tag): ?>
                        <option value="<?= htmlspecialchars($tag) ?>" <?= $tagarFilter === $tag ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tag) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (!empty($hasUntagged)): ?>
                        <option value="__none__" <?= $tagarFilter === '__none__' ? 'selected' : '' ?>>Tanpa tagar</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="asa-field">
                <label class="visually-hidden" for="asaShowZero">Opsi tampilan</label>
                <label class="asa-switch" for="asaShowZero">
                    <input type="checkbox" name="show_zero" id="asaShowZero" value="1" <?= $showZero ? 'checked' : '' ?>>
                    <span>Tampilkan rekening tanpa sisa</span>
                </label>
            </div>

            <div class="asa-field">
                <label class="visually-hidden">Aksi filter</label>
                <div class="asa-filter__actions">
                    <a href="<?= $resetUrl ?>" class="btn btn-outline-secondary asa-btn" id="asaReset">
                        <i class="bi bi-arrow-counterclockwise"></i><span>Reset</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="asa-presets">
            <span class="asa-presets__label">Filter cepat</span>
            <?php foreach ($presetJuta as $preset): ?>
                <?php $isActive = (float) $preset['value'] === (float) $minJuta; ?>
                <button type="button"
                        class="asa-chip <?= $isActive ? 'is-active' : '' ?>"
                        data-juta="<?= (int) $preset['value'] ?>">
                    <?= $preset['value'] > 0 ? '<i class="bi bi-arrow-up-right"></i>' : '<i class="bi bi-grid"></i>' ?>
                    <?= htmlspecialchars($preset['label']) ?>
                </button>
            <?php endforeach; ?>
            <span class="asa-auto-note"><i class="bi bi-lightning-charge-fill"></i> Filter diterapkan otomatis</span>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="asa-stat asa-stat--pagu">
                <div class="asa-stat__top">
                    <span class="asa-stat__label">Total Pagu</span>
                    <span class="asa-stat__icon"><i class="bi bi-cash-stack"></i></span>
                </div>
                <div class="asa-stat__value"><span class="asa-stat__cur">Rp</span><?= $rp($totalPagu) ?></div>
                <div class="asa-stat__meta">
                    <span><?= number_format($baseCount, 0, ',', '.') ?> rekening sesuai filter</span>
                    <?php if ($hiddenZeroCount > 0): ?>
                        <span>·</span>
                        <span><?= number_format($hiddenZeroCount, 0, ',', '.') ?> tanpa sisa disembunyikan</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
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
        <div class="col-12 col-sm-6 col-lg-3">
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
        <div class="col-12 col-sm-6 col-lg-3">
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

    <!-- Rekap per tagar: pagu, realisasi, sisa, serapan -->
    <div class="asa-card mb-3">
        <div class="asa-card__head">
            <div>
                <h3 class="asa-card__title">Realisasi per Tagar</h3>
                <p class="asa-card__sub">
                    Dikelompokkan dari tagar RKA tiap rekening.
                    <?php if ($tagarFilter !== ''): ?>
                        Rekap menampilkan semua tagar; filter tagar hanya memengaruhi tabel utama.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="asa-card__body">
            <?php if (empty($tagRecap)): ?>
                <div class="asa-empty py-4">Belum ada data tagar pada filter ini.</div>
            <?php else: ?>
                <?php
                $tagTotalPagu = array_sum(array_map(fn($t) => (float) $t['pagu'], $tagRecap));
                $tagTotalRealisasi = array_sum(array_map(fn($t) => (float) $t['realisasi'], $tagRecap));
                $tagTotalSisa = $tagTotalPagu - $tagTotalRealisasi;
                $tagTotalSerapan = $tagTotalPagu > 0 ? ($tagTotalRealisasi / $tagTotalPagu) * 100 : 0.0;
                $tagTotalCount = array_sum(array_map(fn($t) => (int) $t['count'], $tagRecap));
                ?>
                <div class="table-responsive">
                    <table class="table asa-table asa-tag-table">
                        <thead>
                            <tr>
                                <th>Tagar</th>
                                <th class="text-end">Rekening</th>
                                <th class="text-end">Pagu (Rp)</th>
                                <th class="text-end">Realisasi (Rp)</th>
                                <th class="text-end">Sisa (Rp)</th>
                                <th class="text-end">Serapan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tagRecap as $t): ?>
                                <?php
                                $tParams = $queryBase;
                                if ($minJuta > 0) { $tParams['min_juta'] = $minJuta; }
                                $tParams['tagar'] = (string) $t['tagar'];
                                $tUrl = $baseUrl . '?' . http_build_query($tParams);
                                $tSisa = (float) $t['sisa'];
                                ?>
                                <tr>
                                    <td>
                                        <a class="asa-tag-link" href="<?= htmlspecialchars($tUrl) ?>"
                                           title="Filter tagar <?= htmlspecialchars($t['label']) ?>">
                                            <span class="asa-badge <?= $t['tagar'] === '__none__' ? 'asa-badge--neutral' : 'asa-badge--tag' ?>">
                                                <?= htmlspecialchars($t['label']) ?>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="asa-num"><?= number_format((int) $t['count'], 0, ',', '.') ?></td>
                                    <td class="asa-num"><?= $rp($t['pagu']) ?></td>
                                    <td class="asa-num asa-num--muted"><?= $rp($t['realisasi']) ?></td>
                                    <td class="asa-num <?= $tSisa < 0 ? 'asa-num--over' : 'asa-num--sisa' ?>"><?= $rp($tSisa) ?></td>
                                    <td class="asa-num"><?= number_format((float) $t['serapan'], 1, ',', '.') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="asa-tag-total">
                                <td>Total</td>
                                <td class="asa-num"><?= number_format($tagTotalCount, 0, ',', '.') ?></td>
                                <td class="asa-num"><?= $rp($tagTotalPagu) ?></td>
                                <td class="asa-num"><?= $rp($tagTotalRealisasi) ?></td>
                                <td class="asa-num <?= $tagTotalSisa < 0 ? 'asa-num--over' : 'asa-num--sisa' ?>"><?= $rp($tagTotalSisa) ?></td>
                                <td class="asa-num"><?= number_format($tagTotalSerapan, 1, ',', '.') ?>%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="asa-card">
                <div class="asa-card__head">
                    <div>
                        <?php
                        $topShown = (int) ($chartData['topShown'] ?? 0);
                        $topCandidates = (int) ($chartData['topCandidates'] ?? 0);
                        ?>
                        <h3 class="asa-card__title">
                            <?php if ($sort === 'sisa_desc'): ?>
                                Rekening dengan Sisa Terbesar
                            <?php else: ?>
                                10 Rekening Teratas
                            <?php endif; ?>
                            <?php if ($topCandidates > 0): ?>
                                (<?= $topShown ?> dari <?= $topCandidates ?>)
                            <?php endif; ?>
                        </h3>
                        <p class="asa-card__sub">
                            <?php if ($topCandidates > 0): ?>
                                <?php if ($sort === 'sisa_desc'): ?>
                                    Diambil dari <?= number_format($topCandidates, 0, ',', '.') ?> rekening bersisa pada filter ini, urut sisa terbesar.
                                <?php else: ?>
                                    10 baris teratas sesuai urutan tabel: <strong><?= htmlspecialchars($sortLabel) ?></strong>.
                                <?php endif; ?>
                            <?php else: ?>
                                Tidak ada rekening pada filter ini.
                            <?php endif; ?>
                        </p>
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
                        <p class="asa-card__sub">
                            <?php if (($seksiMeta['count'] ?? 0) > 0): ?>
                                <?php if (!empty($seksiMeta['filterActive'])): ?>
                                    Grafik menampilkan seluruh <?= (int) $seksiMeta['count'] ?> seksi; filter seksi hanya memengaruhi tabel.
                                <?php else: ?>
                                    <?= (int) $seksiMeta['count'] ?> seksi dengan sisa positif.
                                <?php endif; ?>
                            <?php else: ?>
                                Belum ada sisa positif per seksi pada tahun ini.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="asa-card__body">
                    <?php $singleSeksi = $seksiMeta['single'] ?? null; ?>
                    <?php if ($singleSeksi !== null): ?>
                        <!-- Hanya satu seksi bersisa: donut tidak informatif, ganti angka ringkas. -->
                        <div class="asa-chart-wrap asa-chart-wrap--donut">
                            <div class="asa-single">
                                <div class="asa-single__label">Total sisa — <?= htmlspecialchars($singleSeksi['nama']) ?></div>
                                <div class="asa-single__value">Rp <?= $rp($singleSeksi['sisa']) ?></div>
                                <div class="asa-single__meta">
                                    <strong><?= number_format((float) $singleSeksi['persen'], 1, ',', '.') ?>%</strong>
                                    dari pagu Rp <?= $rp($singleSeksi['pagu']) ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="asa-chart-wrap asa-chart-wrap--donut">
                            <canvas id="asaSeksiChart" aria-label="Grafik sebaran sisa per seksi" role="img"></canvas>
                        </div>
                        <?php
                        // Legenda HTML: nama + nominal + persentase supaya seksi kecil tetap terbaca.
                        $donutPalette = $chartData['palette'] ?? ['#4338ca'];
                        $donutTotal = array_sum(array_map(fn($s) => (float) $s['sisa'], $chartData['perSeksi'] ?? []));
                        ?>
                        <?php if (!empty($chartData['perSeksi'])): ?>
                            <ul class="asa-donut-legend">
                                <?php foreach ($chartData['perSeksi'] as $si => $seksi): ?>
                                    <?php
                                    $seksiPct = $donutTotal > 0 ? (((float) $seksi['sisa']) / $donutTotal) * 100 : 0;
                                    $dotColor = $donutPalette[$si % count($donutPalette)];
                                    ?>
                                    <li class="asa-donut-legend__item">
                                        <span class="asa-donut-legend__dot" style="background: <?= htmlspecialchars($dotColor) ?>"></span>
                                        <span class="asa-donut-legend__name" title="<?= htmlspecialchars($seksi['label']) ?>"><?= htmlspecialchars($seksi['label']) ?></span>
                                        <span class="asa-donut-legend__value">Rp <?= $rp($seksi['sisa']) ?></span>
                                        <span class="asa-donut-legend__pct"><?= number_format($seksiPct, 1, ',', '.') ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
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
    // Bar = proporsi NILAI RUPIAH terhadap total sisa positif (bukan jumlah rekening),
    // agar kartu besar tidak terlihat "kosong" hanya karena jumlah rekeningnya sedikit.
    $positiveBucketTotal = 0.0;
    foreach ($chartData['buckets'] as $bValue) {
        if (($bValue['key'] ?? '') !== 'over') {
            $positiveBucketTotal += (float) $bValue['total'];
        }
    }
    ?>
    <div class="row g-2 mb-3">
        <?php foreach ($chartData['buckets'] as $bucket): ?>
            <?php
            $bucketKey = $bucket['key'] ?? '';
            // Sembunyikan kartu over-realisasi bila tidak ada kasusnya → tepat 4 kartu kelompok.
            if ($bucketKey === 'over' && (int) $bucket['count'] === 0) {
                continue;
            }
            $meta = $bucketMeta[$bucketKey] ?? ['cls' => ''];
            $isOver = $bucketKey === 'over';
            $pct = (!$isOver && $positiveBucketTotal > 0)
                ? ((float) $bucket['total'] / $positiveBucketTotal) * 100
                : 0.0;
            ?>
            <div class="col-6 col-lg-3">
                <div class="asa-bucket <?= $meta['cls'] ?>">
                    <div class="asa-bucket__label">
                        <span class="asa-bucket__dot"></span><?= htmlspecialchars($bucket['label']) ?>
                    </div>
                    <div class="asa-bucket__value">
                        <?= $isOver ? '−Rp ' . $rp(abs($bucket['total'])) : 'Rp ' . $rp($bucket['total']) ?>
                    </div>
                    <div class="asa-bucket__meta">
                        <?= number_format((int) $bucket['count'], 0, ',', '.') ?> rekening
                        <?php if (!$isOver): ?>
                            · <?= number_format($pct, 1, ',', '.') ?>% dari total sisa
                        <?php endif; ?>
                    </div>
                    <div class="asa-bucket__bar"><span style="width: <?= number_format(min(100, max(0, $pct)), 1, '.', '') ?>%"></span></div>
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
                <h5>Tidak ada rekening yang cocok dengan filter.</h5>
                <p>
                    <?php if ($q !== ''): ?>
                        Pencarian &ldquo;<?= htmlspecialchars($q) ?>&rdquo; tidak menemukan hasil.
                    <?php endif; ?>
                    Coba turunkan ambang sisa minimal, ubah seksi, atau reset filter.
                </p>
                <?php if ($hiddenZeroCount > 0): ?>
                    <p><?= number_format($hiddenZeroCount, 0, ',', '.') ?> rekening tanpa sisa disembunyikan.</p>
                <?php endif; ?>
                <a href="<?= $resetUrl ?>" class="btn btn-primary asa-btn">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset filter
                </a>
            </div>
        <?php else: ?>
            <div class="asa-resultbar" id="asaResultBar">
                <?php if ($q !== ''): ?>
                    <span>Hasil pencarian &ldquo;<strong><?= htmlspecialchars($q) ?></strong>&rdquo;</span>
                    <span class="asa-resultbar__sep">·</span>
                <?php endif; ?>
                <span>Menampilkan <strong><?= number_format($rangeStart, 0, ',', '.') ?>–<?= number_format($rangeEnd, 0, ',', '.') ?></strong>
                    dari <strong><?= number_format($total, 0, ',', '.') ?></strong> rekening</span>
                <?php if ($seksiFilter > 0): ?>
                    <span class="asa-resultbar__sep">·</span>
                    <span>Seksi: <strong><?= htmlspecialchars($seksiMap[$seksiFilter] ?? '#' . $seksiFilter) ?></strong></span>
                <?php endif; ?>
                <?php if ($minJuta > 0): ?>
                    <span class="asa-resultbar__sep">·</span>
                    <span>Sisa ≥ <strong>Rp <?= $rp($minJuta * 1_000_000) ?></strong></span>
                <?php endif; ?>
                <?php if ($hiddenZeroCount > 0): ?>
                    <span class="asa-resultbar__sep">·</span>
                    <span><?= number_format($hiddenZeroCount, 0, ',', '.') ?> rekening tanpa sisa disembunyikan —
                        <a class="asa-resultbar__link" href="<?= $toggleZeroUrl ?>">tampilkan</a></span>
                <?php endif; ?>
            </div>
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
                                    <?php if (($row['tagar'] ?? '') !== ''): ?>
                                        <span class="asa-badge asa-badge--tag"><?= htmlspecialchars($row['tagar']) ?></span>
                                    <?php endif; ?>
                                    <span class="asa-meta"><?= htmlspecialchars($row['nama_program']) ?></span>
                                </td>
                                <td>
                                    <div class="asa-meta"><strong><?= htmlspecialchars($row['kode_sub_kegiatan']) ?></strong> · <?= htmlspecialchars($row['nama_sub_kegiatan']) ?></div>
                                    <span class="asa-badge asa-badge--neutral mt-1"><i class="bi bi-building"></i> <?= htmlspecialchars($row['nama_seksi']) ?></span>
                                </td>
                                <td class="asa-num"><?= $rp($row['pagu']) ?></td>
                                <td class="asa-num asa-num--muted"><?= $rp($row['realisasi']) ?></td>
                                <td class="asa-num <?= $sisa < 0 ? 'asa-num--over' : ($sisa == 0 ? 'asa-num--muted' : 'asa-num--sisa') ?>">
                                    <?php if ($sisa == 0): ?>
                                        <span class="asa-badge asa-badge--neutral">Tanpa sisa</span>
                                    <?php else: ?>
                                        <?= $rp($sisa) ?>
                                        <?php if ($sisa >= 500_000_000): ?>
                                            <span class="asa-badge asa-badge--success d-block mt-1">Sisa besar</span>
                                        <?php elseif ($sisa < 0): ?>
                                            <span class="asa-badge asa-badge--danger d-block mt-1">Over realisasi</span>
                                        <?php endif; ?>
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
    const numberFmt = new Intl.NumberFormat('id-ID');

    // Format penuh: ribuan pakai titik (tooltip, CSV).
    const rupiah = (v) => 'Rp ' + numberFmt.format(Math.round(Number(v) || 0));

    // Format ringkas sumbu: "Rp 0", "Rp 0,5 jt", "Rp 1 jt", "Rp 1,5 jt", "Rp 2 M".
    // Selalu satu gaya agar label sumbu tidak campur ("2 jt" vs "Rp 500.000").
    const shortRupiah = (v) => {
        const n = Number(v) || 0;
        const abs = Math.abs(n);
        if (abs >= 1e9) {
            return 'Rp ' + (n / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2, minimumFractionDigits: 0 }) + ' M';
        }
        if (abs >= 1e6) {
            return 'Rp ' + (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1, minimumFractionDigits: 0 }) + ' jt';
        }
        if (abs >= 1e3) {
            return 'Rp ' + numberFmt.format(Math.round(n));
        }
        return 'Rp 0';
    };

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    // Grafik top 10 rekening (horizontal bar)
    const topEl = document.getElementById('asaTopChart');
    const narrowScreen = window.innerWidth < 600;
    if (topEl) {
        if (!ASA.topRekening.length) {
            topEl.parentElement.innerHTML = '<div class="asa-legend-empty">Belum ada rekening pada filter ini.</div>';
        } else {
            // Label satu baris dipotong; nama lengkap tetap muncul di tooltip hover.
            // (Label multi-baris membuat Chart.js salah menghitung lebar sumbu dan terpotong.)
            const shortName = (name) => {
                const limit = narrowScreen ? 18 : 32;
                return name.length > limit ? name.slice(0, limit - 1) + '…' : name;
            };
            const labels = ASA.topRekening.map((r) => shortName(r.nama));
            const codes = ASA.topRekening.map((r) => r.kode);
            const fullNames = ASA.topRekening.map((r) => r.nama);
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
                        barThickness: narrowScreen ? 14 : 18,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { left: 2, right: 4 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            callbacks: {
                                title: (items) => codes[items[0].dataIndex] + ' · ' + fullNames[items[0].dataIndex],
                                label: (ctx) => ' Sisa: ' + rupiah(ctx.raw),
                                afterLabel: (ctx) => [
                                    ' Pagu: ' + rupiah(ASA.topRekening[ctx.dataIndex].pagu),
                                    ' Seksi: ' + ASA.topRekening[ctx.dataIndex].seksi,
                                ],
                            },
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                callback: (v) => shortRupiah(v),
                                maxTicksLimit: narrowScreen ? 4 : 6,
                                font: { size: narrowScreen ? 9 : 11 },
                                color: '#475569',
                            },
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: narrowScreen ? 9 : 11 }, color: '#334155', autoSkip: false },
                        },
                    },
                },
            });
        }
    }

    // Sebaran sisa per seksi (doughnut).
    // Keputusan: donut selalu memakai seluruh seksi bersisa; filter seksi hanya
    // memengaruhi tabel & chart batang. Legenda dirender di PHP (HTML) agar nominal
    // dan persentase tetap terbaca, termasuk untuk seksi dengan porsi kecil.
    const seksiEl = document.getElementById('asaSeksiChart');
    if (seksiEl) {
        if (!ASA.perSeksi.length) {
            seksiEl.parentElement.innerHTML = '<div class="asa-legend-empty">Belum ada sisa anggaran per seksi.</div>';
        } else {
            const palette = ASA.palette || ['#4338ca', '#059669', '#0284c7', '#d97706', '#db2777'];
            const donutTotal = ASA.perSeksi.reduce((sum, s) => sum + Number(s.sisa || 0), 0);
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
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            padding: 10,
                            callbacks: {
                                label: (ctx) => {
                                    const s = ASA.perSeksi[ctx.dataIndex];
                                    const pct = donutTotal > 0 ? (Number(s.sisa) / donutTotal) * 100 : 0;
                                    return ' ' + ctx.label + ': ' + rupiah(ctx.raw)
                                        + ' (' + pct.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%)';
                                },
                                afterLabel: (ctx) => ' ' + ASA.perSeksi[ctx.dataIndex].count + ' rekening',
                            },
                        },
                    },
                },
            });
        }
    }

    // ── Perilaku filter: satu pola (otomatis), chip cepat, dan indikator loading ──
    const form = document.getElementById('asaFilterForm');
    const minInput = document.getElementById('asaMinJuta');
    const searchInput = document.getElementById('asaSearch');
    const loadingBar = document.getElementById('asaLoadingBar');
    const chips = document.querySelectorAll('.asa-chip[data-juta]');
    const loadingTargets = document.querySelectorAll('.asa-card');
    const DEBOUNCE_MS = 400;
    let submitTimer = null;
    let submitting = false;

    const syncChipState = () => {
        if (!minInput) return;
        const current = minInput.value === '' ? 0 : Number(minInput.value);
        chips.forEach((chip) => {
            chip.classList.toggle('is-active', Number(chip.dataset.juta) === current);
        });
    };

    const setLoading = (on) => {
        if (loadingBar) {
            if (on) {
                loadingBar.hidden = false;
                void loadingBar.offsetWidth; // paksa reflow agar transisi lebar berjalan
                loadingBar.classList.add('is-loading');
            } else {
                loadingBar.classList.remove('is-loading');
                loadingBar.hidden = true;
            }
        }
        // Tabel & chart diberi overlay agar konten lama tidak dibiarkan tanpa tanda.
        loadingTargets.forEach((card) => card.classList.toggle('is-loading', on));
        if (form) form.setAttribute('aria-busy', on ? 'true' : 'false');
    };

    const submitNow = () => {
        if (!form || submitting) return;
        clearTimeout(submitTimer);
        // requestSubmit tetap memicu event submit (bisa dipakai walau tanpa tombol submit).
        form.requestSubmit();
    };

    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            if (!minInput || !form) return;
            minInput.value = chip.dataset.juta === '0' ? '' : chip.dataset.juta;
            syncChipState();
            submitNow();
        });
    });

    if (form) {
        // Input teks/angka: debounce 400 ms lalu otomatis terapkan.
        [minInput, searchInput].forEach((input) => {
            if (!input) return;
            input.addEventListener('input', () => {
                syncChipState();
                clearTimeout(submitTimer);
                submitTimer = setTimeout(submitNow, DEBOUNCE_MS);
            });
        });

        // Dropdown & checkbox: langsung terapkan saat berubah.
        form.querySelectorAll('select, input[type="checkbox"]').forEach((control) => {
            control.addEventListener('change', () => {
                clearTimeout(submitTimer);
                submitNow();
            });
        });

        form.addEventListener('submit', () => {
            submitting = true;
            setLoading(true);
        });
    }

    // Loading juga saat pindah halaman (pagination) atau mengunduh.
    document.querySelectorAll('.asa-pager a, .asa-resultbar__link, .asa-header__actions a, #asaReset')
        .forEach((link) => link.addEventListener('click', () => setLoading(true)));

    window.addEventListener('pageshow', () => {
        submitting = false;
        clearTimeout(submitTimer);
        setLoading(false);
    });

    // Unduh CSV (seluruh hasil filter, bukan hanya halaman aktif)
    const exportBtn = document.getElementById('asaExportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            const headers = ['Kode Rekening', 'Nama Rekening', 'Tagar', 'Sub Kegiatan', 'Seksi', 'Pagu', 'Realisasi', 'Sisa', 'Serapan (%)'];
            const lines = [headers.join(';')];
            ASA.rows.forEach((r) => {
                lines.push([
                    r.kode_rekening,
                    r.nama_rekening,
                    r.tagar || '',
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
