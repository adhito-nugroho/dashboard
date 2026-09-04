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
        <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-<?= $stInfo[1] ?> px-2.5 py-1.5 fs-7"><?= $stInfo[0] ?></span>
                    <span class="text-muted" style="font-size:.8rem;">No. Bukti: <strong class="text-dark font-monospace"><?= htmlspecialchars($transaksi['nomor_bukti'] ?? '-') ?></strong></span>
                </div>
                <div class="text-end">
                    <span class="text-muted small">Nilai Transaksi:</span>
                    <span class="fw-bold text-primary fs-5 ms-1"><?= fmtRp($transaksi['nilai'] ?? 0) ?></span>
                </div>
            </div>
            <?php if ($st === 'ditolak' && !empty($transaksi['catatan_verifikasi'])): ?>
                <div class="alert alert-danger py-2 px-3 mt-2 mb-0 d-flex align-items-center gap-2" style="font-size:.85rem;">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                    <div>
                        <strong>Alasan Penolakan:</strong> <?= htmlspecialchars($transaksi['catatan_verifikasi']) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body p-4">
            <!-- Grid Metadata Utama -->
            <div class="row g-3 mb-4 pb-3 border-bottom">
                <div class="col-md-3 col-6">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em;">Tanggal Transaksi</div>
                    <div class="fw-semibold text-dark mt-1">
                        <i class="bi bi-calendar3 me-1 text-muted"></i>
                        <?= $transaksi['tanggal'] ? date('d/m/Y', strtotime($transaksi['tanggal'])) : '-' ?>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em;">Seksi Pengusul</div>
                    <div class="fw-semibold text-dark mt-1">
                        <i class="bi bi-building me-1 text-muted"></i>
                        <?= htmlspecialchars($transaksi['nama_seksi'] ?? '-') ?>
                        <?php if (!empty($transaksi['kode_seksi'])): ?>
                            <span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size:.7rem;"><?= htmlspecialchars($transaksi['kode_seksi']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em;">Jenis Transaksi</div>
                    <div class="mt-1">
                        <?php
                            $jenisRaw = $transaksi['jenis_transaksi'] ?? 'lainnya';
                            $jenisBadge = match($jenisRaw) {
                                'perjalanan_dinas' => ['Perjalanan Dinas', 'bg-primary-subtle text-primary border border-primary-subtle'],
                                'belanja'          => ['Belanja', 'bg-info-subtle text-info-emphasis border border-info-subtle'],
                                'honorarium'       => ['Honorarium', 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
                                default            => [ucfirst(str_replace('_', ' ', $jenisRaw)), 'bg-secondary-subtle text-secondary border']
                            };
                        ?>
                        <span class="badge <?= $jenisBadge[1] ?> px-2 py-1" style="font-size:.75rem;">
                            <?= $jenisBadge[0] ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em;">Status Verifikasi</div>
                    <div class="mt-1">
                        <span class="badge bg-<?= $stInfo[1] ?> px-2 py-1" style="font-size:.75rem;"><?= $stInfo[0] ?></span>
                    </div>
                </div>
            </div>

            <!-- Klasifikasi Anggaran -->
            <div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-diagram-3-fill text-primary"></i>
                    <h6 class="fw-bold mb-0 text-dark">Klasifikasi Anggaran</h6>
                </div>
                <div class="bg-light p-3 rounded-3 border">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Program</div>
                            <div class="text-dark fw-medium mt-0.5" style="font-size:.85rem;">
                                <?php if (!empty($transaksi['kode_program'])): ?>
                                    <span class="badge bg-secondary font-monospace me-1" style="font-size:.72rem;"><?= htmlspecialchars($transaksi['kode_program']) ?></span>
                                    <?= htmlspecialchars($transaksi['nama_program'] ?? '-') ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Kegiatan</div>
                            <div class="text-dark fw-medium mt-0.5" style="font-size:.85rem;">
                                <?php if (!empty($transaksi['kode_kegiatan'])): ?>
                                    <span class="badge bg-secondary font-monospace me-1" style="font-size:.72rem;"><?= htmlspecialchars($transaksi['kode_kegiatan']) ?></span>
                                    <?= htmlspecialchars($transaksi['nama_kegiatan'] ?? '-') ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12 pt-2 border-top">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Sub Kegiatan</div>
                            <div class="text-dark fw-semibold mt-0.5" style="font-size:.875rem;">
                                <?php if (!empty($transaksi['kode_sub_kegiatan'])): ?>
                                    <span class="badge bg-success font-monospace me-1" style="font-size:.75rem;"><?= htmlspecialchars($transaksi['kode_sub_kegiatan']) ?></span>
                                    <span class="text-dark"><?= htmlspecialchars($transaksi['nama_sub_kegiatan'] ?? '-') ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12 pt-2 border-top">
                            <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Rekening Belanja</div>
                            <div class="text-dark fw-semibold mt-0.5" style="font-size:.875rem;">
                                <?php if (!empty($transaksi['kode_rekening'])): ?>
                                    <span class="badge bg-primary font-monospace me-1" style="font-size:.75rem;"><?= htmlspecialchars($transaksi['kode_rekening']) ?></span>
                                    <span class="text-dark"><?= htmlspecialchars($transaksi['nama_rekening'] ?? '-') ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Uraian Transaksi -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.05em;">Uraian Transaksi</div>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted" id="btnCopyUraian" style="font-size:.75rem;" title="Salin Uraian">
                        <i class="bi bi-clipboard me-1"></i><span id="btnCopyUraianText">Salin</span>
                    </button>
                </div>
                <div class="p-3 bg-light rounded-3 border" style="font-size:.875rem;white-space:pre-wrap;line-height:1.6;" id="uraianContent"><?= htmlspecialchars($transaksi['uraian'] ?? '-') ?></div>
            </div>

            <!-- Informasi Surat Tugas & Penerima (Jika ada) -->
            <?php 
                $hasPenerima = !empty($transaksi['nama_penerima']);
                $hasST = !empty($transaksi['nomor_surat_tugas']);
                $hasTglPelaksanaan = !empty($transaksi['tanggal_pelaksanaan']);
                $hasLokasi = !empty($transaksi['lokasi_kegiatan']);
            ?>
            <?php if ($hasPenerima || $hasST || $hasTglPelaksanaan || $hasLokasi): ?>
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-person-badge text-primary"></i>
                    <h6 class="fw-bold mb-0 text-dark">Informasi Pelaksanaan & Penerima</h6>
                </div>
                <div class="row g-3 p-3 bg-light rounded-3 border">
                    <?php if ($hasPenerima): ?>
                    <div class="col-md-6 col-12">
                        <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Nama Penerima</div>
                        <div class="fw-semibold text-dark mt-0.5" style="font-size:.875rem;">
                            <?= htmlspecialchars($transaksi['nama_penerima']) ?>
                            <?php if (!empty($transaksi['pegawai_nip'])): ?>
                                <span class="text-muted fw-normal small d-block font-monospace mt-0.5">NIP: <?= htmlspecialchars($transaksi['pegawai_nip']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasST): ?>
                    <div class="col-md-6 col-12">
                        <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Surat Tugas</div>
                        <div class="fw-semibold text-dark font-monospace mt-0.5" style="font-size:.85rem;">
                            <?= htmlspecialchars($transaksi['nomor_surat_tugas']) ?>
                        </div>
                        <?php if (!empty($transaksi['tanggal_surat_tugas'])): ?>
                            <div class="text-muted small mt-0.5">Tgl ST: <?= date('d/m/Y', strtotime($transaksi['tanggal_surat_tugas'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasTglPelaksanaan): ?>
                    <div class="col-md-6 col-12">
                        <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Tanggal Pelaksanaan</div>
                        <div class="text-dark mt-0.5" style="font-size:.85rem;">
                            <i class="bi bi-calendar-event me-1 text-muted"></i>
                            <?= date('d/m/Y', strtotime($transaksi['tanggal_pelaksanaan'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasLokasi): ?>
                    <div class="col-md-6 col-12">
                        <div class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.05em;">Lokasi Kegiatan</div>
                        <div class="text-dark mt-0.5" style="font-size:.85rem;">
                            <i class="bi bi-geo-alt me-1 text-danger"></i>
                            <?= htmlspecialchars($transaksi['lokasi_kegiatan']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Rincian Biaya Perjalanan Dinas (jika transaksi perjalanan dinas atau memiliki rincian biaya) -->
    <?php 
        $isPerjadin = ($transaksi['jenis_transaksi'] ?? '') === 'perjalanan_dinas' || !empty($rincianBiaya);
    ?>
    <?php if ($isPerjadin): ?>
    <div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #4f46e5 !important;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3" style="font-size:.9rem;">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Rincian Komponen Biaya Perjalanan Dinas</span>
                <span class="badge bg-light text-muted border fw-normal" style="font-size:.72rem;">Read-only</span>
            </div>
            <?php if ($rincianBiaya): ?>
                <a href="<?= base_url('seksi/transaksi/unduh-rincian-biaya?transaksi_id=' . $transaksi['id']) ?>"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-4">
        <?php if (!$rincianBiaya || empty($rincianBiaya['details'])): ?>
            <div class="text-center py-4 text-muted">
                <div class="mb-2">
                    <i class="bi bi-info-circle text-secondary" style="font-size:2rem;"></i>
                </div>
                <h6 class="fw-semibold text-dark mb-1">Rincian Komponen Biaya Belum Tersedia</h6>
                <p class="mb-0 text-muted" style="font-size:.875rem;max-width:480px;margin:0 auto;">
                    Transaksi ini merupakan transaksi Perjalanan Dinas, namun rincian komponen biaya belum diisi oleh seksi pengusul.
                </p>
            </div>
        <?php else:
            $h = $rincianBiaya['header'];
            $details = $rincianBiaya['details'];
            $ditetapkan = (float) ($h['ditetapkan_sejumlah'] ?? 0);
            $dibayar    = (float) ($h['dibayar_semula']      ?? 0);
            $sisa       = $ditetapkan - $dibayar;
        ?>
            <!-- Info Pegawai Penerima SPPD -->
            <div class="mb-4 p-3 bg-light rounded-3 border" style="font-size:.85rem;">
                <div class="row g-2 align-items-center">
                    <div class="col-md-7 col-12">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle fs-5 text-primary"></i>
                            <div>
                                <strong class="text-dark fs-6"><?= htmlspecialchars($h['pegawai_nama'] ?? $transaksi['nama_penerima'] ?? 'Pegawai') ?></strong>
                                <div class="text-muted small">
                                    NIP: <span class="font-monospace"><?= htmlspecialchars($h['pegawai_nip'] ?? $transaksi['pegawai_nip'] ?? '-') ?></span>
                                    <?php if (!empty($h['pegawai_pangkat'])): ?>
                                        <span class="ms-2">· <?= htmlspecialchars($h['pegawai_pangkat']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($h['pegawai_jabatan'])): ?>
                                        <span class="ms-2">· <?= htmlspecialchars($h['pegawai_jabatan']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 col-12 text-md-end">
                        <div class="text-muted small">Surat Tugas:</div>
                        <span class="badge bg-secondary-subtle text-secondary border font-monospace"><?= htmlspecialchars($h['nomor_surat'] ?? $transaksi['nomor_surat_tugas'] ?? '-') ?></span>
                    </div>
                </div>
            </div>

            <!-- Tabel Komponen Biaya -->
            <div class="table-responsive mb-4 rounded-3 border">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size:.85rem;">
                    <thead class="table-light text-muted" style="font-size:.75rem;letter-spacing:.03em;text-transform:uppercase;">
                        <tr>
                            <th width="5%" class="text-center py-2">No</th>
                            <th width="32%" class="py-2">Nama Komponen Biaya</th>
                            <th width="18%" class="text-end py-2">Harga Satuan (Rp)</th>
                            <th width="10%" class="text-center py-2">Hari / Vol</th>
                            <th width="18%" class="text-end py-2">Jumlah Total (Rp)</th>
                            <th width="17%" class="py-2">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            $totalKomponen = 0; 
                            foreach ($details as $no => $d): 
                                $jml = (float)($d['jumlah'] ?? 0);
                                $totalKomponen += $jml; 
                        ?>
                        <tr>
                            <td class="text-center text-muted fw-semibold"><?= $no + 1 ?></td>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($d['nama_komponen']) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($d['harga_satuan'] ?? 0), 0, ',', '.') ?></td>
                            <td class="text-center"><?= $d['jumlah_hari'] !== null && $d['jumlah_hari'] !== '' ? (float)$d['jumlah_hari'] : '—' ?></td>
                            <td class="text-end fw-bold text-dark font-monospace"><?= number_format($jml, 0, ',', '.') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($d['keterangan'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="4" class="text-end fw-bold text-uppercase" style="font-size:.8rem;">Total Komponen Biaya</td>
                            <td class="text-end fw-bold text-success font-monospace fs-6"><?= number_format($totalKomponen, 0, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- SPPD Rampung Summary Cards -->
            <div class="row g-3 mb-3">
                <div class="col-md-4 col-12">
                    <div class="p-3 bg-light rounded-3 border text-center">
                        <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.04em;">Ditetapkan Sejumlah</div>
                        <div class="fw-bold text-dark fs-6"><?= fmtRp($ditetapkan > 0 ? $ditetapkan : $totalKomponen) ?></div>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="p-3 bg-light rounded-3 border text-center">
                        <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.04em;">Yang Telah Dibayar Semula</div>
                        <div class="fw-bold text-dark fs-6"><?= fmtRp($dibayar > 0 ? $dibayar : $totalKomponen) ?></div>
                    </div>
                </div>
                <div class="col-md-4 col-12">
                    <?php 
                        $sisaVal = ($ditetapkan > 0 ? $ditetapkan : $totalKomponen) - ($dibayar > 0 ? $dibayar : $totalKomponen);
                    ?>
                    <div class="p-3 rounded-3 border text-center <?= $sisaVal < 0 ? 'bg-danger-subtle border-danger-subtle' : ($sisaVal > 0 ? 'bg-success-subtle border-success-subtle' : 'bg-primary-subtle border-primary-subtle') ?>">
                        <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.04em;">Sisa Kurang / Lebih</div>
                        <div class="fw-bold fs-6 <?= $sisaVal < 0 ? 'text-danger' : ($sisaVal > 0 ? 'text-success' : 'text-primary') ?>">
                            <?= ($sisaVal < 0 ? '–' : ($sisaVal > 0 ? '+' : '')) . ' ' . fmtRp(abs($sisaVal)) ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($h['tempat_tanggal'])): ?>
            <div class="text-end text-muted small fst-italic">
                <i class="bi bi-pen me-1"></i><?= htmlspecialchars($h['tempat_tanggal']) ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

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

// Copy uraian button handler
document.getElementById('btnCopyUraian')?.addEventListener('click', function() {
    const text = document.getElementById('uraianContent')?.innerText || '';
    if (!text || text === '-') return;
    navigator.clipboard.writeText(text).then(() => {
        const textSpan = document.getElementById('btnCopyUraianText');
        const origText = textSpan.innerText;
        textSpan.innerText = 'Tersalin!';
        this.classList.add('text-success');
        setTimeout(() => {
            textSpan.innerText = origText;
            this.classList.remove('text-success');
        }, 1500);
    });
});
</script>
