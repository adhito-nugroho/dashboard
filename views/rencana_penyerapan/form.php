<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$isEdit = ($action ?? 'store') === 'update' && !empty($form['id']);
$errors = $errors ?? [];
$formAction = $isEdit
    ? base_url('rencana-penyerapan/update/' . (int) $form['id'])
    : base_url('rencana-penyerapan/store');

$bulanFull = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulanShort = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$nilaiDisplay = ((float) $form['nilai_rencana']) > 0 ? number_format((float) $form['nilai_rencana'], 0, ',', '.') : '';
$mekanisme = $form['mekanisme'] ?? '';

$rekenings = $rekenings ?? [];
$selectedRekening = $selectedRekening ?? null;
$selectedRekeningId = (int) ($form['rekening_id'] ?? 0);
$canEditDetail = $isEdit || $selectedRekeningId > 0;
$currentTahun = (int) date('Y');
$formTahun = (int) ($form['tahun'] ?? $currentTahun);

$selectedRow = null;
foreach ($rekenings as $rekItem) {
    if ((int) $rekItem['id'] === $selectedRekeningId) {
        $selectedRow = $rekItem;
        break;
    }
}

$selPaguText = '-';
$selSisaText = '-';
if ($selectedRow) {
    $selPagu = (float) $selectedRow['total_pagu'];
    $selSisa = $selPagu - (float) $selectedRow['total_realisasi'] - (float) $selectedRow['total_rencana'] - (float) ($selectedRow['total_tu'] ?? 0);
    $selPaguText = 'Rp ' . number_format($selPagu, 0, ',', '.');
    $selSisaText = 'Rp ' . number_format($selSisa, 0, ',', '.');
}

$selKode = (string) ($selectedRekening['kode_rekening'] ?? '');
$selNama = (string) ($selectedRekening['nama_rekening'] ?? '');

$seksiOptions = [];
$subOptions = [];
$subToSeksi = [];
$subToSeksiLabel = [];
foreach ($rekenings as $rekItem) {
    $seksiId = (int) ($rekItem['seksi_id'] ?? 0);
    if ($seksiId > 0 && !isset($seksiOptions[$seksiId])) {
        $seksiOptions[$seksiId] = (string) ($rekItem['nama_seksi'] ?? '');
    }
    $subId = (int) ($rekItem['sub_kegiatan_id'] ?? 0);
    if ($subId > 0) {
        $subOptions[$subId] = $rekItem['kode_sub_kegiatan'] . ' · ' . $rekItem['nama_sub_kegiatan'];
        $subToSeksi[$subId] = $seksiId;
        $subToSeksiLabel[$subId] = (string) ($rekItem['nama_seksi'] ?? '');
    }
}
asort($seksiOptions);
asort($subOptions);

$bentukSuggestions = [
    'Pengadaan Barang',
    'Pengadaan Bibit',
    'Pengadaan Jasa',
    'Pengadaan Jasa Konsultansi',
    'Belanja Modal',
    'Perjalanan Dinas',
    'Honorarium / Narasumber',
    'Belanja ATK',
    'Sewa',
    'Pemeliharaan',
    'Lainnya',
];

