<?php
/**
 * View: Detail Pengajuan TU (Admin)
 * Variabel: $data (header + transaksis), $transaksiSiap
 */
$data          = $data          ?? [];
$transaksiSiap = $transaksiSiap ?? [];

$h        = $data;
$transaksis = $h['transaksis'] ?? [];

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$namaBulanMap = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                 7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$statusBadge = [
    'diajukan'  => ['warning', 'Menunggu Persetujuan'],
    'disetujui' => ['info',    'Disetujui'],
    'cair'      => ['primary', 'SP2D Cair'],
    'selesai'   => ['success', 'Selesai'],
    'ditolak'   => ['danger',  'Ditolak'],
];
$stInfo = $statusBadge[$h['status'] ?? ''] ?? ['secondary', $h['status']];

$nominalCair    = (float) ($h['nominal_cair']    ?? 0);
$nominalTerpakai = (float) ($h['nominal_terpakai'] ?? 0);
$sisa           = max(0, $nominalCair - $nominalTerpakai);
$jatuhTempo     = $h['status'] === 'cair' && !empty($h['batas_spj']) && $h['batas_spj'] < date('Y-m-d');

function fmtRp2($v): string { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }
?>

<div class="container-fluid py-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($jatuhTempo): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Batas waktu SPJ-TU sudah terlewat!</strong>
            Batas SPJ: <?= date('d/m/Y', strtotime($h['batas_spj'])) ?>.
            Segera selesaikan dan setorkan sisa dana ke kas.
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('pengajuan-tu') ?>">Tambah Uang (TU)</a></li>
            <li class="breadcrumb-item active">Detail</li>
        </ol>
    </nav>

    <!-- Header info -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($h['nomor_pengajuan']) ?></h5>
                    <span class="badge bg-<?= $stInfo[0] ?> mb-2"><?= $stInfo[1] ?></span>
                    <p class="mb-0 text-muted" style="font-size:.875rem;"><?= htmlspecialchars($h['keperluan']) ?></p>
                </div>
                <!-- Aksi tombol sesuai status -->
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($h['status'] === 'diajukan'): ?>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalSetujui"><i class="bi bi-check-circle me-1"></i>Setujui</button>
                        <button class="btn btn-danger btn-sm"  data-bs-toggle="modal" data-bs-target="#modalTolak"><i class="bi bi-x-circle me-1"></i>Tolak</button>
                    <?php elseif ($h['status'] === 'disetujui'): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCairkan"><i class="bi bi-cash-coin me-1"></i>Cairkan SP2D-TU</button>
                    <?php elseif ($h['status'] === 'cair'): ?>
                        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalSelesaikan"><i class="bi bi-check2-all me-1"></i>Selesaikan TU</button>
                    <?php endif; ?>
                    <a href="<?= base_url('pengajuan-tu') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
                </div>
            </div>

            <hr class="my-3">

            <div class="row g-3" style="font-size:.875rem;">
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Seksi</div><div class="fw-semibold"><?= htmlspecialchars($h['nama_seksi'] ?? '-') ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Periode</div><div><?= ($namaBulanMap[$h['bulan']] ?? $h['bulan']) . ' ' . $h['tahun'] ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Tgl Pengajuan</div><div><?= date('d/m/Y', strtotime($h['tanggal_pengajuan'])) ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Kebutuhan TU</div><div class="fw-bold text-primary"><?= fmtRp2($h['kebutuhan']) ?></div></div>
                <?php if ($nominalCair > 0): ?>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Nomor SP2D-TU</div><div style="font-family:monospace;"><?= htmlspecialchars($h['nomor_sp2d'] ?? '-') ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Tgl Cair</div><div><?= $h['tanggal_cair'] ? date('d/m/Y', strtotime($h['tanggal_cair'])) : '-' ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Nominal Cair</div><div class="fw-bold"><?= fmtRp2($nominalCair) ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Batas SPJ</div><div class="<?= $jatuhTempo ? 'text-danger fw-bold' : '' ?>"><?= $h['batas_spj'] ? date('d/m/Y', strtotime($h['batas_spj'])) : '-' ?></div></div>
                <?php endif; ?>
                <?php if (!empty($h['catatan_admin'])): ?>
                <div class="col-12"><div class="text-muted mb-1" style="font-size:.72rem;">Catatan Admin</div><div><?= htmlspecialchars($h['catatan_admin']) ?></div></div>
                <?php endif; ?>
            </div>

            <?php if ($nominalCair > 0): ?>
            <!-- Meter serapan -->
            <div class="mt-3">
                <?php $pct = $nominalCair > 0 ? min(100, round($nominalTerpakai / $nominalCair * 100)) : 0; ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                    <span class="text-muted">Dana terpakai: <strong><?= fmtRp2($nominalTerpakai) ?></strong> dari <?= fmtRp2($nominalCair) ?></span>
                    <span class="fw-semibold <?= $sisa < 0.01 ? 'text-success' : 'text-warning' ?>">Sisa: <?= fmtRp2($sisa) ?></span>
                </div>
                <div class="progress" style="height:10px;">
                    <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%" role="progressbar"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaksi Terkait -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt me-2 text-primary"></i>Transaksi dari Dana TU ini</span>
            <?php if ($h['status'] === 'cair' && !empty($transaksiSiap)): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalKaitkan">
                    <i class="bi bi-link-45deg me-1"></i>Kaitkan Transaksi
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($transaksis)): ?>
                <div class="text-center py-4 text-muted" style="font-size:.875rem;">
                    <i class="bi bi-receipt-cutoff" style="font-size:1.8rem;"></i>
                    <p class="mt-2 mb-0">Belum ada transaksi yang dikaitkan ke TU ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size:.85rem;">
                        <thead class="table-light">
                            <tr><th>Tanggal</th><th>Rekening</th><th>Uraian</th><th class="text-end">Nilai (Rp)</th><th class="text-center">Status</th><th class="text-center">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php $totalVerif = 0; foreach ($transaksis as $t):
                                if ($t['status'] === 'diverifikasi') $totalVerif += (float)$t['nilai'];
                                $stB = ['diajukan'=>'warning','diverifikasi'=>'success','ditolak'=>'danger'][$t['status']] ?? 'secondary';
                            ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?= date('d/m/Y', strtotime($t['tanggal_lunas_dibayar'] ?? $t['tanggal'])) ?></td>
                                    <td style="font-size:.78rem;"><?= htmlspecialchars($t['kode_rekening'] ?? '') ?><div class="text-muted"><?= htmlspecialchars($t['nama_sub_kegiatan'] ?? '') ?></div></td>
                                    <td><?= htmlspecialchars($t['uraian']) ?><?php if ($t['nama_penerima']): ?><div class="text-muted" style="font-size:.78rem;">a.n. <?= htmlspecialchars($t['nama_penerima']) ?></div><?php endif; ?></td>
                                    <td class="text-end fw-semibold"><?= number_format((float)$t['nilai'], 0, ',', '.') ?></td>
                                    <td class="text-center"><span class="badge bg-<?= $stB ?>"><?= ['diajukan'=>'Menunggu','diverifikasi'=>'Terverifikasi','ditolak'=>'Ditolak'][$t['status']] ?? $t['status'] ?></span></td>
                                    <td class="text-center">
                                        <?php if (in_array($h['status'], ['cair'], true)): ?>
                                            <form method="POST" action="<?= base_url('pengajuan-tu/lepas/' . $h['id'] . '/' . $t['id']) ?>" style="display:inline;" onsubmit="return confirm('Lepas transaksi ini dari TU?')">
                                                <button type="submit" class="btn btn-xs btn-outline-danger" style="padding:.15rem .4rem;font-size:.75rem;" title="Lepas dari TU"><i class="bi bi-unlink"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total Terverifikasi:</td>
                                <td class="text-end fw-bold text-success"><?= number_format($totalVerif, 0, ',', '.') ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($h['status'] === 'selesai'): ?>
    <!-- Ringkasan Penyelesaian -->
    <div class="card border-success border-0 shadow-sm mb-4">
        <div class="card-header bg-success text-white fw-bold"><i class="bi bi-check2-all me-2"></i>Ringkasan Penyelesaian TU</div>
        <div class="card-body">
            <div class="row g-3 text-center" style="font-size:.9rem;">
                <div class="col-md-4">
                    <div class="text-muted mb-1">Nominal Cair</div>
                    <div class="fw-bold"><?= fmtRp2($nominalCair) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted mb-1">Dana Digunakan (SPJ)</div>
                    <div class="fw-bold text-primary"><?= fmtRp2($h['nominal_digunakan'] ?? 0) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted mb-1">Sisa Disetor ke Kas</div>
                    <div class="fw-bold <?= ((float)($h['nominal_disetor'] ?? 0)) > 0 ? 'text-success' : 'text-muted' ?>">
                        <?= fmtRp2($h['nominal_disetor'] ?? 0) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Setujui -->
