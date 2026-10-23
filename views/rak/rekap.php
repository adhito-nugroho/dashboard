<?php
$selectedYear = (int) ($filterTahun ?? date('Y'));
$rekap = is_array($rekap ?? null) ? $rekap : [];
$monthTotals = is_array($monthTotals ?? null) ? $monthTotals : array_fill(1, 12, 0);
$grandTotal = (float) ($grandTotal ?? 0);

$currentYear = (int) date('Y');
$years = range($currentYear - 2, $currentYear + 2);
if (!in_array($selectedYear, $years, true)) {
    $years[] = $selectedYear;
    sort($years);
}

$totalKegiatan = count($rekap);
$totalSubKegiatan = 0;
$totalRekening = 0;
foreach ($rekap as $kegiatan) {
    $totalSubKegiatan += count($kegiatan['sub_kegiatan'] ?? []);
    foreach (($kegiatan['sub_kegiatan'] ?? []) as $subkeg) {
        $totalRekening += count($subkeg['rekening'] ?? []);
    }
}

$renderAmount = static function ($value, bool $strong = false): string {
    $value = (float) $value;
    if (abs($value) < 0.00001) {
        return '<span class="rak-zero">-</span>';
    }

    $formatted = number_format($value, 0, ',', '.');
    return $strong ? '<span class="rak-amount-strong">' . $formatted . '</span>' : $formatted;
};

$bulanShort = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
               7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
$bulanLong = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
              7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

$exportUrl = base_url('rak/export') . '?' . http_build_query(['tahun' => $selectedYear]);
?>

<style>
.rak-rekap-page { min-width: 0; }
.rak-rekap-card {
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 28px -22px rgba(15, 23, 42, 0.45);
}
.rak-rekap-header {
    padding: 1rem 1.25rem;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}