$backUrl = base_url('rencana-penyerapan?tahun=' . urlencode((string) $formTahun));
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<style>
    .rp-page {
        --rp-indigo: #4338ca;
        --rp-indigo-dark: #312e81;
        --rp-ink: #0f172a;
        --rp-muted: #475569;
        --rp-line: #e2e8f0;
    }

    .rp-page h1 { font-size: 1.35rem; }
    .rp-page .rp-hint { font-size: .75rem; color: var(--rp-muted); }

    /* Dropdown panah tidak menimpa teks (style.css global memangkas padding) */
    .rp-page .form-select {
        padding-right: 2.25rem !important;
        background-position: right .75rem center !important;
        background-size: 12px 10px !important;
        text-overflow: ellipsis;
    }

    .rp-page .form-control::placeholder { color: #64748b; opacity: 1; }

    .rp-master-detail {
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
        gap: 1.25rem;
        align-items: start;
    }

    .rp-master-detail.is-edit {
        grid-template-columns: minmax(0, 1fr);
        max-width: 860px;
    }

    .rp-panel {
        background: #fff;
        border: 1px solid var(--rp-line);
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
        overflow: hidden;
        min-width: 0;
    }

    .rp-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 13px 16px;
        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        border-bottom: 1px solid var(--rp-line);
    }

    .rp-panel-title { margin: 0; font-size: .95rem; font-weight: 800; color: var(--rp-ink); }
    .rp-panel-sub { font-size: .74rem; color: var(--rp-muted); }
    .rp-year-badge {
        font-size: .7rem;
        font-weight: 700;
        color: var(--rp-indigo-dark);
        background: #e0e7ff;
        border-radius: 999px;
        padding: 2px 9px;
        white-space: nowrap;
    }

    .rp-list-panel { display: flex; flex-direction: column; }

    .rp-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: flex-end;
        padding: 11px 16px;
        border-bottom: 1px solid var(--rp-line);
    }

    .rp-toolbar .rp-search { flex: 1 1 200px; min-width: 0; }
    .rp-toolbar .rp-filter { flex: 0 1 132px; min-width: 0; }
    .rp-toolbar .rp-filter--sub { flex: 0 1 176px; }
    .rp-toolbar label {
        display: block;
        font-size: .65rem;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--rp-muted);
        margin-bottom: 3px;
    }

    .rp-notice {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 10px 16px 0;
        padding: 9px 11px;
        border: 1px solid #fde68a;
        background: #fffbeb;
        color: #78350f;
        border-radius: 10px;
        font-size: .78rem;
        line-height: 1.4;
    }

    .rp-notice .btn-close { margin-left: auto; font-size: .6rem; }

    /* Satu area scroll vertikal; tanpa scroll horizontal */
    .rp-table-scroll {
        max-height: 70vh;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .rp-table {
        width: 100%;
        margin: 0;
        table-layout: fixed;
        font-size: .79rem;
    }

    .rp-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8fafc;
        border-bottom: 1px solid var(--rp-line);
        padding: 7px 8px;
        font-size: .64rem;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--rp-muted);
        white-space: nowrap;
    }

    .rp-table td {
        padding: 7px 8px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .rp-table tbody tr[data-rekening-row] { cursor: pointer; }
    .rp-table tbody tr[data-rekening-row]:hover > td { background: #f8fafc; }
    .rp-table tbody tr.is-selected > td { background: #eef2ff; }
    .rp-table tbody tr.is-selected > td:first-child { box-shadow: inset 3px 0 0 var(--rp-indigo); }
    .rp-table tbody tr.is-done > td { opacity: .58; }

    .rp-col-action {
        position: sticky;
        right: 0;
        z-index: 1;
        background: #fff;
        box-shadow: -1px 0 0 var(--rp-line);
    }

    .rp-table thead .rp-col-action { z-index: 3; background: #f8fafc; }
    .rp-table tbody tr:hover > td.rp-col-action { background: #f8fafc; }
    .rp-table tbody tr.is-selected > td.rp-col-action { background: #eef2ff; }
    .rp-table tbody tr.is-done > td.rp-col-action { opacity: .85; }

    .rp-kode {
        display: inline-block;
        max-width: 100%;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.25;
        font-size: .62rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        color: #92400e;
        background: #fef3c7;
        border-radius: 6px;
        padding: 1px 6px;
    }

    .rp-rek-name {
        font-size: .8rem;
        font-weight: 700;
        color: var(--rp-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rp-rek-sub {
        font-size: .69rem;
        color: var(--rp-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 1px;
    }

    .rp-money {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        font-size: .74rem;
    }

    .rp-money--neg { color: #b91c1c; font-weight: 700; }

    .rp-status {
        display: inline-block;
        font-size: .63rem;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 999px;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rp-status--open { background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; }
    .rp-status--done { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }

    .rp-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 4px; }

    .rp-expand-btn {
        border: 0;
        background: transparent;
        color: var(--rp-muted);
        padding: 2px 5px;
        line-height: 1;
        border-radius: 6px;
    }

    .rp-expand-btn:hover { background: #eef2ff; color: var(--rp-indigo); }
    .rp-expand-btn .bi { transition: transform .15s ease; display: inline-block; }
    .rp-expand-btn[aria-expanded="true"] .bi { transform: rotate(180deg); }

    .rp-page .btn-xs {
        padding: .16rem .5rem;
        font-size: .72rem;
        border-radius: 6px;
        line-height: 1.4;
    }

    .rp-detail-row > td {
        background: #f8fafc !important;
        padding: 10px 14px !important;
        cursor: default;
    }

    .rp-detail-grid { display: flex; flex-wrap: wrap; gap: 8px 26px; }
    .rp-detail-grid span {
        display: block;
        font-size: .64rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--rp-muted);
    }
    .rp-detail-grid strong { font-size: .78rem; color: var(--rp-ink); font-variant-numeric: tabular-nums; }

    .rp-list-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        padding: 9px 16px;
        border-top: 1px solid var(--rp-line);
    }

    .rp-page-info { font-size: .74rem; color: var(--rp-muted); }
    .rp-list-foot .pagination { margin: 0; }
    .rp-list-foot .page-link { padding: .2rem .5rem; font-size: .74rem; }

    .rp-empty-search { padding: 26px 12px; text-align: center; color: var(--rp-muted); font-size: .8rem; }
    .rp-empty-search .bi { font-size: 1.15rem; display: block; margin-bottom: 6px; color: #94a3b8; }

    /* Panel kanan: sticky, form + ringkasan */
    .rp-form-panel {
        position: sticky;
        top: 16px;
        max-height: calc(100vh - 32px);
        display: flex;
        flex-direction: column;
    }

    .rp-form-panel form { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }

    .rp-panel-body {
        overflow-y: auto;
        min-height: 0;
        padding: 14px 16px;
        flex: 1 1 auto;
    }

    .rp-empty-state {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        border-radius: 12px;
        font-size: .82rem;
        color: #334155;
        margin-bottom: 12px;
    }
    .rp-empty-state .bi { font-size: 1.1rem; color: #64748b; }

    .rp-selected-card {
        display: flex;
        gap: 12px;
        border: 1px solid #c7d2fe;
        background: #f5f7ff;
        border-radius: 12px;
        padding: 11px 12px;
        margin-bottom: 12px;
    }

    .rp-selected-main { min-width: 0; flex: 1 1 auto; }
    .rp-selected-label {
        font-size: .63rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--rp-indigo);
    }
    .rp-selected-kode { font-size: .76rem; font-weight: 800; color: var(--rp-indigo-dark); font-variant-numeric: tabular-nums; }
    .rp-selected-name { font-size: .82rem; font-weight: 600; color: var(--rp-ink); line-height: 1.35; }

    .rp-selected-side {
        flex: 0 0 36%;
        min-width: 126px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-size: .72rem;
        text-align: right;
    }
    .rp-selected-side span { color: var(--rp-muted); display: block; font-size: .66rem; }
    .rp-selected-side strong { color: var(--rp-ink); font-variant-numeric: tabular-nums; font-size: .75rem; }

    .rp-lock-wrap { position: relative; }

    .rp-lock-overlay {
        position: absolute;
        inset: 0;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 18px;
        text-align: center;
        font-size: .82rem;
        font-weight: 600;
        color: #334155;
        background: rgba(248, 250, 252, .86);
        border-radius: 12px;
        backdrop-filter: blur(1px);
    }

    fieldset.rp-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
    fieldset.rp-fieldset[disabled] { opacity: .6; }

    .rp-field-label {
        display: block;
        font-size: .77rem;
        font-weight: 700;
        color: var(--rp-ink);
        margin-bottom: 4px;
    }
    .rp-field-error { color: #b91c1c; font-size: .75rem; margin-top: 4px; }
    .rp-req { color: #b91c1c; }

    .rp-mek-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }

    .rp-mek-option {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 9px 10px;
        border: 1px solid var(--rp-line);
        border-radius: 10px;
        cursor: pointer;
        background: #fff;
        height: 100%;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .rp-mek-option:hover { border-color: #c7d2fe; background: #f8faff; }
    .rp-mek-option:has(input:checked) {
        border-color: var(--rp-indigo);
        background: #eef2ff;
        box-shadow: 0 0 0 3px rgba(67, 56, 202, .10);
    }
    .rp-mek-option input { margin-top: 3px; flex: 0 0 auto; }
    .rp-mek-option strong { display: block; font-size: .82rem; color: var(--rp-ink); }
    .rp-mek-option small { display: block; font-size: .8125rem; line-height: 1.35; color: var(--rp-muted); margin-top: 1px; }

    .rp-mek {
        display: inline-flex;
        align-items: center;
        font-size: .64rem;
        font-weight: 800;
        letter-spacing: .04em;
        padding: 1px 7px;
        border-radius: 999px;
        line-height: 1.5;
        white-space: nowrap;
        margin-bottom: 3px;
    }
    .rp-mek--GU { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
    .rp-mek--TU { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .rp-mek--LS { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

    .rp-fill-link { font-size: .74rem; text-decoration: none; }

    .rp-summary {
        margin-top: 14px;
        border-top: 1px dashed var(--rp-line);
        padding-top: 12px;
    }

    .rp-sum-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .rp-sum-title {
        margin: 0;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--rp-indigo);
    }

    .rp-info-btn {
        border: 0;
        background: transparent;
        color: var(--rp-muted);
        padding: 0 2px;
        line-height: 1;
        font-size: .8rem;
    }
    .rp-info-btn:hover { color: var(--rp-indigo); }

    .rp-sum-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 3px 0;
        font-size: .77rem;
    }
    .rp-sum-row span { color: var(--rp-muted); display: flex; align-items: center; gap: 3px; }
    .rp-sum-row strong { color: var(--rp-ink); font-variant-numeric: tabular-nums; text-align: right; }
    .rp-sum-row.is-total { border-top: 1px solid var(--rp-line); margin-top: 4px; padding-top: 7px; }

    .rp-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: .75rem; color: var(--rp-muted); margin: 8px 0 6px; }
    .rp-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; }
    .rp-dot--real { background: #059669; }
    .rp-dot--plan { background: var(--rp-indigo); }

    .rp-chart-plot {
        position: relative;
        height: 112px;
        background: #f8fafc;
        border: 1px solid #eef2f7;
        border-radius: 10px;
        overflow: hidden;
    }

    .rp-chart-grid {
        position: relative;
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        align-items: end;
        height: 100%;
        padding: 22px 4px 2px;
    }

    .rp-chart-cell {
        height: 100%;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        border-left: 1px dashed #e8edf4;
    }
    .rp-chart-cell:first-child { border-left: 0; }
    .rp-chart-cell.is-past { background: rgba(148, 163, 184, .10); }

    .rp-chart-bar {
        width: 58%;
        border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, #34d399, #059669);
        transition: height .25s ease;
    }

    .rp-plan-band {
        position: absolute;
        top: 9px;
        height: 9px;
        border-radius: 5px;
        background: linear-gradient(90deg, #4338ca, #6366f1);
        box-shadow: 0 1px 2px rgba(49, 46, 129, .35);
        z-index: 2;
        pointer-events: auto;
    }

    .rp-chart-labels { display: grid; grid-template-columns: repeat(12, 1fr); margin-top: 4px; }
    .rp-chart-labels span { text-align: center; font-size: .75rem; color: var(--rp-muted); font-weight: 600; }

    .rp-chart-note { font-size: .73rem; color: var(--rp-muted); margin-top: 6px; line-height: 1.4; }

    .rp-summary.is-loading .rp-sum-row strong,
    .rp-selected-side.is-loading strong {
        color: transparent;
        border-radius: 4px;
        background-image: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
        background-size: 200% 100%;
        animation: rp-shimmer 1.2s ease-in-out infinite;
        user-select: none;
    }

    @keyframes rp-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .rp-info-error {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        padding: 9px 11px;
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        border-radius: 10px;
        font-size: .78rem;
    }

    .rp-form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px 16px;
        border-top: 1px solid var(--rp-line);
        background: #fff;
        flex-shrink: 0;
    }

    .rp-save-hint { flex: 1 1 150px; min-width: 0; font-size: .72rem; color: var(--rp-muted); line-height: 1.35; }
    .rp-save-hint.is-invalid { color: #b91c1c; }
    .rp-footer-actions { display: flex; gap: 8px; flex-shrink: 0; }

    @media (min-width: 1280px) and (max-width: 1439.98px) {
        .rp-master-detail { grid-template-columns: minmax(0, 1.8fr) minmax(340px, 1fr); }
    }

    @media (max-width: 1279.98px) {
        .rp-master-detail { grid-template-columns: minmax(0, 1fr); }
        .rp-form-panel { position: static; max-height: none; }
        .rp-form-panel .rp-panel-body { overflow: visible; }

        /* Baris rekening jadi kartu ringkas: tanpa scroll horizontal */
        .rp-table, .rp-table tbody { display: block; }
        .rp-table colgroup, .rp-table thead { display: none; }
        .rp-table tbody tr[data-rekening-row] {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .rp-table tbody tr[data-rekening-row]:hover { background: #f8fafc; }
        .rp-table tbody tr[data-rekening-row].is-selected {
            background: #eef2ff;
            box-shadow: inset 3px 0 0 var(--rp-indigo);
        }
        .rp-table tbody tr[data-rekening-row] > td { padding: 0; border: 0; background: transparent; min-width: 0; }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(1) { order: 2; }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(2) { order: 1; flex: 1 1 100%; }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(3) { order: 3; }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(4) { order: 4; }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(5) { order: 5; }
        .rp-table tbody tr[data-rekening-row] > td.rp-col-action {
            order: 6;
            position: static;
            margin-left: auto;
            box-shadow: none;
        }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(3)::before,
        .rp-table tbody tr[data-rekening-row] > td:nth-child(4)::before {
            color: var(--rp-muted);
            font-weight: 700;
            font-size: .66rem;
            letter-spacing: .03em;
            text-transform: uppercase;
            margin-right: 3px;
        }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(3)::before { content: 'Pagu'; }
        .rp-table tbody tr[data-rekening-row] > td:nth-child(4)::before { content: 'Sisa'; }
        .rp-table tbody tr[data-rekening-row] .rp-money { text-align: left; }
        .rp-table .rp-detail-row { display: block; }
        .rp-table .rp-detail-row[hidden] { display: none; }
        .rp-table tbody > tr:not([data-rekening-row]):not(.rp-detail-row) { display: block; }
        .rp-table tbody > tr:not([data-rekening-row]):not(.rp-detail-row) > td { display: block; border: 0; }
        .rp-table .rp-detail-row > td {
            display: block;
            padding: 10px 12px;
            background: #fbfcfe;
            border-bottom: 1px solid #f1f5f9;
        }
    }

    @media (max-width: 767.98px) {
        .rp-toolbar .rp-search { flex-basis: 100%; }
        .rp-toolbar .rp-filter, .rp-toolbar .rp-filter--sub { flex: 1 1 40%; }
        .rp-mek-options { grid-template-columns: 1fr; }
        .rp-selected-card { flex-direction: column; }
        .rp-selected-side { flex: 1 1 auto; text-align: left; }
        .rp-form-footer { flex-direction: column; align-items: stretch; }
        .rp-footer-actions { justify-content: stretch; }
        .rp-footer-actions .btn, .rp-footer-actions a { flex: 1 1 0; text-align: center; }
    }
</style>

<div class="container-fluid py-4 rp-page">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div class="min-w-0">
            <h1 class="fw-bold mb-1" style="color:#0f172a;">
                <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-1"></i><?= htmlspecialchars($pageTitle ?? 'Rencana Penyerapan') ?>
            </h1>
            <p class="text-muted small mb-0"><?= htmlspecialchars($subtitle ?? '') ?></p>
        </div>
        <?php if (!$isEdit): ?>
            <form method="GET" action="<?= base_url('rencana-penyerapan/create') ?>" class="d-flex align-items-end gap-2 m-0">
                <div>
                    <label for="picker-tahun" class="form-label fw-semibold mb-1" style="font-size:.7rem;">Tahun Anggaran</label>
                    <select name="tahun" id="picker-tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php for ($y = $currentTahun + 1; $y >= $currentTahun - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $formTahun === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-sm btn-primary">Tampilkan</button></noscript>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'warning' ? 'warning' : ($flashType === 'success' ? 'success' : 'info')) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['umum'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errors['umum']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <div class="rp-master-detail <?= $isEdit ? 'is-edit' : '' ?>">
        <?php if (!$isEdit): ?>
            <section class="rp-panel rp-list-panel" id="rpListPanel" aria-label="Daftar rekening">
                <div class="rp-panel-head">
                    <div class="min-w-0">
                        <h2 class="rp-panel-title"><i class="bi bi-list-ul text-primary me-1"></i>Pilih Rekening</h2>
                        <span class="rp-panel-sub">Klik baris untuk melihat rincian, lalu pilih untuk mengisi rencana.</span>
                    </div>
                    <span class="rp-year-badge">Tahun <?= $formTahun ?></span>
                </div>

                <div class="rp-toolbar">
                    <div class="rp-search">
                        <label for="rpRekeningSearch">Cari</label>
                        <input type="search" id="rpRekeningSearch" class="form-control form-control-sm"
                               placeholder="Kode, nama rekening, sub kegiatan, seksi..." autocomplete="off">
                    </div>
                    <div class="rp-filter">
                        <label for="rpFilterStatus">Status</label>
                        <select id="rpFilterStatus" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <option value="open" selected>Belum selesai</option>
                            <option value="done">Selesai</option>
                        </select>
                    </div>
                    <div class="rp-filter">
                        <label for="rpFilterSeksi">Seksi</label>
                        <select id="rpFilterSeksi" class="form-select form-select-sm">
                            <option value="">Semua seksi</option>
                            <?php foreach ($seksiOptions as $seksiId => $seksiNama): ?>
                                <option value="<?= (int) $seksiId ?>"><?= htmlspecialchars($seksiNama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rp-filter rp-filter--sub">
                        <label for="rpFilterSub">Sub Kegiatan</label>
                        <select id="rpFilterSub" class="form-select form-select-sm">
                            <option value="">Semua sub kegiatan</option>
                            <?php foreach ($subOptions as $subId => $subLabel): ?>
                                <option value="<?= (int) $subId ?>" data-seksi="<?= (int) ($subToSeksi[$subId] ?? 0) ?>" title="<?= htmlspecialchars($subLabel) ?>"><?= htmlspecialchars($subLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="rp-notice" id="rpSelesaiNotice" role="status" aria-live="polite" hidden>
                    <i class="bi bi-info-circle"></i>
                    <span id="rpSelesaiNoticeText"></span>
                    <button type="button" class="btn-close" id="rpSelesaiNoticeClose" aria-label="Tutup pemberitahuan"></button>
                </div>

                <div class="rp-table-scroll" id="rpTableScroll">
                    <table class="rp-table" aria-label="Rekening yang dapat direncanakan">
                        <colgroup>
                            <col style="width:104px;">
                            <col>
                            <col style="width:88px;">
                            <col style="width:112px;">
                            <col style="width:100px;">
                            <col style="width:80px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Kode</th>
                                <th scope="col">Rekening</th>
                                <th scope="col" class="text-end">Pagu</th>
                                <th scope="col" class="text-end">Sisa Kapasitas</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="rp-col-action text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="rpTableBody">
                            <?php if (empty($rekenings)): ?>
                                <tr>
                                    <td colspan="6" class="rp-empty-search">
                                        <i class="bi bi-inbox"></i>
                                        Tidak ada rekening dengan pagu pada tahun <?= $formTahun ?>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rekenings as $rek): ?>
                                    <?php
                                        $paguRek = (float) $rek['total_pagu'];
                                        $realRek = (float) $rek['total_realisasi'];
                                        $rencanaRek = (float) $rek['total_rencana'];
                                        $tuRek = (float) ($rek['total_tu'] ?? 0);
                                        $sisaRek = $paguRek - $realRek - $rencanaRek - $tuRek;
                                        $selesaiManual = !empty($rek['selesai_id']);
                                        $selesaiOtomatis = !$selesaiManual && $paguRek > 0 && $sisaRek <= 0.005;
                                        $isSelesai = $selesaiManual || $selesaiOtomatis;
                                        $isSelected = $selectedRekeningId === (int) $rek['id'];
                                        $statusLabel = $selesaiManual ? 'Selesai' : ($selesaiOtomatis ? 'Selesai · sisa 0' : '');
                                        $searchText = mb_strtolower(implode(' ', array_filter([
                                            $rek['kode_rekening'], $rek['nama_rekening'],
                                            $rek['kode_program'], $rek['nama_program'],
                                            $rek['kode_kegiatan'], $rek['nama_kegiatan'],
                                            $rek['kode_sub_kegiatan'], $rek['nama_sub_kegiatan'],
                                            $rek['nama_seksi'] ?? '',
                                            $isSelesai ? 'selesai' : 'belum selesai',
                                        ])));
                                        $subLine = $rek['kode_sub_kegiatan'] . ' · ' . $rek['nama_sub_kegiatan'];
                                        if (!empty($rek['nama_seksi'])) {
                                            $subLine .= ' · ' . $rek['nama_seksi'];
                                        }
                                    ?>
                                    <tr data-rekening-row
                                        data-rekening-id="<?= (int) $rek['id'] ?>"
                                        data-kode="<?= htmlspecialchars($rek['kode_rekening']) ?>"
                                        data-nama="<?= htmlspecialchars($rek['nama_rekening']) ?>"
                                        data-search="<?= htmlspecialchars($searchText) ?>"
                                        data-status="<?= $isSelesai ? 'done' : 'open' ?>"
                                        data-status-label="<?= htmlspecialchars($statusLabel) ?>"
                                        data-seksi-id="<?= (int) ($rek['seksi_id'] ?? 0) ?>"
                                        data-sub-id="<?= (int) ($rek['sub_kegiatan_id'] ?? 0) ?>"
                                        data-pagu="<?= $paguRek ?>"
                                        data-sisa="<?= $sisaRek ?>"
                                        class="<?= $isSelected ? 'is-selected' : '' ?><?= $isSelesai ? ' is-done' : '' ?>">
                                        <td>
                                            <span class="rp-kode" title="<?= htmlspecialchars($rek['kode_rekening']) ?>"><?= htmlspecialchars($rek['kode_rekening']) ?></span>
                                        </td>
                                        <td>
                                            <div class="rp-rek-name" title="<?= htmlspecialchars($rek['nama_rekening']) ?>"><?= htmlspecialchars($rek['nama_rekening']) ?></div>
                                            <div class="rp-rek-sub" title="<?= htmlspecialchars($subLine) ?>"><?= htmlspecialchars($subLine) ?></div>
                                        </td>
                                        <td class="rp-money" title="Pagu: Rp <?= number_format($paguRek, 0, ',', '.') ?>"><?= number_format($paguRek, 0, ',', '.') ?></td>
                                        <td class="rp-money <?= $sisaRek < 0 ? 'rp-money--neg' : '' ?>" title="Sisa kapasitas: Rp <?= number_format($sisaRek, 0, ',', '.') ?>"><?= number_format($sisaRek, 0, ',', '.') ?></td>
                                        <td>
                                            <?php if ($isSelesai): ?>
                                                <span class="rp-status rp-status--done" title="<?= htmlspecialchars($selesaiManual ? 'Ditandai selesai manual' : 'Sisa kapasitas sudah 0') ?>">Selesai</span>
                                            <?php else: ?>
                                                <span class="rp-status rp-status--open">Belum selesai</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="rp-col-action">
                                            <div class="rp-row-actions">
                                                <button type="button" class="rp-expand-btn" data-expand-row
                                                        aria-expanded="false" aria-controls="rpDetail<?= (int) $rek['id'] ?>"
                                                        title="Lihat realisasi, rencana, dan rencana TU">
                                                    <i class="bi bi-chevron-down"></i>
                                                </button>
                                                <?php if (!$isSelesai): ?>
                                                    <button type="button" class="btn btn-xs <?= $isSelected ? 'btn-primary' : 'btn-outline-primary' ?>" data-pick-rekening>
                                                        <?= $isSelected ? 'Dipilih' : 'Pilih' ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="rp-detail-row" id="rpDetail<?= (int) $rek['id'] ?>" data-detail-for="<?= (int) $rek['id'] ?>" hidden>
                                        <td colspan="6">
                                            <div class="rp-detail-grid">
                                                <div><span>Realisasi</span><strong>Rp <?= number_format($realRek, 0, ',', '.') ?></strong></div>
                                                <div><span>Rencana tersimpan</span><strong>Rp <?= number_format($rencanaRek, 0, ',', '.') ?></strong></div>
                                                <div><span>Rencana TU otomatis</span><strong>Rp <?= number_format($tuRek, 0, ',', '.') ?></strong></div>
                                            </div>
                                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                                <small class="text-muted" style="font-size:.72rem;">Pagu − realisasi − rencana − rencana TU = sisa kapasitas.</small>
                                                <?php if (!$isSelesai): ?>
                                                    <form method="POST" action="<?= base_url('rencana-penyerapan/selesai') ?>" class="m-0 ms-auto"
                                                          onsubmit="return confirm('Tandai rekening ini selesai? Tidak perlu input rencana lagi.');">
                                                        <input type="hidden" name="rekening_id" value="<?= (int) $rek['id'] ?>">
                                                        <input type="hidden" name="tahun" value="<?= $formTahun ?>">
                                                        <input type="hidden" name="redirect" value="create">
                                                        <button type="submit" class="btn btn-xs btn-outline-success">Tandai selesai</button>
                                                    </form>
                                                <?php elseif ($selesaiManual): ?>
                                                    <form method="POST" action="<?= base_url('rencana-penyerapan/selesai/batal') ?>" class="m-0 ms-auto">
                                                        <input type="hidden" name="rekening_id" value="<?= (int) $rek['id'] ?>">
                                                        <input type="hidden" name="tahun" value="<?= $formTahun ?>">
                                                        <input type="hidden" name="redirect" value="create">
                                                        <button type="submit" class="btn btn-xs btn-outline-secondary">Batal selesai</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="rp-list-foot">
                    <span class="rp-page-info" id="rpPageInfo" aria-live="polite">&nbsp;</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="rp-page-info" id="rpRekeningCount"><?= count($rekenings) ?> rekening</span>
                        <nav aria-label="Navigasi halaman rekening">
                            <ul class="pagination pagination-sm" id="rpPagination"></ul>
                        </nav>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="rp-panel rp-form-panel" id="rpDetailPanel" aria-label="Rencana kegiatan">
            <form method="POST" action="<?= $formAction ?>" id="rpForm" novalidate>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                <?php endif; ?>
                <input type="hidden" name="rekening_id" id="rekening_id" value="<?= $selectedRekeningId ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" id="tahun" name="tahun" value="<?= $formTahun ?>">
                <?php else: ?>
                    <input type="hidden" id="tahun" name="tahun" value="<?= $formTahun ?>">
                <?php endif; ?>

                <div class="rp-panel-head">
                    <div class="min-w-0">
                        <h2 class="rp-panel-title"><i class="bi bi-clipboard-check text-primary me-1"></i>Rencana Kegiatan</h2>
                        <span class="rp-panel-sub">Detail rencana &amp; ringkasan rekening</span>
                    </div>
                    <span class="rp-year-badge" id="rpFormYear">Tahun <?= $formTahun ?></span>
                </div>

                <div class="rp-panel-body" id="rpFormBody">
                    <?php if (isset($errors['rekening_id'])): ?>
                        <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.8rem;"><?= htmlspecialchars($errors['rekening_id']) ?></div>
                    <?php endif; ?>

                    <div class="rp-empty-state" id="rpEmptyState" <?= $canEditDetail ? 'hidden' : '' ?>>
                        <i class="bi bi-hand-index-thumb"></i>
                        <span>Pilih rekening dari daftar untuk mulai mengisi rencana.</span>
                    </div>

                    <div class="rp-selected-card" id="rpSelectedCard" <?= $canEditDetail ? '' : 'hidden' ?>>
                        <div class="rp-selected-main">
                            <div class="rp-selected-label">Rekening terpilih</div>
                            <div class="rp-selected-kode" id="rpSelKode"><?= htmlspecialchars($selKode) ?></div>
                            <div class="rp-selected-name" id="rpSelName"><?= htmlspecialchars($selNama) ?></div>
                        </div>
                        <div class="rp-selected-side" id="rpSelectedSide">
                            <div><span>Pagu</span><strong id="rpSelPagu"><?= htmlspecialchars($selPaguText) ?></strong></div>
                            <div><span>Sisa kapasitas</span><strong id="rpSelSisa"><?= htmlspecialchars($selSisaText) ?></strong></div>
                            <?php if (!$isEdit): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="rpChangeRekening">
                                    <i class="bi bi-arrow-left-right me-1"></i>Ganti rekening
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="mb-3">
                            <label for="tahun_display" class="rp-field-label">Tahun Anggaran <span class="rp-req">*</span></label>
                            <input type="number" class="form-control form-control-sm <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>"
                                   id="tahun_display" value="<?= $formTahun ?>" min="2000" max="2100"
                                   onchange="document.getElementById('tahun').value = this.value;">
                            <?php if (isset($errors['tahun'])): ?>
                                <div class="rp-field-error"><?= htmlspecialchars($errors['tahun']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rp-lock-wrap">
                        <fieldset class="rp-fieldset" id="rpDetailFieldset" <?= $canEditDetail ? '' : 'disabled' ?>>
                            <div class="mb-3">
                                <label for="bentuk_kegiatan" class="rp-field-label">Bentuk Kegiatan <span class="rp-req">*</span></label>
                                <input type="text" class="form-control form-control-sm <?= isset($errors['bentuk_kegiatan']) ? 'is-invalid' : '' ?>"
                                       id="bentuk_kegiatan" name="bentuk_kegiatan" list="bentukKegiatanList"
                                       value="<?= htmlspecialchars((string) $form['bentuk_kegiatan']) ?>"
                                       placeholder="Contoh: Pengadaan Bibit" maxlength="150" autocomplete="off">
                                <datalist id="bentukKegiatanList">
                                    <?php foreach ($bentukSuggestions as $suggestion): ?>
                                        <option value="<?= htmlspecialchars($suggestion) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="rp-hint mt-1">Contoh: Pengadaan Barang, Pengadaan Bibit, Perjalanan Dinas, Honorarium.</div>
                                <?php if (isset($errors['bentuk_kegiatan'])): ?>
                                    <div class="rp-field-error"><?= htmlspecialchars($errors['bentuk_kegiatan']) ?></div>
                                <?php endif; ?>
                                <div class="rp-field-error" id="rpBentukError" hidden></div>
                            </div>

                            <div class="mb-3">
                                <label for="keterangan" class="rp-field-label">Keterangan</label>
                                <input type="text" class="form-control form-control-sm <?= isset($errors['keterangan']) ? 'is-invalid' : '' ?>"
                                       id="keterangan" name="keterangan" maxlength="255"
                                       value="<?= htmlspecialchars((string) $form['keterangan']) ?>"
                                       placeholder="Opsional, mis. nama rekanan atau catatan">
                                <?php if (isset($errors['keterangan'])): ?>
                                    <div class="rp-field-error"><?= htmlspecialchars($errors['keterangan']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <span class="rp-field-label d-block">Mekanisme Pembayaran <span class="rp-req">*</span></span>
                                <div class="rp-mek-options" role="radiogroup" aria-label="Mekanisme pembayaran">
                                    <label class="rp-mek-option">
                                        <input type="radio" name="mekanisme" value="GU" <?= $mekanisme === 'GU' ? 'checked' : '' ?>>
                                        <span>
                                            <span class="rp-mek rp-mek--GU">GU</span>
                                            <strong>Ganti Uang</strong>
                                            <small>Belanja UP yang di-SPJ-kan, lalu kas kembali utuh.</small>
                                        </span>
                                    </label>
                                    <label class="rp-mek-option">
                                        <input type="radio" name="mekanisme" value="TU" <?= $mekanisme === 'TU' ? 'checked' : '' ?>>
                                        <span>
                                            <span class="rp-mek rp-mek--TU">TU</span>
                                            <strong>Tambah Uang</strong>
                                            <small>Pengajuan dana TU, dibayarkan, lalu sisa disetor.</small>
                                        </span>
                                    </label>
                                    <label class="rp-mek-option">
                                        <input type="radio" name="mekanisme" value="LS" <?= $mekanisme === 'LS' ? 'checked' : '' ?>>
                                        <span>
                                            <span class="rp-mek rp-mek--LS">LS</span>
                                            <strong>Langsung</strong>
                                            <small>Dibayar langsung Kas Daerah ke rekanan.</small>
                                        </span>
                                    </label>
                                </div>
                                <?php if (isset($errors['mekanisme'])): ?>
                                    <div class="rp-field-error"><?= htmlspecialchars($errors['mekanisme']) ?></div>
                                <?php endif; ?>
                                <div class="rp-field-error" id="rpMekError" hidden></div>
                            </div>

                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <label for="bulan_mulai" class="rp-field-label">Bulan Mulai <span class="rp-req">*</span></label>
                                    <select class="form-select form-select-sm <?= isset($errors['bulan_mulai']) ? 'is-invalid' : '' ?>" id="bulan_mulai" name="bulan_mulai">
                                        <?php for ($b = 1; $b <= 12; $b++): ?>
                                            <option value="<?= $b ?>" <?= (int) $form['bulan_mulai'] === $b ? 'selected' : '' ?>><?= $bulanFull[$b] ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <?php if (isset($errors['bulan_mulai'])): ?>
                                        <div class="rp-field-error"><?= htmlspecialchars($errors['bulan_mulai']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-4">
                                    <label for="bulan_selesai" class="rp-field-label">Bulan Selesai <span class="rp-req">*</span></label>
                                    <select class="form-select form-select-sm <?= isset($errors['bulan_selesai']) ? 'is-invalid' : '' ?>" id="bulan_selesai" name="bulan_selesai">
                                        <?php for ($b = 1; $b <= 12; $b++): ?>
                                            <option value="<?= $b ?>" <?= (int) $form['bulan_selesai'] === $b ? 'selected' : '' ?>><?= $bulanFull[$b] ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <?php if (isset($errors['bulan_selesai'])): ?>
                                        <div class="rp-field-error"><?= htmlspecialchars($errors['bulan_selesai']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-4">
                                    <label for="nilai_rencana" class="rp-field-label">Nilai Rencana <span class="rp-req">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" inputmode="numeric" class="form-control text-end <?= isset($errors['nilai_rencana']) ? 'is-invalid' : '' ?>"
                                               id="nilai_rencana" name="nilai_rencana" value="<?= $nilaiDisplay ?>" placeholder="0" autocomplete="off">
                                    </div>
                                    <?php if (isset($errors['nilai_rencana'])): ?>
                                        <div class="rp-field-error"><?= htmlspecialchars($errors['nilai_rencana']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-1">
                                <small class="rp-hint" id="rpNilaiHint">Pilih rekening untuk melihat sisa kapasitas.</small>
                                <button type="button" class="btn btn-link rp-fill-link p-0" id="rpFillSisa" hidden>Isi sisa kapasitas</button>
                            </div>
                            <div class="rp-field-error" id="rpBulanError" hidden>Bulan selesai tidak boleh sebelum bulan mulai.</div>
                            <div class="rp-field-error" id="rpNilaiError" hidden></div>
                        </fieldset>
                        <div class="rp-lock-overlay" id="rpLockOverlay" <?= $canEditDetail ? 'hidden' : '' ?>>
                            <i class="bi bi-lock"></i>
                            <span>Pilih rekening dulu untuk mengisi detail rencana.</span>
                        </div>
                    </div>

                    <div class="rp-summary" id="rpSummaryCard" <?= $canEditDetail ? '' : 'hidden' ?>>
                        <div class="rp-sum-head">
                            <h3 class="rp-sum-title">Ringkasan Rekening</h3>
                            <button type="button" class="rp-info-btn" id="rpSisaInfo"
                                    data-bs-toggle="popover" data-bs-trigger="click" data-bs-placement="top"
                                    data-bs-content="Sisa kapasitas = pagu − realisasi terverifikasi − rencana tersimpan − rencana TU otomatis. Pengajuan TU berjalan sudah dihitung, tidak perlu input ulang."
                                    aria-label="Penjelasan sisa kapasitas">
                                <i class="bi bi-info-circle"></i>
                            </button>
                        </div>
                        <div class="rp-sum-row"><span>Pagu</span><strong id="rpSumPagu">-</strong></div>
                        <div class="rp-sum-row"><span>Realisasi terverifikasi</span><strong id="rpSumRealisasi">Rp 0</strong></div>
                        <div class="rp-sum-row"><span>Rencana tersimpan</span><strong id="rpSumRencana">Rp 0</strong></div>
                        <div class="rp-sum-row"><span>Rencana TU otomatis</span><strong id="rpSumTu">Rp 0</strong></div>
                        <div class="rp-sum-row is-total"><span>Sisa kapasitas</span><strong id="rpSumSisa">-</strong></div>
                        <div class="rp-sum-row"><span>Total RAK</span><strong id="rpSumRak">Rp 0</strong></div>

                        <div class="mt-3">
                            <div class="rp-legend">
                                <span><span class="rp-dot rp-dot--real"></span>Realisasi terverifikasi</span>
                                <span><span class="rp-dot rp-dot--plan"></span>Rencana (bulan mulai–selesai)</span>
                            </div>
                            <div class="rp-chart-plot">
                                <div class="rp-plan-band" id="rpPlanBand" hidden></div>
                                <div class="rp-chart-grid" id="rpChartGrid">
                                    <?php for ($b = 1; $b <= 12; $b++): ?>
                                        <div class="rp-chart-cell"><div class="rp-chart-bar" data-bulan="<?= $b ?>"></div></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="rp-chart-labels">
                                <?php for ($b = 1; $b <= 12; $b++): ?><span><?= $bulanShort[$b] ?></span><?php endfor; ?>
                            </div>
                            <div class="rp-chart-note" id="rpChartNote">Pilih rekening untuk melihat realisasi bulanan dan rentang rencana.</div>
                        </div>

                        <div class="rp-info-error" id="rpInfoError" hidden>
                            <i class="bi bi-exclamation-triangle"></i>
                            <span id="rpInfoErrorText">Gagal memuat ringkasan rekening.</span>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="rpRetryInfo">Coba lagi</button>
                        </div>
                    </div>
                </div>

                <div class="rp-form-footer">
                    <div class="rp-save-hint" id="rpSaveHint" aria-live="polite">Pilih rekening untuk mulai mengisi rencana.</div>
                    <div class="rp-footer-actions" id="rpSubmitWrap">
                        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-sm btn-primary" id="rpSubmit" disabled>
                            <i class="bi bi-check-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Rencana' ?>
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE = '<?= base_url() ?>';
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    const excludeId = <?= $isEdit ? (int) $form['id'] : 'null' ?>;
    const formTahun = <?= (int) $formTahun ?>;
    const PAGE_SIZE = 25;

    const initialSelection = {
        id: <?= $selectedRekeningId ?>,
        kode: <?= json_encode($selKode, $jsonFlags) ?>,
        nama: <?= json_encode($selNama, $jsonFlags) ?>
    };

    const form = document.getElementById('rpForm');
    const rekeningSel = document.getElementById('rekening_id');
    const tahunInput = document.getElementById('tahun');
    const tahunDisplay = document.getElementById('tahun_display');
    const formYearBadge = document.getElementById('rpFormYear');
    const bulanMulai = document.getElementById('bulan_mulai');
    const bulanSelesai = document.getElementById('bulan_selesai');
    const nilaiInput = document.getElementById('nilai_rencana');
    const bentukInput = document.getElementById('bentuk_kegiatan');

    const detailFieldset = document.getElementById('rpDetailFieldset');
    const lockOverlay = document.getElementById('rpLockOverlay');
    const emptyState = document.getElementById('rpEmptyState');
    const selectedCard = document.getElementById('rpSelectedCard');
    const selectedSide = document.getElementById('rpSelectedSide');
    const selKode = document.getElementById('rpSelKode');
    const selName = document.getElementById('rpSelName');
    const selPagu = document.getElementById('rpSelPagu');
    const selSisa = document.getElementById('rpSelSisa');
    const changeBtn = document.getElementById('rpChangeRekening');

    const summaryCard = document.getElementById('rpSummaryCard');
    const sumPagu = document.getElementById('rpSumPagu');
    const sumRealisasi = document.getElementById('rpSumRealisasi');
    const sumRencana = document.getElementById('rpSumRencana');
    const sumTu = document.getElementById('rpSumTu');
    const sumSisa = document.getElementById('rpSumSisa');
    const sumRak = document.getElementById('rpSumRak');
    const chartNote = document.getElementById('rpChartNote');
    const chartBars = Array.from(document.querySelectorAll('.rp-chart-bar'));
    const chartCells = Array.from(document.querySelectorAll('.rp-chart-cell'));
    const planBand = document.getElementById('rpPlanBand');
    const infoError = document.getElementById('rpInfoError');
    const infoErrorText = document.getElementById('rpInfoErrorText');
    const retryBtn = document.getElementById('rpRetryInfo');

    const nilaiHint = document.getElementById('rpNilaiHint');
    const nilaiError = document.getElementById('rpNilaiError');
    const bulanError = document.getElementById('rpBulanError');
    const bentukError = document.getElementById('rpBentukError');
    const mekError = document.getElementById('rpMekError');
    const fillSisaBtn = document.getElementById('rpFillSisa');

    const submitBtn = document.getElementById('rpSubmit');
    const submitWrap = document.getElementById('rpSubmitWrap');
    const saveHint = document.getElementById('rpSaveHint');

    const rows = Array.from(document.querySelectorAll('[data-rekening-row]'));
    const searchInput = document.getElementById('rpRekeningSearch');
    const statusFilter = document.getElementById('rpFilterStatus');
    const seksiFilter = document.getElementById('rpFilterSeksi');
    const subFilter = document.getElementById('rpFilterSub');
    const countEl = document.getElementById('rpRekeningCount');
    const pageInfo = document.getElementById('rpPageInfo');
    const pagination = document.getElementById('rpPagination');
    const notice = document.getElementById('rpSelesaiNotice');
    const noticeText = document.getElementById('rpSelesaiNoticeText');
    const noticeClose = document.getElementById('rpSelesaiNoticeClose');
    const listPanel = document.getElementById('rpListPanel');

    const bulanNama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    let infoData = null;
    let realisasiBulanan = {};
    let infoRequest = 0;
    let noticeTimer = null;
    let currentPage = 1;
    let visibleRows = rows.slice();
    let expanded = new Set();
    const touched = { bentuk: false, mekanisme: false, nilai: false, bulan: false };

    function rp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function rpOr(n) {
        return (n === null || n === undefined) ? '-' : rp(n);
    }

    function parseNominal(value) {
        const digits = String(value || '').replace(/[^\d]/g, '');
        return digits ? Number(digits) : 0;
    }

    function formatNilai() {
        const value = parseNominal(nilaiInput.value);
        nilaiInput.value = value > 0 ? value.toLocaleString('id-ID') : '';
    }

    function isNarrow() {
        return window.matchMedia('(max-width: 1279.98px)').matches;
    }

    function pastMonthLimit() {
        const tahun = Number(tahunInput.value || 0);
        if (!tahun) return 0;
        const now = new Date();
        const currentYear = now.getFullYear();
        if (tahun < currentYear) return 12;
        if (tahun === currentYear) return now.getMonth() + 1;
        return 0;
    }

    /* ---------- Notifikasi selesai ---------- */

    function showSelesaiNotice(row) {
        const label = row.dataset.statusLabel || 'Selesai';
        noticeText.textContent = 'Rekening ini sudah ditandai selesai (' + label + '). Batalkan status selesai di baris rekening bila perlu membuat rencana baru.';
        notice.hidden = false;
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(function () { notice.hidden = true; }, 5000);
    }

    if (noticeClose) {
        noticeClose.addEventListener('click', function () {
            clearTimeout(noticeTimer);
            notice.hidden = true;
        });
    }

    /* ---------- Filter, pencarian, paginasi ---------- */

    function matchesFilters(row) {
        const term = String(searchInput.value || '').trim().toLowerCase();
        if (term) {
            const terms = term.split(/\s+/).filter(Boolean);
            const haystack = row.dataset.search || '';
            if (!terms.every(function (t) { return haystack.indexOf(t) !== -1; })) return false;
        }
        if (statusFilter.value && row.dataset.status !== statusFilter.value) return false;
        if (seksiFilter.value && row.dataset.seksiId !== seksiFilter.value) return false;
        if (subFilter.value && row.dataset.subId !== subFilter.value) return false;
        return true;
    }

    function filtersActive() {
        return statusFilter.value !== '' || seksiFilter.value !== '' || subFilter.value !== '' || String(searchInput.value || '').trim() !== '';
    }

    function applyFilters(resetPage) {
        if (resetPage) currentPage = 1;
        visibleRows = rows.filter(matchesFilters);
        const totalPages = Math.max(1, Math.ceil(visibleRows.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        renderPage();
    }

    function renderPage() {
        if (!countEl || !pageInfo || !pagination) return;

        const emptyRow = document.getElementById('rpEmptyVisibleRow');
        if (emptyRow) emptyRow.hidden = true;

        if (!rows.length) {
            countEl.textContent = '0 rekening';
            pageInfo.innerHTML = '&nbsp;';
            pagination.innerHTML = '';
            return;
        }

        const start = (currentPage - 1) * PAGE_SIZE;
        const pageRows = visibleRows.slice(start, start + PAGE_SIZE);
        const pageIds = new Set(pageRows.map(function (r) { return r.dataset.rekeningId; }));

        rows.forEach(function (row) {
            row.hidden = !pageIds.has(row.dataset.rekeningId);
        });

        document.querySelectorAll('[data-detail-for]').forEach(function (detailRow) {
            const id = detailRow.dataset.detailFor;
            detailRow.hidden = !expanded.has(id) || !pageIds.has(id);
        });

        countEl.textContent = filtersActive()
            ? visibleRows.length + ' dari ' + rows.length + ' rekening'
            : rows.length + ' rekening';

        if (!visibleRows.length) {
            pageInfo.textContent = 'Tidak ada rekening yang cocok';
            pagination.innerHTML = '';
            showEmptySearchRow();
            return;
        }

        pageInfo.textContent = 'Menampilkan ' + (start + 1) + '–' + (start + pageRows.length) + ' dari ' + visibleRows.length;
        renderPagination();
    }

    function showEmptySearchRow() {
        let emptyRow = document.getElementById('rpEmptyVisibleRow');
        if (!emptyRow) {
            const tbody = document.getElementById('rpTableBody');
            if (!tbody) return;
            emptyRow = document.createElement('tr');
            emptyRow.id = 'rpEmptyVisibleRow';
            const td = document.createElement('td');
            td.colSpan = 6;
            td.className = 'rp-empty-search';
            td.innerHTML = '<i class="bi bi-search"></i>Tidak ada rekening yang cocok dengan filter atau pencarian.'
                + ' <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="rpResetFilters">Reset filter</button>';
            emptyRow.appendChild(td);
            tbody.appendChild(emptyRow);
            emptyRow.querySelector('#rpResetFilters').addEventListener('click', resetFilters);
        }
        emptyRow.hidden = false;
    }

    function resetFilters() {
        searchInput.value = '';
        statusFilter.value = '';
        seksiFilter.value = '';
        refreshSubOptions();
        subFilter.value = '';
        applyFilters(true);
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(visibleRows.length / PAGE_SIZE));
        let pages = [];
        if (totalPages <= 7) {
            for (let p = 1; p <= totalPages; p++) pages.push(p);
        } else {
            const set = new Set([1, totalPages, currentPage, currentPage - 1, currentPage + 1]);
            if (currentPage <= 3) { set.add(2); set.add(3); set.add(4); }
            if (currentPage >= totalPages - 2) { set.add(totalPages - 1); set.add(totalPages - 2); set.add(totalPages - 3); }
            pages = Array.from(set).filter(function (p) { return p >= 1 && p <= totalPages; }).sort(function (a, b) { return a - b; });
        }

        let html = '';
        html += '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '">'
            + '<button type="button" class="page-link" data-page="' + (currentPage - 1) + '" aria-label="Sebelumnya">&laquo;</button></li>';

        let previous = 0;
        pages.forEach(function (p) {
            if (previous && p - previous > 1) {
                html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            html += '<li class="page-item ' + (p === currentPage ? 'active' : '') + '">'
                + '<button type="button" class="page-link" data-page="' + p + '"'
                + (p === currentPage ? ' aria-current="page"' : '') + '>' + p + '</button></li>';
            previous = p;
        });

        html += '<li class="page-item ' + (currentPage === totalPages ? 'disabled' : '') + '">'
            + '<button type="button" class="page-link" data-page="' + (currentPage + 1) + '" aria-label="Berikutnya">&raquo;</button></li>';

        pagination.innerHTML = html;
    }

    if (pagination) {
        pagination.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-page]');
            if (!btn || btn.closest('.disabled')) return;
            const page = Number(btn.dataset.page);
            if (page < 1) return;
            currentPage = page;
            renderPage();
            const scroller = document.getElementById('rpTableScroll');
            if (scroller) scroller.scrollTop = 0;
        });
    }

    function refreshSubOptions() {
        if (!subFilter) return;
        const seksi = seksiFilter.value;
        let selectedStillValid = !subFilter.value;
        Array.from(subFilter.options).forEach(function (opt) {
            if (!opt.value) return;
            const ok = !seksi || opt.dataset.seksi === seksi;
            opt.hidden = !ok;
            opt.disabled = !ok;
            if (opt.selected && ok) selectedStillValid = true;
        });
        if (!selectedStillValid) subFilter.value = '';
    }

    if (searchInput) searchInput.addEventListener('input', function () { applyFilters(true); });
    if (statusFilter) statusFilter.addEventListener('change', function () { applyFilters(true); });
    if (seksiFilter) {
        seksiFilter.addEventListener('change', function () {
            refreshSubOptions();
            applyFilters(true);
        });
    }
    if (subFilter) subFilter.addEventListener('change', function () { applyFilters(true); });

    document.querySelectorAll('[data-expand-row]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            const row = btn.closest('[data-rekening-row]');
            const id = row.dataset.rekeningId;
            const detailRow = document.querySelector('[data-detail-for="' + id + '"]');
            if (!detailRow) return;
            const open = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (open) {
                expanded.delete(id);
                detailRow.hidden = true;
            } else {
                expanded.add(id);
                detailRow.hidden = false;
            }
        });
    });

    /* ---------- Pemilihan rekening ---------- */

    function lockForm() {
        if (detailFieldset) detailFieldset.disabled = true;
        if (lockOverlay) lockOverlay.hidden = false;
        if (summaryCard) summaryCard.hidden = true;
        selectedCard.hidden = true;
        emptyState.hidden = false;
        infoData = null;
        updateValidation();
    }

    function unlockForm() {
        if (detailFieldset) detailFieldset.disabled = false;
        if (lockOverlay) lockOverlay.hidden = true;
        emptyState.hidden = true;
        selectedCard.hidden = false;
        summaryCard.hidden = false;
        updateValidation();
    }

    function markSelectedRow(row) {
        rows.forEach(function (item) {
            const active = item === row;
            item.classList.toggle('is-selected', active);
            const btn = item.querySelector('[data-pick-rekening]');
            if (btn) {
                btn.classList.toggle('btn-primary', active);
                btn.classList.toggle('btn-outline-primary', !active);
                btn.textContent = active ? 'Dipilih' : 'Pilih';
            }
        });
    }

    function selectRekening(row) {
        if (!row || row.dataset.status === 'done') {
            if (row) showSelesaiNotice(row);
            return;
        }

        rekeningSel.value = row.dataset.rekeningId;
        infoData = null;
        markSelectedRow(row);
        unlockForm();

        selKode.textContent = row.dataset.kode;
        selName.textContent = row.dataset.nama;
        selPagu.textContent = rp(row.dataset.pagu);
        selSisa.textContent = rp(row.dataset.sisa);
        selSisa.classList.toggle('text-danger', Number(row.dataset.sisa) < 0);

        infoError.hidden = true;
        fetchInfo();

        if (isNarrow()) {
            const panel = document.getElementById('rpDetailPanel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (bentukInput) setTimeout(function () { bentukInput.focus({ preventScroll: true }); }, 250);
        } else if (bentukInput && !bentukInput.value) {
            bentukInput.focus({ preventScroll: true });
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('button, a, form, input, label')) return;
            if (row.dataset.status === 'done') {
                showSelesaiNotice(row);
                return;
            }
            if (rekeningSel.value === row.dataset.rekeningId) return;
            selectRekening(row);
        });

        const pickBtn = row.querySelector('[data-pick-rekening]');
        if (pickBtn) {
            pickBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                selectRekening(row);
            });
        }
    });

    if (changeBtn) {
        changeBtn.addEventListener('click', function () {
            rekeningSel.value = '0';
            infoData = null;
            realisasiBulanan = {};
            rows.forEach(function (item) {
                item.classList.remove('is-selected');
                const btn = item.querySelector('[data-pick-rekening]');
                if (btn) {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                    btn.textContent = 'Pilih';
                }
            });
            lockForm();
            updateNilaiMeta();
            updateChart();
            if (isNarrow() && listPanel) listPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (searchInput) searchInput.focus({ preventScroll: true });
        });
    }

    /* ---------- Ringkasan & grafik ---------- */

    function setLoading(on) {
        if (!summaryCard) return;
        summaryCard.classList.toggle('is-loading', on);
        selectedSide.classList.toggle('is-loading', on);
        if (on) chartNote.textContent = 'Memuat data rekening…';
    }

    function updateChart() {
        let max = 0;
        const totals = [];
        for (let b = 1; b <= 12; b++) {
            const total = Number((realisasiBulanan[b] || {}).total || 0);
            totals[b] = total;
            if (total > max) max = total;
        }

        const limit = pastMonthLimit();
        chartBars.forEach(function (bar, idx) {
            const b = idx + 1;
            const total = totals[b] || 0;
            bar.style.height = (total > 0 && max > 0) ? Math.max(4, (total / max) * 100) + '%' : '0';
            const row = realisasiBulanan[b] || {};
            const parts = [];
            ['GU', 'TU', 'LS', 'UP'].forEach(function (m) {
                if (Number(row[m] || 0) > 0) parts.push(m + ' ' + rp(row[m]));
            });
            bar.title = bulanNama[b - 1] + ': ' + rp(total) + (parts.length ? ' (' + parts.join(' · ') + ')' : '');
            chartCells[idx].classList.toggle('is-past', b <= limit);
        });

        const planValue = parseNominal(nilaiInput.value);
        let start = Number(bulanMulai.value || 1);
        let end = Number(bulanSelesai.value || start);
        if (start > end) { const tmp = start; start = end; end = tmp; }
        if (!isFinite(start) || start < 1) start = 1;
        if (!isFinite(end) || end > 12) end = 12;

        planBand.hidden = planValue <= 0;
        planBand.style.left = ((start - 1) / 12 * 100) + '%';
        planBand.style.width = ((end - start + 1) / 12 * 100) + '%';
        planBand.title = 'Rencana ' + rp(planValue) + ' · ' + bulanNama[start - 1]
            + (end > start ? '–' + bulanNama[end - 1] : '');

        const grand = totals.reduce(function (a, b) { return a + (b || 0); }, 0);
        const parts = [];
        parts.push(grand > 0 ? 'Realisasi terverifikasi ' + rp(grand) : 'Belum ada realisasi terverifikasi');
        if (planValue > 0) {
            parts.push('rencana ' + rp(planValue) + ' (' + bulanNama[start - 1]
                + (end > start ? '–' + bulanNama[end - 1] : '') + ')');
        }
        chartNote.textContent = parts.join(' · ') + '.';
    }

    function fillSummary(data) {
        infoData = data;
        const pagu = data.pagu;
        const sisa = data.sisa_kapasitas;

        selPagu.textContent = rpOr(pagu);
        selSisa.textContent = rpOr(sisa);
        selSisa.classList.toggle('text-danger', sisa !== null && sisa !== undefined && sisa < 0);

        sumPagu.textContent = rpOr(pagu);
        sumRealisasi.textContent = rp((data.realisasi || {}).total || 0);
        sumRencana.textContent = rp(data.total_rencana || 0);
        sumTu.textContent = rp(data.total_tu || 0);
        sumSisa.textContent = rpOr(sisa);
        sumSisa.classList.toggle('text-danger', sisa !== null && sisa !== undefined && sisa < 0);
        sumRak.textContent = rp(data.total_rak || 0);

        realisasiBulanan = data.realisasi_bulanan || {};
        updateNilaiMeta();
        updateValidation();
    }

    function nilaiLimit() {
        if (!infoData || infoData.sisa_kapasitas === null || infoData.sisa_kapasitas === undefined) return null;
        return Number(infoData.sisa_kapasitas);
    }

    function updateNilaiMeta() {
        const limit = nilaiLimit();
        if (limit === null) {
            nilaiHint.textContent = 'Sisa kapasitas belum tersedia.';
            fillSisaBtn.hidden = true;
            return;
        }
        nilaiHint.textContent = 'Maks: ' + rp(Math.max(0, limit)) + ' (sisa kapasitas)';
        fillSisaBtn.hidden = !(limit > 0.005);
    }

    function resetInfo(message) {
        infoData = null;
        realisasiBulanan = {};
        selPagu.textContent = '-';
        selSisa.textContent = '-';
        sumPagu.textContent = '-';
        sumRealisasi.textContent = 'Rp 0';
        sumRencana.textContent = 'Rp 0';
        sumTu.textContent = 'Rp 0';
        sumSisa.textContent = '-';
        sumRak.textContent = 'Rp 0';
        infoErrorText.textContent = message || 'Gagal memuat ringkasan rekening.';
        infoError.hidden = false;
        updateNilaiMeta();
        updateValidation();
    }

    function fetchInfo() {
        const rekeningId = Number(rekeningSel.value || 0);
        const tahun = Number(tahunInput.value || 0);

        if (!rekeningId || !tahun) {
            resetInfo('Pilih rekening untuk melihat ringkasan.');
            return;
        }

        const requestId = ++infoRequest;
        infoError.hidden = true;
        setLoading(true);

        let url = BASE + 'rencana-penyerapan/rekening-info?rekening_id=' + encodeURIComponent(rekeningId) + '&tahun=' + encodeURIComponent(tahun);
        if (excludeId) url += '&exclude_id=' + encodeURIComponent(excludeId);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (requestId !== infoRequest) return;
                setLoading(false);
                if (!data || !data.ok) {
                    resetInfo(data && data.message ? data.message : 'Gagal memuat ringkasan rekening.');
                    return;
                }
                fillSummary(data);
                updateChart();
            })
            .catch(function () {
                if (requestId !== infoRequest) return;
                setLoading(false);
                resetInfo('Gagal memuat ringkasan rekening. Periksa koneksi lalu coba lagi.');
            });
    }

    if (retryBtn) retryBtn.addEventListener('click', function () { fetchInfo(); });

    /* ---------- Validasi inline ---------- */

    function updateValidation() {
        const issues = [];

        if (!Number(rekeningSel.value)) issues.push('pilih rekening');

        const bentukOk = Boolean(String(bentukInput.value || '').trim());
        bentukError.hidden = bentukOk || !touched.bentuk || detailFieldset.disabled;
        if (!bentukOk) bentukError.textContent = 'Bentuk kegiatan wajib diisi.';
        bentukInput.classList.toggle('is-invalid', !bentukOk && touched.bentuk && !detailFieldset.disabled);

        const mekanisme = form.querySelector('input[name="mekanisme"]:checked');
        mekError.hidden = Boolean(mekanisme) || !touched.mekanisme || detailFieldset.disabled;
        if (!mekanisme) mekError.textContent = 'Pilih salah satu mekanisme pembayaran.';

        const start = Number(bulanMulai.value || 0);
        const end = Number(bulanSelesai.value || 0);
        const bulanBad = start > 0 && end > 0 && end < start;
        const showBulanError = bulanBad && touched.bulan && !detailFieldset.disabled;
        bulanError.hidden = !showBulanError;
        bulanMulai.classList.toggle('is-invalid', showBulanError);
        bulanSelesai.classList.toggle('is-invalid', showBulanError);

        const nilai = parseNominal(nilaiInput.value);
        const limit = nilaiLimit();
        let nilaiMessage = '';
        if (nilai <= 0) {
            nilaiMessage = 'Nilai rencana harus lebih dari 0.';
        } else if (limit !== null && nilai > limit + 0.005) {
            nilaiMessage = 'Nilai melebihi sisa kapasitas (' + rp(Math.max(0, limit)) + ').';
        }
        const showNilaiError = nilaiMessage !== '' && touched.nilai && !detailFieldset.disabled;
        nilaiError.hidden = !showNilaiError;
        nilaiError.textContent = nilaiMessage;
        nilaiInput.classList.toggle('is-invalid', showNilaiError);

        if (!bentukOk) issues.push('isi bentuk kegiatan');
        if (!mekanisme) issues.push('pilih mekanisme');
        if (bulanBad) issues.push('bulan selesai ≥ bulan mulai');
        if (nilai <= 0) issues.push('isi nilai rencana');
        else if (limit !== null && nilai > limit + 0.005) issues.push('nilai ≤ sisa kapasitas');

        const invalid = issues.length > 0 || detailFieldset.disabled;
        submitBtn.disabled = invalid;
        const locked = detailFieldset.disabled;
        saveHint.textContent = invalid
            ? (locked ? 'Pilih rekening untuk mulai mengisi rencana.' : 'Lengkapi: ' + issues.join(' · '))
            : 'Siap disimpan.';
        saveHint.classList.toggle('is-invalid', invalid);
        const reason = invalid ? 'Tidak bisa disimpan: ' + issues.join(', ') + '.' : '';
        submitWrap.title = reason;
        submitBtn.title = reason;
    }

    nilaiInput.addEventListener('input', function () {
        touched.nilai = true;
        formatNilai();
        updateNilaiMeta();
        updateValidation();
        updateChart();
    });
    nilaiInput.addEventListener('blur', function () {
        formatNilai();
    });

    if (fillSisaBtn) {
        fillSisaBtn.addEventListener('click', function () {
            const limit = nilaiLimit();
            if (limit === null || limit <= 0) return;
            touched.nilai = true;
            nilaiInput.value = Math.floor(limit).toLocaleString('id-ID');
            updateNilaiMeta();
            updateValidation();
            updateChart();
            nilaiInput.focus();
        });
    }

    bulanMulai.addEventListener('change', function () {
        touched.bulan = true;
        updateValidation();
        updateChart();
    });
    bulanSelesai.addEventListener('change', function () {
        touched.bulan = true;
        updateValidation();
        updateChart();
    });
    bentukInput.addEventListener('input', function () {
        touched.bentuk = true;
        updateValidation();
    });
    form.querySelectorAll('input[name="mekanisme"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            touched.mekanisme = true;
            updateValidation();
        });
    });

    if (tahunDisplay) {
        tahunDisplay.addEventListener('change', function () {
            tahunInput.value = String(Number(tahunDisplay.value || 0));
            if (formYearBadge) formYearBadge.textContent = 'Tahun ' + tahunInput.value;
            if (Number(rekeningSel.value)) fetchInfo();
            updateChart();
        });
    }

    form.addEventListener('submit', function (event) {
        touched.bentuk = touched.mekanisme = touched.nilai = touched.bulan = true;
        updateValidation();
        if (submitBtn.disabled) {
            event.preventDefault();
            return;
        }
        nilaiInput.value = String(parseNominal(nilaiInput.value));
    });

    /* ---------- Inisialisasi ---------- */

    if (!isEdit) applyFilters(false);

    if (initialSelection.id > 0) {
        selKode.textContent = initialSelection.kode || selKode.textContent;
        selName.textContent = initialSelection.nama || selName.textContent;
        let blocked = false;
        if (!isEdit) {
            const row = rows.find(function (r) { return Number(r.dataset.rekeningId) === initialSelection.id; });
            if (row && row.dataset.status === 'done') {
                blocked = true;
                showSelesaiNotice(row);
                lockForm();
            } else if (row) {
                markSelectedRow(row);
                selPagu.textContent = rp(row.dataset.pagu);
                selSisa.textContent = rp(row.dataset.sisa);
            }
        }
        if (!blocked) fetchInfo();
    } else {
        lockForm();
        updateChart();
    }

    formatNilai();
    updateNilaiMeta();
    updateValidation();
    updateChart();

    if (window.bootstrap && window.bootstrap.Popover) {
        const infoBtn = document.getElementById('rpSisaInfo');
        if (infoBtn) new window.bootstrap.Popover(infoBtn, { container: 'body' });
    }
});
</script>
