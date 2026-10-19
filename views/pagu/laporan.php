<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
$totSelisih = $totAkhir - $totAwal;
$totSisa = $totAkhir - $totReal;
$totalRekening = count($rows);
$totTambah = 0.0;
$totKurang = 0.0;
$jumlahBerubah = 0;
foreach ($rows as $row) {
    $selisih = (float) ($row['selisih'] ?? 0);
    if ($selisih > 0.009) {
        $totTambah += $selisih;
        $jumlahBerubah++;
    } elseif ($selisih < -0.009) {
        $totKurang += $selisih;
        $jumlahBerubah++;
    }
}
?>
<style>
.main-content,
.main-content > .content {
    min-width: 0;
}
.pagu-laporan-page {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding-inline: clamp(0.25rem, 0.8vw, 0.75rem);
}
.pagu-laporan-page .pagu-laporan-head {
    min-width: 0;
}
.pagu-laporan-page .pagu-laporan-head h2 {
    font-size: clamp(1.15rem, 1.8vw, 1.5rem);
    line-height: 1.3;
}
.pagu-laporan-page .pagu-laporan-head > div:first-child {
    min-width: 0;
}
.pagu-laporan-page .pagu-laporan-actions {
    flex-shrink: 0;
}
.pagu-summary-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.pagu-summary-card {
    height: 100%;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #94a3b8;
    border-radius: 0.75rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.pagu-summary-card .card-body {
    padding: 1rem 1.15rem;
}
.pagu-summary-label {
    display: block;
    margin-bottom: 0.35rem;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.pagu-summary-value {
    display: block;
    color: #0f172a;
    font-size: clamp(0.85rem, 1.35vw, 1.2rem);
    font-variant-numeric: tabular-nums;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
}
.pagu-summary-note {
    display: block;
    margin-top: 0.35rem;
    color: #64748b;
    font-size: 0.75rem;
}
.pagu-summary-lines {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.pagu-summary-line {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.15rem 0.75rem;
}
.pagu-summary-line span {
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.pagu-summary-line strong {
    margin-left: auto;
    color: #0f172a;
    font-size: clamp(0.8rem, 1.2vw, 1.05rem);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.pagu-accent-neutral { border-left-color: #64748b; }
.pagu-accent-primary { border-left-color: #4338ca; }
.pagu-accent-success { border-left-color: #059669; }
.pagu-accent-danger { border-left-color: #dc2626; }
.pagu-accent-warning { border-left-color: #d97706; }
.pagu-search {
    position: relative;
    width: min(100%, 320px);
}
.pagu-search i {
    position: absolute;
    top: 50%;
    left: 0.8rem;
    color: #94a3b8;
    transform: translateY(-50%);
    pointer-events: none;
}
.pagu-search .form-control {
    padding-left: 2.15rem;
}
.pagu-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.pagu-laporan-table {
    min-width: 1180px;
    margin-bottom: 0;
}
.pagu-laporan-table > :not(caption) > * > * {
    vertical-align: middle;
}
.pagu-laporan-table .money {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}
.pagu-laporan-table .col-kode {
    min-width: 120px;
    white-space: nowrap;
}
.pagu-laporan-table .col-nama {
    min-width: 240px;
}
.pagu-laporan-table .col-sub {
    min-width: 230px;
}
.pagu-laporan-table .col-aksi {
    min-width: 64px;
}
@media (min-width: 768px) {
    .pagu-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 992px) {
    .pagu-summary-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>

<div class="pagu-laporan-page py-4">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="pagu-laporan-head d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-clipboard-data text-info me-2"></i>Laporan Selisih APBD vs PAPBD</h2>
            <p class="text-muted mb-0">Perbandingan pagu awal, pagu berjalan, realisasi, dan sisa per rekening</p>
        </div>
        <div class="pagu-laporan-actions d-flex flex-wrap gap-2 align-items-center">
            <form method="GET" action="<?= base_url('pagu/laporan') ?>" class="d-flex gap-2 align-items-center">
                <label for="tahun" class="visually-hidden">Tahun</label>
                <input type="number" id="tahun" name="tahun" class="form-control form-control-sm" min="2000" max="2100" value="<?= (int) $tahun ?>" style="width:110px">
                <button class="btn btn-outline-primary btn-sm" type="submit">Tampil</button>
            </form>
            <a href="<?= base_url('pagu/laporan/excel?tahun=' . (int) $tahun) ?>" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
            <a href="<?= base_url('pagu?tahun=' . (int) $tahun) ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Data Pagu
            </a>
        </div>
    </div>

    <div class="pagu-summary-grid">
        <div class="card pagu-summary-card pagu-accent-neutral">
            <div class="card-body">
                <span class="pagu-summary-label">Total APBD Awal <?= (int) $tahun ?></span>
                <span class="pagu-summary-value">Rp <?= number_format($totAwal, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="card pagu-summary-card pagu-accent-primary">
            <div class="card-body">
                <span class="pagu-summary-label">Total PAPBD</span>
                <span class="pagu-summary-value text-primary">Rp <?= number_format($totAkhir, 0, ',', '.') ?></span>
            </div>
        </div>
        <div class="card pagu-summary-card <?= $totSelisih >= 0 ? 'pagu-accent-success' : 'pagu-accent-danger' ?>">
            <div class="card-body">
                <span class="pagu-summary-label">Selisih (PAPBD - APBD Murni)</span>
                <span class="pagu-summary-value <?= $totSelisih >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $totSelisih >= 0 ? '+' : '' ?>Rp <?= number_format($totSelisih, 0, ',', '.') ?>
                </span>
                <small class="pagu-summary-note">
                    +Rp <?= number_format($totTambah, 0, ',', '.') ?> /
                    -Rp <?= number_format(abs($totKurang), 0, ',', '.') ?>
                    (<?= (int) $jumlahBerubah ?>x)
                </small>
            </div>
        </div>
        <div class="card pagu-summary-card pagu-accent-warning">
            <div class="card-body">
                <span class="pagu-summary-label">Realisasi / Sisa</span>
                <div class="pagu-summary-lines">
                    <div class="pagu-summary-line">
                        <span>Realisasi</span>
                        <strong>Rp <?= number_format($totReal, 0, ',', '.') ?></strong>
                    </div>
                    <div class="pagu-summary-line">
                        <span>Sisa</span>
                        <strong class="<?= $totSisa < 0 ? 'text-danger' : '' ?>">Rp <?= number_format($totSisa, 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2 mb-0">Belum ada pagu tahun <?= (int) $tahun ?></p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-0">Rincian per Rekening</h5>
                        <small class="text-muted" id="searchCount"><?= (int) $totalRekening ?> rekening</small>
                    </div>
                    <div class="pagu-search">
                        <i class="bi bi-search"></i>
                        <input type="search" id="searchRekening" class="form-control form-control-sm" placeholder="Cari kode atau nama rekening..." aria-label="Cari kode atau nama rekening" autocomplete="off">
                    </div>
                </div>

                <div class="table-responsive pagu-table-wrap">
                    <table class="table table-hover table-sm align-middle pagu-laporan-table" id="paguLaporanTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:52px">No</th>
                                <th class="col-kode">Kode Rekening</th>
                                <th class="col-nama">Nama Rekening</th>
                                <th class="col-sub">Sub Kegiatan</th>
                                <th class="money">APBD Awal</th>
                                <th class="money">PAPBD</th>
                                <th class="money">Selisih</th>
                                <th class="money">Realisasi</th>
                                <th class="money">Sisa</th>
                                <th class="text-center col-aksi">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="paguLaporanBody">
                            <?php foreach ($rows as $i => $r): ?>
                                <?php $s = (float) $r['selisih']; $sisa = (float) $r['sisa_baru']; ?>
                                <tr class="<?= $sisa < 0 ? 'table-danger' : '' ?>" data-search="<?= htmlspecialchars(($r['kode_rekening'] ?? '') . ' ' . ($r['nama_rekening'] ?? '') . ' ' . ($r['nama_sub_kegiatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <td class="text-center text-muted"><?= $i + 1 ?></td>
                                    <td class="col-kode"><span class="badge bg-warning text-dark"><?= htmlspecialchars($r['kode_rekening'] ?? '') ?></span></td>
                                    <td class="col-nama"><?= htmlspecialchars($r['nama_rekening'] ?? '') ?></td>
                                    <td class="col-sub"><small><?= htmlspecialchars($r['nama_sub_kegiatan'] ?? '') ?></small></td>
                                    <td class="money text-muted">Rp <?= number_format($r['pagu_awal'], 0, ',', '.') ?></td>
                                    <td class="money"><strong>Rp <?= number_format($r['nilai_pagu'], 0, ',', '.') ?></strong></td>
                                    <td class="money <?= $s > 0 ? 'text-success' : ($s < 0 ? 'text-danger' : 'text-muted') ?>"><?= $s > 0 ? '+' : '' ?>Rp <?= number_format($s, 0, ',', '.') ?></td>
                                    <td class="money">Rp <?= number_format($r['realisasi'], 0, ',', '.') ?></td>
                                    <td class="money <?= $sisa < 0 ? 'fw-bold text-danger' : '' ?>">Rp <?= number_format($sisa, 0, ',', '.') ?></td>
                                    <td class="text-center col-aksi">
                                        <a href="<?= base_url('pagu/riwayat/' . $r['id']) ?>" class="btn btn-sm btn-outline-info" title="Riwayat">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="paguSearchEmpty" class="d-none">
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-search me-1"></i> Tidak ada rekening yang cocok dengan pencarian.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">TOTAL</th>
                                <th class="money">Rp <?= number_format($totAwal, 0, ',', '.') ?></th>
                                <th class="money">Rp <?= number_format($totAkhir, 0, ',', '.') ?></th>
                                <th class="money">Rp <?= number_format($totSelisih, 0, ',', '.') ?></th>
                                <th class="money">Rp <?= number_format($totReal, 0, ',', '.') ?></th>
                                <th class="money">Rp <?= number_format($totSisa, 0, ',', '.') ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('searchRekening');
    var table = document.getElementById('paguLaporanTable');
    if (!input || !table) {
        return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-search]'));
    var empty = document.getElementById('paguSearchEmpty');
    var count = document.getElementById('searchCount');
    var total = rows.length;

    function filterRows() {
        var query = input.value.trim().toLowerCase();
        var visible = 0;

        rows.forEach(function (row) {
            var match = query === '' || (row.getAttribute('data-search') || '').toLowerCase().indexOf(query) !== -1;
            row.classList.toggle('d-none', !match);
            if (match) {
                visible++;
                var numberCell = row.querySelector('td:first-child');
                if (numberCell) {
                    numberCell.textContent = visible;
                }
            }
        });

        if (empty) {
            empty.classList.toggle('d-none', visible !== 0);
        }
        if (count) {
            count.textContent = query === ''
                ? total + ' rekening'
                : visible + ' dari ' + total + ' rekening';
        }
    }

    input.addEventListener('input', filterRows);
})();
</script>