.rak-rekap-heading { flex: 1 1 420px; min-width: 0; }
.rak-rekap-eyebrow {
    margin-bottom: 0.2rem;
    color: #94a3b8;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}
.rak-rekap-title {
    margin: 0;
    color: #0f172a;
    font-size: 1.06rem;
    font-weight: 700;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.rak-rekap-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.6rem;
    margin-top: 0.4rem;
    color: #64748b;
    font-size: 0.72rem;
}
.rak-total-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.16rem 0.55rem;
    border: 1px solid #a7f3d0;
    border-radius: 999px;
    background: #ecfdf5;
    color: #047857;
    font-weight: 600;
}
.rak-rekap-toolbar {
    display: flex;
    flex: 0 0 auto;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    max-width: 100%;
    min-width: 0;
}
.rak-rekap-toolbar .btn { white-space: nowrap; font-size: 0.78rem; }
.rak-year-select { width: 96px; font-size: 0.78rem; }
.rak-search { position: relative; flex: 0 1 230px; min-width: 170px; max-width: 260px; }
.rak-search .bi-search {
    position: absolute;
    top: 50%;
    left: 0.62rem;
    z-index: 2;
    color: #94a3b8;
    font-size: 0.76rem;
    pointer-events: none;
    transform: translateY(-50%);
}
.rak-search input { height: 31px; padding-right: 1.9rem; padding-left: 1.85rem; font-size: 0.78rem; }
.rak-search input::-webkit-search-cancel-button { display: none; }
.rak-search-clear {
    position: absolute;
    top: 50%;
    right: 0.3rem;
    display: none;
    padding: 0.15rem;
    border: 0;
    background: transparent;
    color: #94a3b8;
    line-height: 1;
    transform: translateY(-50%);
}
.rak-search-clear.is-visible { display: inline-flex; }
.rak-search-clear:hover { color: #475569; }

.rak-scroll-hint {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.74rem;
}
.rak-rekap-scroll {
    max-height: calc(100vh - 260px);
    min-height: 280px;
    overflow: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
}
.rak-rekap-scroll:focus-visible { outline: 2px solid #34d399; outline-offset: -2px; }

.rak-rekap-table {
    width: 100%;
    min-width: 1320px;
    border-collapse: separate;
    border-spacing: 0;
    color: #334155;
    font-size: 0.78rem;
}
.rak-rekap-table th,
.rak-rekap-table td {
    padding: 0.5rem 0.6rem;
    border-right: 1px solid #f1f5f9;
    border-bottom: 1px solid #eef2f7;
    text-align: left;
    vertical-align: middle;
}
.rak-rekap-table th:last-child,
.rak-rekap-table td:last-child { border-right: 0; }
.rak-rekap-table thead th {
    position: sticky;
    top: 0;
    z-index: 30;
    border-bottom: 2px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-align: center;
    text-transform: uppercase;
    white-space: nowrap;
}
.rak-col-name { width: 340px; min-width: 340px; max-width: 380px; }
.rak-col-month { width: 74px; min-width: 74px; }
.rak-col-total { width: 118px; min-width: 118px; text-align: right; }
.rak-rekap-table thead th.rak-col-name { text-align: left; }

.rak-rekap-table thead th.rak-col-name { left: 0; z-index: 50; }
.rak-rekap-table thead th.rak-col-total { right: 0; z-index: 50; }
.rak-rekap-table tbody td.rak-col-name,
.rak-rekap-table tfoot td.rak-col-name {
    position: sticky;
    left: 0;
    z-index: 20;
    background: inherit;
    box-shadow: 5px 0 8px -6px rgba(15, 23, 42, 0.28);
}
.rak-rekap-table tbody td.rak-col-total,
.rak-rekap-table tfoot td.rak-col-total {
    position: sticky;
    right: 0;
    z-index: 20;
    background: inherit;
    box-shadow: -5px 0 8px -6px rgba(15, 23, 42, 0.28);
}

.rak-rekap-table tbody tr { --rak-row-bg: #fff; background: var(--rak-row-bg); }
.rak-rekap-table tbody tr.rak-row-kegiatan { --rak-row-bg: #f8fafc; }
.rak-rekap-table tbody tr:hover { --rak-row-bg: #f1f5f9; }
.rak-rekap-table tbody td { background: inherit; }
.rak-rekap-table tbody tr.rak-row-kegiatan td { border-top: 1px solid #e2e8f0; }
.rak-rekap-table [hidden] { display: none !important; }

.rak-name-cell { display: flex; align-items: flex-start; gap: 0.5rem; min-width: 0; }
.rak-toggle {
    display: inline-flex;
    flex: 0 0 26px;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    padding: 0;
    border: 0;
    border-radius: 7px;
    background: #eef2f7;
    color: #475569;
    transition: background-color 0.15s ease, color 0.15s ease;
}
.rak-toggle:hover { background: #dbeafe; color: #1d4ed8; }
.rak-toggle:focus-visible { outline: 2px solid #34d399; outline-offset: 1px; }
.rak-toggle i { font-size: 0.7rem; transition: transform 0.18s ease; }
.rak-toggle[aria-expanded="true"] i { transform: rotate(90deg); }
.rak-toggle-spacer { flex: 0 0 26px; width: 26px; }
.rak-name-content { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.rak-code {
    align-self: flex-start;
    padding: 0.04rem 0.3rem;
    border: 1px solid #d1fae5;
    border-radius: 0.3rem;
    background: #ecfdf5;
    color: #047857;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.66rem;
    font-weight: 600;
    line-height: 1.35;
    white-space: nowrap;
}
.rak-name { color: #1e293b; font-weight: 500; line-height: 1.35; overflow-wrap: anywhere; }
.rak-row-kegiatan .rak-name { font-weight: 600; }
.rak-row-sub .rak-name { font-weight: 600; }
.rak-row-rek .rak-name { color: #475569; font-size: 0.76rem; font-weight: 400; }
.rak-row-rek .rak-code { border-color: #e2e8f0; background: #f8fafc; color: #64748b; font-weight: 500; }
.rak-row-sub .rak-name-cell { padding-left: 0.9rem; }
.rak-row-rek .rak-name-cell { padding-left: 2.15rem; }

.rak-rekap-table td.rak-num {
    color: #334155;
    font-size: 0.78rem;
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum";
    text-align: right;
    white-space: nowrap;
}
.rak-row-kegiatan td.rak-num { color: #1e293b; font-weight: 500; }
.rak-row-sub td.rak-num { font-weight: 400; }
.rak-row-rek td.rak-num { color: #64748b; font-weight: 400; }
.rak-total-cell { color: #0f172a !important; font-weight: 600 !important; }
.rak-row-rek .rak-total-cell { color: #475569 !important; font-weight: 500 !important; }
.rak-zero { color: #cbd5e1; font-weight: 400; }

.rak-rekap-table tfoot td {
    position: sticky;
    bottom: 0;
    z-index: 28;
    border-top: 2px solid #10b981;
    border-bottom: 0;
    background: #ecfdf5;
    color: #064e3b;
    font-weight: 600;
    white-space: nowrap;
}
.rak-rekap-table tfoot td.rak-col-name { z-index: 48; background: #ecfdf5; }
.rak-rekap-table tfoot td.rak-col-total { z-index: 48; background: #d1fae5; }
.rak-total-label { display: block; font-size: 0.7rem; letter-spacing: 0.06em; text-transform: uppercase; }
.rak-total-sub { display: block; margin-top: 0.1rem; color: #047857; font-size: 0.66rem; font-weight: 500; }

.rak-no-match td {
    padding: 2rem 1rem;
    background: #fff;
    color: #94a3b8;
    text-align: center;
}
.rak-empty-state { padding: 3rem 1rem; color: #64748b; text-align: center; }
.rak-empty-state i { display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 2rem; }

@media (max-width: 991.98px) {
    .rak-rekap-header { padding: 1rem; }
    .rak-rekap-toolbar { width: 100%; flex-wrap: wrap; justify-content: flex-start; }
    .rak-search { flex: 1 1 100%; max-width: none; }
    .rak-rekap-scroll { max-height: calc(100vh - 320px); }
}

@media (max-width: 575.98px) {
    .rak-rekap-title { font-size: 0.98rem; }
    .rak-rekap-meta { font-size: 0.72rem; }
    .rak-col-name { width: 280px; min-width: 280px; max-width: 300px; }
    .rak-rekap-table { min-width: 1180px; }
    .rak-rekap-table tbody td.rak-col-total,
    .rak-rekap-table tfoot td.rak-col-total { position: static; box-shadow: none; }
    .rak-rekap-table thead th.rak-col-total { right: auto; }
}
</style>

<div class="rak-rekap-page">
    <div class="card rak-rekap-card">
        <div class="card-header rak-rekap-header">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="rak-rekap-heading">
                    <div class="rak-rekap-eyebrow">Monitoring Anggaran / RAK</div>
                    <h2 class="rak-rekap-title">Rekapitulasi RAK Tahun <?= $selectedYear ?></h2>
                    <div class="rak-rekap-meta">
                        <span class="rak-total-badge">
                            <i class="bi bi-cash-stack"></i>
                            Total Pagu <strong>Rp <?= number_format($grandTotal, 0, ',', '.') ?></strong>
                        </span>
                        <span><i class="bi bi-diagram-3 me-1"></i><?= $totalKegiatan ?> kegiatan</span>
                        <span><i class="bi bi-list-nested me-1"></i><?= $totalSubKegiatan ?> sub kegiatan</span>
                        <span><i class="bi bi-wallet2 me-1"></i><?= $totalRekening ?> rekening</span>
                    </div>
                </div>

                <div class="rak-rekap-toolbar">
                    <form method="GET" action="<?= base_url('rak/rekap') ?>" class="m-0">
                        <label for="rakTahun" class="visually-hidden">Tahun anggaran</label>
                        <select name="tahun" id="rakTahun" class="form-select form-select-sm rak-year-select" onchange="this.form.submit()" title="Pilih tahun anggaran">
                            <?php foreach ($years as $year): ?>
                                <option value="<?= (int) $year ?>" <?= (int) $year === $selectedYear ? 'selected' : '' ?>><?= (int) $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <div class="rak-search">
                        <i class="bi bi-search"></i>
                        <input type="search" id="rakSearch" class="form-control form-control-sm" placeholder="Cari kode atau uraian..." autocomplete="off" aria-label="Cari kode atau uraian">
                        <button type="button" id="rakSearchClear" class="rak-search-clear" aria-label="Bersihkan pencarian" title="Bersihkan pencarian">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>

                    <button type="button" id="rakToggleAll" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrows-expand me-1"></i>Expand All
                    </button>

                    <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-sm btn-success" title="Export data RAK tahun <?= $selectedYear ?> ke Excel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($rekap)): ?>
                <div class="rak-empty-state">
                    <i class="bi bi-inbox"></i>
                    <div class="fw-semibold text-dark">Data RAK tidak ditemukan</div>
                    <div class="small">Belum ada rencana anggaran kas untuk tahun <?= $selectedYear ?>.</div>
                </div>
            <?php else: ?>
                <div class="rak-scroll-hint" id="rakScrollHint">
                    <i class="bi bi-arrows-expand"></i>
                    <span>Geser tabel untuk melihat seluruh bulan dan total.</span>
                </div>

                <div class="rak-rekap-scroll" id="rakRekapScroll" tabindex="0" role="region" aria-label="Tabel rekapitulasi RAK tahun <?= $selectedYear ?>">
                    <table class="rak-rekap-table" id="rakRekapTable" aria-label="Rekapitulasi RAK tahun <?= $selectedYear ?>">
                        <thead>
                            <tr>
                                <th scope="col" class="rak-col-name">Kode - Uraian</th>
                                <?php foreach ($bulanShort as $i => $label): ?>
                                    <th scope="col" class="rak-col-month" title="<?= $bulanLong[$i] ?>" aria-label="Bulan <?= str_pad((string) $i, 2, '0', STR_PAD_LEFT) ?> - <?= $bulanLong[$i] ?>">
                                        <?= str_pad((string) $i, 2, '0', STR_PAD_LEFT) ?>
                                    </th>
                                <?php endforeach; ?>
                                <th scope="col" class="rak-col-total">Total</th>
                            </tr>
                        </thead>
                        <tbody id="rakRekapBody">
                            <?php foreach ($rekap as $kId => $kegiatan): ?>
                                <tr class="rak-row rak-row-kegiatan"
                                    data-rak-id="k<?= (int) $kId ?>"
                                    data-rak-parent=""
                                    data-search="<?= htmlspecialchars(strtolower(($kegiatan['kode_kegiatan'] ?? '') . ' ' . ($kegiatan['nama_kegiatan'] ?? ''))) ?>">
                                    <td class="rak-col-name">
                                        <div class="rak-name-cell">
                                            <button type="button" class="rak-toggle" aria-expanded="false" aria-label="Buka atau tutup rincian kegiatan" title="Buka atau tutup rincian">
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                            <div class="rak-name-content">
                                                <span class="rak-code"><?= htmlspecialchars($kegiatan['kode_kegiatan'] ?? '') ?></span>
                                                <span class="rak-name"><?= htmlspecialchars($kegiatan['nama_kegiatan'] ?? '') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <td class="rak-num"><?= $renderAmount($kegiatan['months'][$i] ?? 0) ?></td>
                                    <?php endfor; ?>
                                    <td class="rak-num rak-col-total rak-total-cell"><?= $renderAmount($kegiatan['total'] ?? 0, true) ?></td>
                                </tr>

                                <?php foreach (($kegiatan['sub_kegiatan'] ?? []) as $skId => $subkeg): ?>
                                    <tr class="rak-row rak-row-sub"
                                        data-rak-id="s<?= (int) $skId ?>"
                                        data-rak-parent="k<?= (int) $kId ?>"
                                        data-search="<?= htmlspecialchars(strtolower(($subkeg['kode_sub_kegiatan'] ?? '') . ' ' . ($subkeg['nama_sub_kegiatan'] ?? ''))) ?>">
                                        <td class="rak-col-name">
                                            <div class="rak-name-cell">
                                                <button type="button" class="rak-toggle" aria-expanded="false" aria-label="Buka atau tutup rincian sub kegiatan" title="Buka atau tutup rincian">
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                                <div class="rak-name-content">
                                                    <span class="rak-code"><?= htmlspecialchars($subkeg['kode_sub_kegiatan'] ?? '') ?></span>
                                                    <span class="rak-name"><?= htmlspecialchars($subkeg['nama_sub_kegiatan'] ?? '') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <td class="rak-num"><?= $renderAmount($subkeg['months'][$i] ?? 0) ?></td>
                                        <?php endfor; ?>
                                        <td class="rak-num rak-col-total rak-total-cell"><?= $renderAmount($subkeg['total'] ?? 0, true) ?></td>
                                    </tr>

                                    <?php foreach (($subkeg['rekening'] ?? []) as $rId => $rek): ?>
                                        <tr class="rak-row rak-row-rek"
                                            data-rak-id="r<?= (int) $rId ?>"
                                            data-rak-parent="s<?= (int) $skId ?>"
                                            data-search="<?= htmlspecialchars(strtolower(($rek['kode_rekening'] ?? '') . ' ' . ($rek['nama_rekening'] ?? ''))) ?>">
                                            <td class="rak-col-name">
                                                <div class="rak-name-cell">
                                                    <span class="rak-toggle-spacer" aria-hidden="true"></span>
                                                    <div class="rak-name-content">
                                                        <span class="rak-code"><?= htmlspecialchars($rek['kode_rekening'] ?? '') ?></span>
                                                        <span class="rak-name"><?= htmlspecialchars($rek['nama_rekening'] ?? '') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <td class="rak-num"><?= $renderAmount($rek['months'][$i] ?? 0) ?></td>
                                            <?php endfor; ?>
                                            <td class="rak-num rak-col-total rak-total-cell"><?= $renderAmount($rek['total'] ?? 0, true) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>

                            <tr id="rakNoMatchRow" class="rak-no-match" hidden>
                                <td colspan="14">
                                    <i class="bi bi-search me-1"></i>Tidak ada kode atau uraian yang cocok dengan pencarian.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="rak-col-name">
                                    <span class="rak-total-label">Total</span>
                                    <span class="rak-total-sub">Total per bulan</span>
                                </td>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <td class="rak-num"><?= $renderAmount($monthTotals[$i] ?? 0, true) ?></td>
                                <?php endfor; ?>
                                <td class="rak-num rak-col-total rak-total-cell"><?= $renderAmount($grandTotal, true) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('rakRekapTable');
    const tbody = document.getElementById('rakRekapBody');
    if (!table || !tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr.rak-row'));
    const rowById = new Map(rows.map(function (row) { return [row.dataset.rakId, row]; }));
    const childCount = new Map();
    rows.forEach(function (row) {
        const parentId = row.dataset.rakParent;
        if (parentId) childCount.set(parentId, (childCount.get(parentId) || 0) + 1);
    });

    const open = new Set();
    const searchInput = document.getElementById('rakSearch');
    const searchClear = document.getElementById('rakSearchClear');
    const toggleAllBtn = document.getElementById('rakToggleAll');
    const noMatchRow = document.getElementById('rakNoMatchRow');
    const scrollContainer = document.getElementById('rakRekapScroll');
    const scrollHint = document.getElementById('rakScrollHint');
    let query = '';
    let searchVisible = new Set();

    function parentOf(row) {
        return row && row.dataset.rakParent ? rowById.get(row.dataset.rakParent) : null;
    }

    function hasChildren(row) {
        return (childCount.get(row.dataset.rakId) || 0) > 0;
    }

    function ancestorsOpen(row) {
        let parent = parentOf(row);
        while (parent) {
            if (!open.has(parent.dataset.rakId)) return false;
            parent = parentOf(parent);
        }
        return true;
    }

    function buildSearchVisible(needle) {
        const visible = new Set();
        if (!needle) return visible;

        rows.forEach(function (row) {
            if (!(row.dataset.search || '').includes(needle)) return;
            visible.add(row.dataset.rakId);
            let parent = parentOf(row);
            while (parent) {
                visible.add(parent.dataset.rakId);
                parent = parentOf(parent);
            }
        });

        return visible;
    }

    function updateToggleState() {
        rows.forEach(function (row) {
            const toggle = row.querySelector('.rak-toggle');
            if (!toggle) return;
            const expanded = query ? searchVisible.has(row.dataset.rakId) : open.has(row.dataset.rakId);
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });

        if (!toggleAllBtn) return;
        const parents = rows.filter(hasChildren);
        const allOpen = parents.length > 0 && parents.every(function (row) { return open.has(row.dataset.rakId); });
        toggleAllBtn.innerHTML = allOpen
            ? '<i class="bi bi-arrows-collapse me-1"></i>Collapse All'
            : '<i class="bi bi-arrows-expand me-1"></i>Expand All';
    }

    function updateScrollHint() {
        if (!scrollContainer || !scrollHint) return;
        scrollHint.hidden = scrollContainer.scrollWidth <= scrollContainer.clientWidth + 1;
    }

    function render() {
        query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        searchVisible = buildSearchVisible(query);
        let anyVisible = false;

        rows.forEach(function (row) {
            const visible = query ? searchVisible.has(row.dataset.rakId) : ancestorsOpen(row);
            row.hidden = !visible;
            if (visible) anyVisible = true;
        });

        if (noMatchRow) noMatchRow.hidden = !query || anyVisible;
        if (searchClear) searchClear.classList.toggle('is-visible', query !== '');
        updateToggleState();
    }

    function toggleRow(row) {
        if (!hasChildren(row)) return;

        if (query) {
            query = '';
            if (searchInput) searchInput.value = '';
        }

        const id = row.dataset.rakId;
        if (open.has(id)) {
            open.delete(id);
        } else {
            open.add(id);
        }
        render();
    }

    rows.forEach(function (row) {
        const toggle = row.querySelector('.rak-toggle');
        if (toggle) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                toggleRow(row);
            });
        }

        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, select, textarea')) return;
            if (hasChildren(row)) toggleRow(row);
        });
    });

    if (toggleAllBtn) {
        toggleAllBtn.addEventListener('click', function () {
            if (query) {
                query = '';
                if (searchInput) searchInput.value = '';
            }

            const parents = rows.filter(hasChildren);
            const allOpen = parents.length > 0 && parents.every(function (row) { return open.has(row.dataset.rakId); });

            if (allOpen) {
                open.clear();
            } else {
                parents.forEach(function (row) { open.add(row.dataset.rakId); });
            }
            render();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', render);
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                searchInput.value = '';
                render();
                searchInput.blur();
            }
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            render();
        });
    }

    window.addEventListener('resize', updateScrollHint);
    window.addEventListener('load', updateScrollHint);
    render();
    updateScrollHint();
});
</script>
