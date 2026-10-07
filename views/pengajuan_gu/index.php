<?php
$namaBulanMap = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$flash = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
$statusBadge = [
    'diajukan' => ['Menunggu Cair', 'bg-warning text-dark border-warning'],
    'cair'     => ['Sudah Cair', 'bg-success-subtle text-success border-success-subtle'],
    'ditolak'  => ['Ditolak', 'bg-danger-subtle text-danger border-danger-subtle'],
];
?>
<div class="container-fluid px-4 py-3">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-send-check text-primary me-2"></i>Pengajuan SPJ ke GU
            </h1>
            <p class="text-muted small mb-0">
                Pilih sendiri transaksi yang belum di-SPJ-kan (bisa banyak sekaligus) untuk dimintakan Ganti Uang ke Kasda.
                Nominal GU otomatis = jumlah transaksi terpilih.
            </p>
        </div>
        <form method="GET" action="<?= base_url('pengajuan-gu') ?>" class="d-flex align-items-center gap-2 bg-white p-1 rounded-3 border shadow-sm">
            <select name="bulan" class="form-select form-select-sm border-0 bg-light" style="width: auto;" onchange="this.form.submit()">
                <option value="">Semua bulan</option>
                <?php foreach ($namaBulanMap as $m => $nama): ?>
                    <option value="<?= $m ?>" <?= $bulan === $m ? 'selected' : '' ?>><?= $nama ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tahun" class="form-select form-select-sm border-0 bg-light" style="width: auto;" onchange="this.form.submit()">
                <?php for ($y = date('Y') + 1; $y >= 2024; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $tahun ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="alert alert-light border shadow-sm rounded-3 py-2 px-3 mb-4 d-flex align-items-center gap-2 flex-wrap" style="font-size:0.85rem;">
        <i class="bi bi-info-circle-fill text-primary"></i>
        <span>Total belum di-SPJ-kan (semua waktu): <strong class="font-monospace">Rp <?= number_format($totalBelumSpj, 0, ',', '.') ?></strong></span>
        <span class="text-muted">·</span>
        <span>Siap diajukan pada filter ini: <strong class="font-monospace"><?= count($siapList) ?> transaksi (Rp <?= number_format($totalSiap, 0, ',', '.') ?>)</strong></span>
    </div>

    <!-- Daftar siap diajukan -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="card-title fw-bold mb-0 text-dark">
                    <i class="bi bi-list-check text-success me-2"></i>Transaksi Siap di-SPJ-kan
                </h5>
                <small class="text-muted">Hanya yang terverifikasi, dana UP, dan belum tercakup pengajuan mana pun</small>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 font-monospace" id="badgeTerpilih">
                Terpilih: 0 (Rp 0)
            </span>
        </div>
        <form method="POST" action="<?= base_url('pengajuan-gu/store') ?>" id="formAjukanSpj">
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 480px;">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3 text-center" style="width:40px;"><input type="checkbox" class="form-check-input" id="checkSemua" title="Pilih semua"></th>
                                <th>Tgl Lunas / No. Bukti</th>
                                <th>Uraian & Seksi</th>
                                <th class="text-end pe-3">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($siapList)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="bi bi-check2-all fs-3 d-block mb-1 text-success opacity-50"></i>
                                        Semua transaksi sudah di-SPJ-kan. Tidak ada yang perlu diajukan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($siapList as $t): ?>
                                    <tr>
                                        <td class="ps-3 text-center">
                                            <input type="checkbox" class="form-check-input cek-spj" name="ids[]" value="<?= $t['id'] ?>" data-nilai="<?= (float) $t['nilai'] ?>">
                                        </td>
                                        <td>
                                            <div class="fw-bold font-monospace text-dark" style="font-size:0.78rem;"><?= htmlspecialchars($t['nomor_bukti'] ?: '-') ?></div>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($t['tanggal_efektif'] ?? $t['tanggal'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="text-truncate text-dark fw-medium" style="max-width: 320px;" title="<?= htmlspecialchars($t['uraian']) ?>">
                                                <?= htmlspecialchars($t['uraian']) ?>
                                            </div>
                                            <small class="badge bg-light text-secondary border px-1.5 py-0.5" style="font-size:0.65rem;">
                                                <?= htmlspecialchars($t['nama_seksi'] ?? '-') ?>
                                            </small>
                                        </td>
                                        <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                            Rp <?= number_format((float) $t['nilai'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($siapList)): ?>
                <div class="card-footer bg-light py-3 px-4">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-dark mb-1">Tanggal Pengajuan <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pengajuan" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark mb-1">Keterangan</label>
                            <input type="text" name="keterangan" class="form-control" placeholder="Contoh: SPJ Periode 1–15 Oktober 2026" maxlength="255">
                        </div>
                        <div class="col-md-3 text-md-end">
                            <button type="submit" class="btn btn-success fw-semibold w-100" onclick="return confirmAjukanSpj()">
                                <i class="bi bi-send-check me-1"></i>Ajukan SPJ Terpilih
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Riwayat pengajuan -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h5 class="card-title fw-bold mb-0 text-dark">
                <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pengajuan GU
            </h5>
            <small class="text-muted">Setiap pengajuan otomatis tercatat sebagai GU menunggu cair di Kas & Bank</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nomor / Tanggal</th>
                            <th>Keterangan</th>
                            <th class="text-end">Nominal</th>
                            <th>Status</th>
                            <th class="text-center pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada pengajuan GU.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat as $p): ?>
                                <?php
                                $pid = (int) $p['id'];
                                $st = $p['status'] ?? 'diajukan';
                                $bInfo = $statusBadge[$st] ?? ['-', 'bg-light text-dark'];
                                $items = $itemsMap[$pid] ?? [];
                                ?>
                                <tr class="<?= $st === 'diajukan' ? 'table-warning bg-opacity-25' : '' ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold font-monospace text-dark"><?= htmlspecialchars($p['nomor_pengajuan']) ?></div>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($p['tanggal_pengajuan'])) ?> · <?= count($items) ?> transaksi</small>
                                    </td>
                                    <td class="text-dark"><?= htmlspecialchars($p['keterangan'] ?: '-') ?></td>
                                    <td class="text-end font-monospace fw-bold <?= $st === 'diajukan' ? 'text-warning-emphasis' : 'text-success' ?>">
                                        Rp <?= number_format((float) $p['total_nominal'], 0, ',', '.') ?>
                                    </td>
                                    <td><span class="badge <?= $bInfo[1] ?> border px-2 py-1"><?= $bInfo[0] ?></span></td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <?php if ($st === 'diajukan'): ?>
                                                <form method="POST" action="<?= base_url('pengajuan-gu/cairkan/' . $pid) ?>" class="d-flex gap-1" onsubmit="return confirm('Tandai pengajuan ini sudah cair (SP2D masuk kas)?')">
                                                    <input type="hidden" name="tanggal_cair" value="<?= date('Y-m-d') ?>">
                                                    <button type="submit" class="btn btn-sm btn-success fw-semibold" title="Tandai sudah cair">
                                                        <i class="bi bi-check-lg me-1"></i>Cair
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?= base_url('pengajuan-gu/batalkan/' . $pid) ?>" onsubmit="return confirm('Batalkan pengajuan ini? Transaksi kembali siap di-SPJ-kan.')">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan pengajuan">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="ps-4 pe-4 pb-3 pt-0 bg-light bg-opacity-50">
                                        <details>
                                            <summary class="small text-primary fw-semibold" style="cursor:pointer;">
                                                Rincian <?= count($items) ?> transaksi tercakup
                                            </summary>
                                            <div class="table-responsive mt-2">
                                                <table class="table table-sm table-bordered bg-white mb-0" style="font-size:0.78rem;">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Tgl</th>
                                                            <th>No. Bukti</th>
                                                            <th>Uraian</th>
                                                            <th class="text-end">Nilai</th>
                                                            <?php if ($st === 'diajukan'): ?><th class="text-center" style="width:60px;">Lepas</th><?php endif; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($items as $it): ?>
                                                            <tr>
                                                                <td class="font-monospace"><?= date('d/m/Y', strtotime($it['tanggal_efektif'] ?? $it['tanggal'])) ?></td>
                                                                <td class="font-monospace"><?= htmlspecialchars($it['nomor_bukti'] ?: '-') ?></td>
                                                                <td><?= htmlspecialchars(mb_strimwidth($it['uraian'] ?? '', 0, 80, '…')) ?></td>
                                                                <td class="text-end font-monospace">Rp <?= number_format((float) $it['nilai'], 0, ',', '.') ?></td>
                                                                <?php if ($st === 'diajukan'): ?>
                                                                    <td class="text-center">
                                                                        <form method="POST" action="<?= base_url('pengajuan-gu/lepas/' . $pid . '/' . $it['id']) ?>" onsubmit="return confirm('Lepas transaksi ini dari pengajuan?')">
                                                                            <button type="submit" class="btn btn-xs btn-outline-warning py-0 px-2" title="Lepas dari pengajuan">
                                                                                <i class="bi bi-dash-circle"></i>
                                                                            </button>
                                                                        </form>
                                                                    </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cekSemua = document.getElementById('checkSemua');
    const badge = document.getElementById('badgeTerpilih');
    const boxes = () => document.querySelectorAll('.cek-spj');

    function hitungTerpilih() {
        let n = 0, total = 0;
        boxes().forEach(cb => {
            if (cb.checked) { n++; total += parseFloat(cb.dataset.nilai || 0); }
        });
        if (badge) badge.textContent = 'Terpilih: ' + n + ' (Rp ' + total.toLocaleString('id-ID') + ')';
        if (cekSemua && boxes().length > 0) {
            cekSemua.checked = n === boxes().length;
            cekSemua.indeterminate = n > 0 && n < boxes().length;
        }
        return { n, total };
    }

    cekSemua?.addEventListener('change', function() {
        boxes().forEach(cb => { cb.checked = cekSemua.checked; });
        hitungTerpilih();
    });
    boxes().forEach(cb => cb.addEventListener('change', hitungTerpilih));
    hitungTerpilih();

    window.confirmAjukanSpj = function() {
        const { n, total } = hitungTerpilih();
        if (n === 0) { alert('Pilih minimal satu transaksi.'); return false; }
        return confirm('Ajukan SPJ untuk ' + n + ' transaksi senilai Rp ' + total.toLocaleString('id-ID') + '?');
    };
});
</script>
