<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
$totSelisih = $totAkhir - $totAwal;
$totSisa = $totAkhir - $totReal;
?>
<div class="container-fluid py-4">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1"><i class="bi bi-clipboard-data text-info me-2"></i>Laporan Selisih APBD vs PAPBD</h2>
            <p class="text-muted mb-0">Perbandingan pagu awal, pagu berjalan, realisasi, dan sisa per rekening</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="<?= base_url('pagu/laporan') ?>" class="d-flex gap-2">
                <input type="number" name="tahun" class="form-control" min="2000" max="2100" value="<?= (int) $tahun ?>" style="width:120px">
                <button class="btn btn-outline-primary" type="submit">Tampil</button>
            </form>
            <a href="<?= base_url('pagu?tahun=' . (int) $tahun) ?>" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Data Pagu</a>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-3"><div class="card"><div class="card-body">
            <small class="text-muted text-uppercase">Total APBD Awal <?= (int) $tahun ?></small>
            <h5 class="mb-0">Rp <?= number_format($totAwal, 0, ',', '.') ?></h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-primary"><div class="card-body">
            <small class="text-muted text-uppercase">Total PAPBD</small>
            <h5 class="mb-0 text-primary">Rp <?= number_format($totAkhir, 0, ',', '.') ?></h5>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <small class="text-muted text-uppercase">Selisih (Tambah − Kurang)</small>
            <h5 class="mb-0 <?= $totSelisih >= 0 ? 'text-success' : 'text-danger' ?>"><?= $totSelisih >= 0 ? '+' : '' ?>Rp <?= number_format($totSelisih, 0, ',', '.') ?></h5>
            <small class="text-muted">+Rp <?= number_format($ringkasan['total_tambah'], 0, ',', '.') ?> / <?= number_format($ringkasan['total_kurang'], 0, ',', '.') ?> (<?= (int) $ringkasan['jumlah_perubahan'] ?>x)</small>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <small class="text-muted text-uppercase">Realisasi / Sisa</small>
            <h6 class="mb-0">Rp <?= number_format($totReal, 0, ',', '.') ?> / Rp <?= number_format($totSisa, 0, ',', '.') ?></h6>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-center text-muted py-4"><i class="bi bi-inbox fs-1"></i><p class="mt-2">Belum ada pagu tahun <?= (int) $tahun ?></p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Kode Rekening</th>
                                <th>Nama Rekening</th>
                                <th>Sub Kegiatan</th>
                                <th class="text-end">APBD Awal</th>
                                <th class="text-end">PAPBD</th>
                                <th class="text-end">Selisih</th>
                                <th class="text-end">Realisasi</th>
                                <th class="text-end">Sisa</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $i => $r): ?>
                                <?php $s = (float) $r['selisih']; $sisa = (float) $r['sisa_baru']; ?>
                                <tr class="<?= $sisa < 0 ? 'table-danger' : '' ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($r['kode_rekening'] ?? '') ?></span></td>
                                    <td><?= htmlspecialchars($r['nama_rekening'] ?? '') ?></td>
                                    <td><small><?= htmlspecialchars($r['nama_sub_kegiatan'] ?? '') ?></small></td>
                                    <td class="text-end text-muted">Rp <?= number_format($r['pagu_awal'], 0, ',', '.') ?></td>
                                    <td class="text-end"><strong>Rp <?= number_format($r['nilai_pagu'], 0, ',', '.') ?></strong></td>
                                    <td class="text-end <?= $s > 0 ? 'text-success' : ($s < 0 ? 'text-danger' : 'text-muted') ?>"><?= $s > 0 ? '+' : '' ?>Rp <?= number_format($s, 0, ',', '.') ?></td>
                                    <td class="text-end">Rp <?= number_format($r['realisasi'], 0, ',', '.') ?></td>
                                    <td class="text-end <?= $sisa < 0 ? 'fw-bold text-danger' : '' ?>">Rp <?= number_format($sisa, 0, ',', '.') ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('pagu/riwayat/' . $r['id']) ?>" class="btn btn-sm btn-outline-info" title="Riwayat"><i class="bi bi-clock-history"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">TOTAL</th>
                                <th class="text-end">Rp <?= number_format($totAwal, 0, ',', '.') ?></th>
                                <th class="text-end">Rp <?= number_format($totAkhir, 0, ',', '.') ?></th>
                                <th class="text-end">Rp <?= number_format($totSelisih, 0, ',', '.') ?></th>
                                <th class="text-end">Rp <?= number_format($totReal, 0, ',', '.') ?></th>
                                <th class="text-end">Rp <?= number_format($totSisa, 0, ',', '.') ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