<div class="modal fade" id="modalSetujui" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="<?= base_url('pengajuan-tu/setujui/' . $h['id']) ?>">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-check-circle text-success me-2"></i>Setujui Pengajuan TU</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Setujui pengajuan TU <strong><?= htmlspecialchars($h['nomor_pengajuan']) ?></strong>?</p>
                <div><label class="form-label">Catatan (opsional)</label><textarea name="catatan_admin" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Setujui</button></div>
        </form>
    </div>
</div>

<!-- Modal Tolak -->
<div class="modal fade" id="modalTolak" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="<?= base_url('pengajuan-tu/tolak/' . $h['id']) ?>">
            <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Pengajuan TU</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Catatan Penolakan <span class="text-danger">*</span></label>
                <textarea name="catatan_admin" class="form-control" rows="3" required></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Tolak</button></div>
        </form>
    </div>
</div>

<!-- Modal Cairkan SP2D-TU -->
<div class="modal fade" id="modalCairkan" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="<?= base_url('pengajuan-tu/cairkan/' . $h['id']) ?>">
            <div class="modal-header"><h5 class="modal-title text-primary"><i class="bi bi-cash-coin me-2"></i>Cairkan SP2D-TU</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nomor SP2D-TU <span class="text-danger">*</span></label>
                    <input type="text" name="nomor_sp2d" class="form-control" placeholder="Contoh: SP2D-TU-001/2026" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tanggal Cair <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_cair" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nominal SP2D (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="nominal_cair" id="nominalCairInput" class="form-control"
                               placeholder="<?= number_format((float)$h['kebutuhan'], 0, ',', '.') ?>"
                               value="<?= number_format((float)$h['kebutuhan'], 0, ',', '.') ?>"
                               required inputmode="numeric">
                    </div>
                    <small class="text-muted">Kebutuhan diajukan: <?= fmtRp2($h['kebutuhan']) ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Batas Waktu SPJ</label>
                    <input type="date" name="batas_spj" class="form-control" id="batasSpjInput">
                    <small class="text-muted">Kosongkan = otomatis 30 hari dari tanggal cair.</small>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Cairkan & Catat ke Kas</button></div>
        </form>
    </div>
