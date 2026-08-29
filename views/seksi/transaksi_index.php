<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-weight:700;color:#0f172a;"><i class="bi bi-receipt me-2 text-primary"></i>Transaksi Saya</h2>
        <p class="text-muted mb-0">Daftar transaksi yang diinput seksi Anda. Menunggu verifikasi admin sebelum masuk realisasi.</p>
    </div>
    <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Tambah Transaksi
    </a>
</div>

<div class="bsa-card">
    <div class="table-responsive p-3">
        <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
            <thead class="table-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Program / Kegiatan / Sub Kegiatan</th>
                    <th>Rekening</th>
                    <th>Uraian</th>
                    <th class="text-end">Nilai</th>
                    <th>Nomor Bukti</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transaksis)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                <?php else: foreach ($transaksis as $t): ?>
                    <?php
                        $status = $t['status'] ?? 'diverifikasi';
                        $badge = match ($status) {
                            'diajukan'     => ['Menunggu Verifikasi', 'warning'],
                            'diverifikasi' => ['Terverifikasi', 'success'],
                            'ditolak'      => ['Ditolak', 'danger'],
                            default        => [ucfirst($status), 'secondary'],
                        };
                        $bolehEdit = $status === 'diajukan';
                    ?>
                    <tr>
                        <td><?= date('d-m-Y', strtotime($t['tanggal'])) ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($t['kode_program'] . ' - ' . $t['nama_program']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($t['kode_kegiatan'] . ' - ' . $t['nama_kegiatan']) ?><br><?= htmlspecialchars($t['kode_sub_kegiatan'] . ' - ' . $t['nama_sub_kegiatan']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($t['kode_rekening'] . ' - ' . $t['nama_rekening']) ?></td>
                        <td><?= htmlspecialchars($t['uraian']) ?></td>
                        <td class="text-end fw-semibold">Rp <?= number_format($t['nilai'], 0, ',', '.') ?></td>
                        <td><?= htmlspecialchars($t['nomor_bukti']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badge[1] ?>"><?= $badge[0] ?></span>
                            <?php if ($status === 'ditolak' && !empty($t['catatan_verifikasi'])): ?>
                                <div class="small text-danger mt-1" data-bs-toggle="tooltip" title="<?= htmlspecialchars($t['catatan_verifikasi']) ?>">
                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars($t['catatan_verifikasi']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($bolehEdit): ?>
                                <a href="<?= base_url('seksi/transaksi/edit/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="<?= base_url('seksi/transaksi/delete/' . $t['id']) ?>" style="display:inline;" onsubmit="return confirm('Hapus transaksi ini?')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
