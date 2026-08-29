<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 style="font-weight:800;color:#0f172a;letter-spacing:-0.02em;margin-bottom:0.25rem;">
            <i class="bi bi-receipt me-2 text-primary"></i>Transaksi Saya
        </h3>
        <p class="text-muted mb-0" style="font-size:0.875rem;">Daftar transaksi anggaran yang diajukan oleh seksi Anda.</p>
    </div>
    <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary px-3 py-2 fw-semibold" style="border-radius:8px;box-shadow:0 2px 6px rgba(37,99,235,0.25);">
        <i class="bi bi-plus-circle me-1"></i> Tambah Transaksi
    </a>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
    <div class="card-body p-0">
        <?php if (empty($transaksis)): ?>
            <!-- EMPTY STATE ENHANCED -->
            <div class="text-center py-5 px-3">
                <div style="width:72px;height:72px;border-radius:50%;background:#eff6ff;color:#3b82f6;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:1rem;">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Belum Ada Transaksi yang Diajukan</h5>
                <p class="text-muted mx-auto mb-4" style="max-width:420px;font-size:0.875rem;">
                    Seksi Anda belum pernah mengajukan transaksi belanja. Klik tombol di bawah untuk mulai menginput transaksi baru.
                </p>
                <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary px-4 py-2 fw-semibold" style="border-radius:8px;">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Transaksi Sekarang
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
                    <thead class="table-light text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">
                        <tr>
                            <th class="ps-3 py-3" style="width:10%;">Tanggal</th>
                            <th style="width:18%;">Sub Kegiatan</th>
                            <th style="width:14%;">Rekening</th>
                            <th style="width:24%;">Uraian & Penerima</th>
                            <th class="text-end" style="width:14%;">Nilai (Rp)</th>
                            <th class="text-center" style="width:10%;">Status</th>
                            <th class="text-center pe-3" style="width:10%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaksis as $t): ?>
                            <?php
                                $status = $t['status'] ?? 'diverifikasi';
                                $badge = match ($status) {
                                    'diajukan'     => ['Menunggu Verifikasi', 'warning', '#fefce8', '#854d0e', '#fef08a'],
                                    'diverifikasi' => ['Terverifikasi', 'success', '#f0fdf4', '#166534', '#bbf7d0'],
                                    'ditolak'      => ['Ditolak', 'danger', '#fef2f2', '#991b1b', '#fecaca'],
                                    default        => [ucfirst($status), 'secondary', '#f1f5f9', '#475569', '#e2e8f0'],
                                };
                                $bolehEdit = $status === 'diajukan';

                                $jenisLabel = match ($t['jenis_transaksi'] ?? '') {
                                    'perjalanan_dinas' => 'Perjadin',
                                    'belanja'          => 'Belanja',
                                    'honorarium'       => 'Honor',
                                    default            => '',
                                };
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($t['tanggal'])) ?></div>
                                    <small class="text-muted font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($t['nomor_bukti'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark" style="font-size:0.825rem;"><?= htmlspecialchars($t['nama_sub_kegiatan'] ?? '-') ?></div>
                                    <small class="text-muted font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($t['kode_sub_kegiatan'] ?? '') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border" style="font-weight:600;font-size:0.775rem;">
                                        <?= htmlspecialchars($t['kode_rekening'] ?? '') ?>
                                    </span>
                                    <div class="small text-muted text-truncate mt-1" style="max-width:180px;" title="<?= htmlspecialchars($t['nama_rekening'] ?? '') ?>">
                                        <?= htmlspecialchars($t['nama_rekening'] ?? '') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($jenisLabel): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1" style="font-size:0.7rem;padding:0.2rem 0.4rem;">
                                            <?= $jenisLabel ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-dark"><?= htmlspecialchars($t['uraian']) ?></span>
                                    <?php if (!empty($t['nama_penerima'])): ?>
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-person-fill text-secondary me-1"></i><?= htmlspecialchars($t['nama_penerima']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($t['nomor_surat_tugas'])): ?>
                                        <div class="small text-muted" style="font-size:0.75rem;">
                                            <i class="bi bi-file-earmark-text me-1"></i>ST: <?= htmlspecialchars($t['nomor_surat_tugas']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp <?= number_format($t['nilai'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span style="font-size:0.75rem;font-weight:700;padding:0.3rem 0.65rem;border-radius:999px;background:<?= $badge[2] ?>;color:<?= $badge[3] ?>;border:1px solid <?= $badge[4] ?>;white-space:nowrap;">
                                        <?= $badge[0] ?>
                                    </span>
                                    <?php if ($status === 'ditolak' && !empty($t['catatan_verifikasi'])): ?>
                                        <div class="small text-danger mt-1 text-truncate" style="max-width:140px;margin:auto;" data-bs-toggle="tooltip" title="<?= htmlspecialchars($t['catatan_verifikasi']) ?>">
                                            <i class="bi bi-exclamation-circle-fill me-1"></i><?= htmlspecialchars($t['catatan_verifikasi']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-3">
                                    <?php if ($bolehEdit): ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('seksi/transaksi/edit/' . $t['id']) ?>" class="btn btn-outline-primary" title="Edit Transaksi">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="<?= base_url('seksi/transaksi/delete/' . $t['id']) ?>" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                                <button type="submit" class="btn btn-outline-danger" title="Hapus Transaksi">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.8rem;"><i class="bi bi-lock-fill"></i> Terkunci</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
