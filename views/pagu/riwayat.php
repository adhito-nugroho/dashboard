<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
$selisihTotal = ((float) ($pagu['nilai_pagu'] ?? 0)) - ((float) ($nilaiAwal ?? $pagu['nilai_pagu']));
?>
<div class="container-fluid py-4">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-clock-history text-info me-2"></i>Riwayat PAPBD</h2>
            <p class="text-muted mb-0">
                <?= htmlspecialchars(($pagu['kode_rekening'] ?? '') . ' - ' . ($pagu['nama_rekening'] ?? '')) ?>
                &middot; Tahun <?= htmlspecialchars($pagu['tahun'] ?? '') ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('pagu/laporan?tahun=' . (int) ($pagu['tahun'] ?? date('Y'))) ?>" class="btn btn-outline-info">
                <i class="bi bi-clipboard-data me-1"></i> Laporan Selisih
            </a>
            <a href="<?= base_url('pagu') ?>" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <small class="text-muted text-uppercase">APBD Awal</small>
                <h5 class="mb-0">Rp <?= number_format($nilaiAwal, 0, ',', '.') ?></h5>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary"><div class="card-body">
                <small class="text-muted text-uppercase">PAPBD (Saat Ini)</small>
                <h5 class="mb-0 text-primary">Rp <?= number_format($pagu['nilai_pagu'], 0, ',', '.') ?></h5>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <small class="text-muted text-uppercase">Selisih</small>
                <h5 class="mb-0 <?= $selisihTotal > 0 ? 'text-success' : ($selisihTotal < 0 ? 'text-danger' : '') ?>">
                    <?= $selisihTotal > 0 ? '+' : '' ?>Rp <?= number_format($selisihTotal, 0, ',', '.') ?>
                </h5>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <small class="text-muted text-uppercase">Realisasi / Sisa</small>
                <h6 class="mb-0">Rp <?= number_format($realisasi ?? 0, 0, ',', '.') ?> / Rp <?= number_format(((float) $pagu['nilai_pagu']) - ((float) ($realisasi ?? 0)), 0, ',', '.') ?></h6>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($riwayat)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2 mb-0">Belum ada perubahan. Nilai saat ini adalah APBD awal.</p>
                    <a href="<?= base_url('pagu/edit/' . $pagu['id']) ?>" class="btn btn-primary btn-sm mt-3">
                        <i class="bi bi-pencil me-1"></i> Ubah Pagu (PAPBD)
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th class="text-end">Sebelum</th>
                                <th class="text-end">Sesudah</th>
                                <th class="text-end">Selisih</th>
                                <th>Keterangan</th>
                                <th>Diubah Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $i => $r): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($r['jenis'] ?? 'PAPBD') ?></span></td>
                                    <td class="text-end">Rp <?= number_format($r['nilai_sebelum'], 0, ',', '.') ?></td>
                                    <td class="text-end"><strong>Rp <?= number_format($r['nilai_sesudah'], 0, ',', '.') ?></strong></td>
                                    <td class="text-end">
                                        <?php $s = (float) $r['selisih']; ?>
                                        <span class="<?= $s > 0 ? 'text-success' : ($s < 0 ? 'text-danger' : 'text-muted') ?>">
                                            <?= $s > 0 ? '+' : '' ?>Rp <?= number_format($s, 0, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($r['diubah_oleh'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