</div>

<!-- Modal Selesaikan TU -->
<div class="modal fade" id="modalSelesaikan" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="<?= base_url('pengajuan-tu/selesaikan/' . $h['id']) ?>">
            <div class="modal-header"><h5 class="modal-title text-success"><i class="bi bi-check2-all me-2"></i>Selesaikan Siklus TU</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-info py-2" style="font-size:.875rem;">
                    <strong>Ringkasan:</strong><br>
                    Dana Cair: <strong><?= fmtRp2($nominalCair) ?></strong><br>
                    Terpakai (diverifikasi): <strong><?= fmtRp2($nominalTerpakai) ?></strong><br>
                    Sisa yang akan disetor: <strong class="text-<?= $sisa > 0 ? 'danger' : 'success' ?>"><?= fmtRp2($sisa) ?></strong>
                </div>
                <?php if ($sisa > 0): ?>
                    <p class="text-danger" style="font-size:.875rem;"><i class="bi bi-info-circle me-1"></i>Sisa Rp <?= number_format($sisa, 0, ',', '.') ?> akan otomatis dicatat sebagai setoran ke kas.</p>
                <?php else: ?>
                    <p class="text-success" style="font-size:.875rem;"><i class="bi bi-check me-1"></i>Semua dana TU telah digunakan. Tidak ada sisa yang perlu disetor.</p>
                <?php endif; ?>
                <div><label class="form-label">Catatan (opsional)</label><textarea name="catatan_admin" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Selesaikan TU</button></div>
        </form>
    </div>
</div>

<!-- Modal Kaitkan Transaksi -->
<?php if ($h['status'] === 'cair' && !empty($transaksiSiap)): ?>
<div class="modal fade" id="modalKaitkan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="<?= base_url('pengajuan-tu/kaitkan/' . $h['id']) ?>">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>Kaitkan Transaksi ke TU ini</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" style="max-height:400px;overflow-y:auto;">
                <p class="text-muted" style="font-size:.875rem;">Pilih transaksi yang dibiayai dari dana TU ini (sudah terverifikasi, belum dikaitkan ke GU atau TU manapun):</p>
                <table class="table table-sm align-middle" style="font-size:.85rem;">
                    <thead class="table-light"><tr><th><input type="checkbox" id="checkAll"></th><th>Tanggal</th><th>Uraian</th><th class="text-end">Nilai (Rp)</th></tr></thead>
                    <tbody>
                        <?php foreach ($transaksiSiap as $ts): ?>
                            <tr>
                                <td><input type="checkbox" name="transaksi_ids[]" value="<?= $ts['id'] ?>"></td>
                                <td><?= date('d/m/Y', strtotime($ts['tanggal_lunas_dibayar'] ?? $ts['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($ts['uraian']) ?><div class="text-muted"><?= htmlspecialchars($ts['nama_rekening'] ?? '') ?></div></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$ts['nilai'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Kaitkan yang Dipilih</button></div>
        </form>
    </div>
</div>
<script>
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="transaksi_ids[]"]').forEach(cb => cb.checked = this.checked);
});
</script>
<?php endif; ?>

<script>
// Auto-hitung batas SPJ (+30 hari dari tanggal cair)
const tglCairInput = document.querySelector('input[name="tanggal_cair"]');
const batasInput   = document.getElementById('batasSpjInput');
if (tglCairInput && batasInput) {
    tglCairInput.addEventListener('change', function() {
        if (!this.value) return;
        const d = new Date(this.value);
        d.setDate(d.getDate() + 30);
        batasInput.value = d.toISOString().split('T')[0];
    });
    // Trigger sekali untuk set default
    if (tglCairInput.value) tglCairInput.dispatchEvent(new Event('change'));
}
// Format ribuan input nominal
const nominalInput = document.getElementById('nominalCairInput');
if (nominalInput) {
    nominalInput.addEventListener('blur', function() {
        const v = parseInt(this.value.replace(/[^0-9]/g, ''), 10);
        if (!isNaN(v)) this.value = v.toLocaleString('id-ID');
    });
    nominalInput.addEventListener('focus', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    nominalInput.form?.addEventListener('submit', function() {
        nominalInput.value = nominalInput.value.replace(/[^0-9]/g, '');
    });
}
</script>
