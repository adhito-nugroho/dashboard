<?php
/**
 * View: Detail Transaksi (Admin — Read Only)
 * Variabel dari controller: $transaksi, $rincianBiaya (null jika belum ada)
 */
$transaksi    = $transaksi    ?? [];
$rincianBiaya = $rincianBiaya ?? null;   // ['header' => [...], 'details' => [...]]

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$statusLabels = [
    'diajukan'     => ['Menunggu Verifikasi', 'warning'],
    'diverifikasi' => ['Terverifikasi',        'success'],
    'ditolak'      => ['Ditolak',              'danger'],
];
$st     = $transaksi['status'] ?? 'diajukan';
$stInfo = $statusLabels[$st] ?? [ucfirst($st), 'secondary'];

function fmtRp($v): string { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }
?>

<div class="container-fluid py-4" style="max-width:920px;">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('transaksi') ?>"><i class="bi bi-receipt me-1"></i>Transaksi</a></li>
            <li class="breadcrumb-item active">Detail Transaksi #<?= $transaksi['id'] ?? '' ?></li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><i class="bi bi-receipt text-primary me-2"></i>Detail Transaksi</h4>
        <div class="d-flex gap-2">
            <?php if ($st === 'diajukan'): ?>
                <button type="button" class="btn btn-success btn-sm btn-verifikasi-show" data-id="<?= $transaksi['id'] ?>">
                    <i class="bi bi-check-lg me-1"></i>Verifikasi
                </button>
                <button type="button" class="btn btn-danger btn-sm btn-tolak-show" data-id="<?= $transaksi['id'] ?>">
                    <i class="bi bi-x-lg me-1"></i>Tolak
                </button>
            <?php endif; ?>
            <a href="<?= base_url('transaksi') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>
    </div>

    <!-- Info Transaksi -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Tanggal</div>
                    <div class="fw-semibold"><?= $transaksi['tanggal'] ? date('d/m/Y', strtotime($transaksi['tanggal'])) : '-' ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Status</div>
                    <span class="badge bg-<?= $stInfo[1] ?>"><?= $stInfo[0] ?></span>
                    <?php if ($st === 'ditolak' && !empty($transaksi['catatan_verifikasi'])): ?>
                        <div class="text-danger small mt-1"><?= htmlspecialchars($transaksi['catatan_verifikasi']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Seksi</div>
                    <div><?= htmlspecialchars($transaksi['nama_seksi'] ?? '-') ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Nilai</div>
                    <div class="fw-bold text-primary"><?= fmtRp($transaksi['nilai'] ?? 0) ?></div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Nomor Bukti</div>
                    <div style="font-family:monospace;font-size:.875rem;"><?= htmlspecialchars($transaksi['nomor_bukti'] ?? '-') ?></div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Rekening</div>
                    <div style="font-size:.85rem;"><?= htmlspecialchars(($transaksi['kode_rekening'] ?? '') . ' ' . ($transaksi['nama_rekening'] ?? '')) ?></div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Jenis</div>
                    <div style="font-size:.85rem;"><?= htmlspecialchars(str_replace('_', ' ', $transaksi['jenis_transaksi'] ?? '-')) ?></div>
                </div>
                <div class="col-12">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Uraian</div>
                    <div style="font-size:.9rem;white-space:pre-wrap;"><?= htmlspecialchars($transaksi['uraian'] ?? '-') ?></div>
                </div>
                <?php if (!empty($transaksi['nama_penerima'])): ?>
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Nama Penerima</div>
                    <div><?= htmlspecialchars($transaksi['nama_penerima']) ?>
                        <?php if (!empty($transaksi['pegawai_nip'])): ?>
                            <span class="text-muted small"> · NIP <?= htmlspecialchars($transaksi['pegawai_nip']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($transaksi['nomor_surat_tugas'])): ?>
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">Surat Tugas</div>
                    <div style="font-size:.85rem;"><?= htmlspecialchars($transaksi['nomor_surat_tugas']) ?>
                        <?php if (!empty($transaksi['tanggal_pelaksanaan'])): ?>
                            <span class="text-muted small">· Pelaksanaan: <?= date('d/m/Y', strtotime($transaksi['tanggal_pelaksanaan'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Rincian Biaya SPJ (read-only) -->
    <div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #4f46e5 !important;">
        <div class="card-header bg-white fw-bold" style="font-size:.9rem;">
            <i class="bi bi-receipt-cutoff text-primary me-2"></i>Rincian Biaya Perjalanan Dinas
            <span class="badge bg-light text-muted fw-normal ms-2" style="font-size:.72rem;">Read-only</span>
        </div>
        <div class="card-body">
        <?php if (!$rincianBiaya): ?>
            <div class="text-center py-3 text-muted">
                <i class="bi bi-hourglass" style="font-size:1.5rem;"></i>
                <p class="mt-2 mb-0" style="font-size:.875rem;">Rincian biaya belum diisi oleh seksi.</p>
            </div>
        <?php else:
            $h = $rincianBiaya['header'];
            $details = $rincianBiaya['details'];
            $ditetapkan = (float) ($h['ditetapkan_sejumlah'] ?? 0);
            $dibayar    = (float) ($h['dibayar_semula']      ?? 0);
            $sisa       = $ditetapkan - $dibayar;
        ?>
            <!-- Info pegawai -->
            <div class="mb-3 p-2 bg-light rounded" style="font-size:.85rem;">
                <strong><?= htmlspecialchars($h['pegawai_nama']) ?></strong>
                <span class="text-muted ms-2">NIP <?= htmlspecialchars($h['pegawai_nip']) ?></span>
                <?php if (!empty($h['pegawai_pangkat'])): ?>
                    <span class="text-muted ms-2">· <?= htmlspecialchars($h['pegawai_pangkat']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Tabel komponen -->
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle mb-0" style="font-size:.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th width="4%" class="text-center">No</th>
                            <th width="25%">Nama Komponen</th>
                            <th width="18%" class="text-end">Harga Satuan (Rp)</th>
                            <th width="8%" class="text-center">Hari</th>
                            <th width="18%" class="text-end">Jumlah (Rp)</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $totalKomponen = 0; foreach ($details as $no => $d): $totalKomponen += (float)($d['jumlah'] ?? 0); ?>
                        <tr>
                            <td class="text-center text-muted"><?= $no + 1 ?></td>
                            <td><?= htmlspecialchars($d['nama_komponen']) ?></td>
                            <td class="text-end"><?= number_format((float)($d['harga_satuan'] ?? 0), 0, ',', '.') ?></td>
                            <td class="text-center"><?= $d['jumlah_hari'] !== null ? (float)$d['jumlah_hari'] : '—' ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float)($d['jumlah'] ?? 0), 0, ',', '.') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($d['keterangan'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold text-success"><?= number_format($totalKomponen, 0, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- SPPD Rampung -->
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded text-center" style="font-size:.8rem;">
                        <div class="text-muted mb-1">Ditetapkan Sejumlah</div>
                        <div class="fw-bold"><?= fmtRp($ditetapkan) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded text-center" style="font-size:.8rem;">
                        <div class="text-muted mb-1">Yang Telah Dibayar Semula</div>
                        <div class="fw-bold"><?= fmtRp($dibayar) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 rounded text-center <?= $sisa < 0 ? 'bg-danger-subtle' : ($sisa > 0 ? 'bg-success-subtle' : 'bg-primary-subtle') ?>" style="font-size:.8rem;">
                        <div class="text-muted mb-1">Sisa Kurang / Lebih</div>
                        <div class="fw-bold <?= $sisa < 0 ? 'text-danger' : ($sisa > 0 ? 'text-success' : 'text-primary') ?>">
                            <?= ($sisa < 0 ? '–' : ($sisa > 0 ? '+' : '')) . ' ' . fmtRp(abs($sisa)) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($h['tempat_tanggal'])): ?>
            <div class="text-end text-muted" style="font-size:.8rem;font-style:italic;"><?= htmlspecialchars($h['tempat_tanggal']) ?></div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal Tolak -->
<div class="modal fade" id="modalTolakShow" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="formTolakShow" method="POST">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Tolak Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Catatan Penolakan <span class="text-danger">*</span></label>
                <textarea name="catatan_verifikasi" class="form-control" rows="3" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Transaksi</button>
            </div>
        </form>
    </div>
</div>

<script>
const SHOW_BASE = '<?= rtrim(base_url(), '/') ?>';
document.querySelector('.btn-verifikasi-show')?.addEventListener('click', function() {
    if (confirm('Verifikasi transaksi ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = SHOW_BASE + '/transaksi/verifikasi/' + this.dataset.id;
        document.body.appendChild(form);
        form.submit();
    }
});
const modalEl = document.getElementById('modalTolakShow');
document.querySelector('.btn-tolak-show')?.addEventListener('click', function() {
    document.getElementById('formTolakShow').action = SHOW_BASE + '/transaksi/tolak/' + this.dataset.id;
    new bootstrap.Modal(modalEl).show();
});
</script>
