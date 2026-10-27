<?php
/**
 * View: Detail Pengajuan TU (Admin/Bendahara)
 * Variabel: $data (header + transaksis), $programs
 */
$data     = $data     ?? [];
$programs = $programs ?? [];

$h          = $data;
$transaksis = $h['transaksis'] ?? [];

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$namaBulanMap = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                 7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$statusBadge = [
    'diajukan'  => ['warning', 'Menunggu Cair'],
    'disetujui' => ['info',    'Disetujui (lama)'],
    'cair'      => ['primary', 'SP2D Cair'],
    'selesai'   => ['success', 'Selesai'],
    'ditolak'   => ['danger',  'Ditolak'],
];
$stInfo = $statusBadge[$h['status'] ?? ''] ?? ['secondary', $h['status']];

$nominalCair     = (float) ($h['nominal_cair']    ?? 0);
$nominalTerpakai = (float) ($h['nominal_terpakai'] ?? 0);
$sisa            = max(0, $nominalCair - $nominalTerpakai);
$jatuhTempo      = $h['status'] === 'cair' && !empty($h['batas_spj']) && $h['batas_spj'] < date('Y-m-d');

$selectedProgramId  = $h['program_id']      ?? '';
$selectedKegiatanId = $h['kegiatan_id']     ?? '';
$selectedSubKegId   = $h['sub_kegiatan_id'] ?? '';
$selectedRekeningId = $h['rekening_id']     ?? '';

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
                        <a href="<?= base_url('pengajuan-tu/edit/' . $h['id']) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                        <form method="POST" action="<?= base_url('pengajuan-tu/delete/' . $h['id']) ?>" onsubmit="return confirm('Hapus pengajuan TU ini?')">
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus</button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($h['status'], ['diajukan', 'disetujui'], true)): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCairkan"><i class="bi bi-cash-coin me-1"></i>Cairkan SP2D-TU</button>
                    <?php elseif ($h['status'] === 'cair'): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBayar"><i class="bi bi-wallet2 me-1"></i>Catat Pembayaran</button>
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
                <?php if (!empty($h['kode_rekening'])): ?>
                <div class="col-12"><div class="text-muted mb-1" style="font-size:.72rem;">Rekening</div><div><?= htmlspecialchars($h['kode_rekening'] . ' – ' . ($h['nama_rekening'] ?? '')) ?></div></div>
                <?php endif; ?>
                <?php if ($nominalCair > 0): ?>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Nomor SP2D-TU</div><div style="font-family:monospace;"><?= htmlspecialchars($h['nomor_sp2d'] ?? '-') ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Tgl Cair</div><div><?= $h['tanggal_cair'] ? date('d/m/Y', strtotime($h['tanggal_cair'])) : '-' ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Nominal Cair</div><div class="fw-bold"><?= fmtRp2($nominalCair) ?></div></div>
                <div class="col-md-3 col-6"><div class="text-muted mb-1" style="font-size:.72rem;">Batas SPJ</div><div class="<?= $jatuhTempo ? 'text-danger fw-bold' : '' ?>"><?= $h['batas_spj'] ? date('d/m/Y', strtotime($h['batas_spj'])) : '-' ?></div></div>
                <?php endif; ?>
                <?php if (!empty($h['catatan_admin'])): ?>
                <div class="col-12"><div class="text-muted mb-1" style="font-size:.72rem;">Catatan</div><div><?= htmlspecialchars($h['catatan_admin']) ?></div></div>
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

    <!-- Pembayaran / Realisasi -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt me-2 text-primary"></i>Pembayaran dari Dana TU ini</span>
            <?php if ($h['status'] === 'cair'): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalBayar">
                    <i class="bi bi-plus-circle me-1"></i>Catat Pembayaran
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($transaksis)): ?>
                <div class="text-center py-4 text-muted" style="font-size:.875rem;">
                    <i class="bi bi-receipt-cutoff" style="font-size:1.8rem;"></i>
                    <p class="mt-2 mb-0">Belum ada pembayaran dari dana TU ini.</p>
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
                                        <?php if ($h['status'] === 'cair'): ?>
                                            <form method="POST" action="<?= base_url('pengajuan-tu/hapus-pembayaran/' . $h['id'] . '/' . $t['id']) ?>" style="display:inline;" onsubmit="return confirm('Hapus pembayaran ini dari TU?')">
                                                <button type="submit" class="btn btn-outline-danger" style="padding:.15rem .4rem;font-size:.75rem;" title="Hapus pembayaran"><i class="bi bi-trash"></i></button>
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
                <div class="col-md-3">
                    <div class="text-muted mb-1">Nominal Cair</div>
                    <div class="fw-bold"><?= fmtRp2($nominalCair) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted mb-1">Dana Digunakan (Realisasi)</div>
                    <div class="fw-bold text-primary"><?= fmtRp2($h['nominal_digunakan'] ?? 0) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted mb-1">Sisa Disetor ke Kas</div>
                    <div class="fw-bold <?= ((float)($h['nominal_disetor'] ?? 0)) > 0 ? 'text-success' : 'text-muted' ?>">
                        <?= fmtRp2($h['nominal_disetor'] ?? 0) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted mb-1">Selisih</div>
                    <div class="fw-bold"><?= fmtRp2(max(0, $nominalCair - (float)($h['nominal_digunakan'] ?? 0) - (float)($h['nominal_disetor'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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

<!-- Modal Catat Pembayaran -->
<?php if ($h['status'] === 'cair'): ?>
<div class="modal fade" id="modalBayar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="<?= base_url('pengajuan-tu/bayar/' . $h['id']) ?>" id="formBayar">
            <div class="modal-header"><h5 class="modal-title text-primary"><i class="bi bi-wallet2 me-2"></i>Catat Pembayaran dari Dana TU</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-info py-2" style="font-size:.82rem;">
                    Sisa dana TU yang bisa dibayarkan: <strong><?= fmtRp2($sisa) ?></strong>
                </div>
                <?php
                $qsEndpoint = base_url('transaksi/cari-klasifikasi');
                $qsProgram  = 'bayar_program_id';
                $qsKegiatan = 'bayar_kegiatan_id';
                $qsSub      = 'bayar_sub_kegiatan_id';
                $qsRekening = 'bayar_rekening_id';
                $qsTitle    = 'Cari Sub Kegiatan / Rekening';
                include __DIR__ . '/../shared/_klasifikasi_quick_search.php';
                ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Tanggal Bayar <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Jenis Transaksi</label>
                        <select name="jenis_transaksi" class="form-select">
                            <option value="belanja">Belanja</option>
                            <option value="perjalanan_dinas">Perjalanan Dinas</option>
                            <option value="honorarium">Honorarium</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Nomor Bukti</label>
                        <input type="text" name="nomor_bukti" class="form-control" placeholder="Contoh: 001/TU/2026">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Program</label>
                        <select id="bayar_program_id" class="form-select">
                            <option value="">-- Pilih Program --</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $selectedProgramId == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['kode_program'] . ' – ' . $p['nama_program']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Kegiatan</label>
                        <select id="bayar_kegiatan_id" class="form-select" disabled>
                            <option value="">-- Pilih Kegiatan --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Sub Kegiatan</label>
                        <select id="bayar_sub_kegiatan_id" class="form-select" disabled>
                            <option value="">-- Pilih Sub Kegiatan --</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Rekening Belanja <span class="text-danger">*</span></label>
                        <select name="rekening_id" id="bayar_rekening_id" class="form-select" disabled required>
                            <option value="">-- Pilih Rekening --</option>
                        </select>
                        <small class="text-muted">Boleh memakai rekening dari seksi mana pun; transaksi mengikuti seksi rekening yang dipilih.</small>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Uraian Pembayaran <span class="text-danger">*</span></label>
                        <input type="text" name="uraian" class="form-control" placeholder="Contoh: Belanja ATK bulan Oktober" required maxlength="500">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Nama Penerima</label>
                        <input type="text" name="nama_penerima" class="form-control" placeholder="Nama toko / penerima" maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Nilai (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="nilai" id="bayarNilaiInput" class="form-control" placeholder="0" required inputmode="numeric">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Pembayaran</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal Selesaikan TU -->
<?php if ($h['status'] === 'cair'): ?>
<div class="modal fade" id="modalSelesaikan" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="<?= base_url('pengajuan-tu/selesaikan/' . $h['id']) ?>">
            <div class="modal-header"><h5 class="modal-title text-success"><i class="bi bi-check2-all me-2"></i>Selesaikan Siklus TU</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-info py-2" style="font-size:.875rem;">
                    <strong>Ringkasan:</strong><br>
                    Dana Cair: <strong><?= fmtRp2($nominalCair) ?></strong><br>
                    Terpakai (diverifikasi): <strong><?= fmtRp2($nominalTerpakai) ?></strong><br>
                    Sisa dana: <strong class="text-<?= $sisa > 0 ? 'danger' : 'success' ?>"><?= fmtRp2($sisa) ?></strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nominal Setoran Sisa (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="nominal_setor" id="nominalSetorInput" class="form-control"
                                   value="<?= number_format($sisa, 0, ',', '.') ?>" inputmode="numeric">
                        </div>
                        <small class="text-muted">Boleh disesuaikan dengan jumlah yang benar-benar disetor.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tanggal Setoran</label>
                        <input type="date" name="tanggal_setoran" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Nomor Bukti Setoran</label>
                        <input type="text" name="nomor_bukti_setoran" class="form-control" placeholder="Opsional">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea name="catatan_admin" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <p class="text-muted mt-2 mb-0" style="font-size:.8rem;">
                    Setoran otomatis dicatat sebagai penerimaan kas (jenis setoran) sesuai nominal dan tanggal di atas.
                </p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Selesaikan TU</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ── Format ribuan input nominal ────────────────────────────────────────
function bindRibuan(inputId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    el.addEventListener('blur', function() {
        const v = parseInt(this.value.replace(/[^0-9]/g, ''), 10);
        if (!isNaN(v)) this.value = v.toLocaleString('id-ID');
    });
    el.addEventListener('focus', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    el.form?.addEventListener('submit', function() {
        el.value = el.value.replace(/[^0-9]/g, '');
    });
}
bindRibuan('nominalCairInput');
bindRibuan('bayarNilaiInput');
bindRibuan('nominalSetorInput');

// ── Auto-hitung batas SPJ (+30 hari dari tanggal cair) ─────────────────
const tglCairInput = document.querySelector('#modalCairkan input[name="tanggal_cair"]');
const batasInput   = document.getElementById('batasSpjInput');
if (tglCairInput && batasInput) {
    tglCairInput.addEventListener('change', function() {
        if (!this.value) return;
        const d = new Date(this.value);
        d.setDate(d.getDate() + 30);
        batasInput.value = d.toISOString().split('T')[0];
    });
    if (tglCairInput.value) tglCairInput.dispatchEvent(new Event('change'));
}

<?php if ($h['status'] === 'cair'): ?>
// ── Cascade pembayaran: Program → Kegiatan → Sub Kegiatan → Rekening ───
const BASE = (typeof BASE_URL !== 'undefined' ? BASE_URL.replace(/\/$/, '') : '');

function bayarLoadOptions(selectEl, url, keepValue, placeholder) {
    selectEl.innerHTML = `<option value="">Memuat…</option>`;
    selectEl.disabled  = true;
    return fetch(url)
        .then(r => r.json())
        .then(data => {
            let html = `<option value="">${placeholder}</option>`;
            data.forEach(d => {
                let label = d.kode_rekening
                    ? d.kode_rekening + ' – ' + (d.nama_rekening || '')
                    : d.kode_sub_kegiatan
                        ? d.kode_sub_kegiatan + ' – ' + (d.nama_sub_kegiatan || '')
                        : d.kode_kegiatan
                            ? d.kode_kegiatan + ' – ' + (d.nama_kegiatan || '')
                            : '';
                html += `<option value="${d.id}">${label}</option>`;
            });
            selectEl.innerHTML = html;
            selectEl.disabled  = false;
            if (keepValue) selectEl.value = keepValue;
            return data;
        })
        .catch(() => {
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            selectEl.disabled  = false;
        });
}

const byProgram  = document.getElementById('bayar_program_id');
const byKegiatan = document.getElementById('bayar_kegiatan_id');
const bySubKeg   = document.getElementById('bayar_sub_kegiatan_id');
const byRekening = document.getElementById('bayar_rekening_id');

if (byProgram) {
    byProgram.addEventListener('change', function () {
        byKegiatan.innerHTML = '<option value="">-- Pilih Kegiatan --</option>'; byKegiatan.disabled = true;
        bySubKeg.innerHTML   = '<option value="">-- Pilih Sub Kegiatan --</option>'; bySubKeg.disabled = true;
        byRekening.innerHTML = '<option value="">-- Pilih Rekening --</option>'; byRekening.disabled = true;
        if (!this.value) return;
        bayarLoadOptions(byKegiatan, `${BASE}/pagu/get-kegiatans?program_id=${this.value}`, '', '-- Pilih Kegiatan --');
    });

    byKegiatan.addEventListener('change', function () {
        bySubKeg.innerHTML   = '<option value="">-- Pilih Sub Kegiatan --</option>'; bySubKeg.disabled = true;
        byRekening.innerHTML = '<option value="">-- Pilih Rekening --</option>'; byRekening.disabled = true;
        if (!this.value) return;
        bayarLoadOptions(bySubKeg, `${BASE}/pagu/get-sub-kegiatans?kegiatan_id=${this.value}`, '', '-- Pilih Sub Kegiatan --');
    });

    bySubKeg.addEventListener('change', function () {
        byRekening.innerHTML = '<option value="">-- Pilih Rekening --</option>'; byRekening.disabled = true;
        if (!this.value) return;
        bayarLoadOptions(byRekening, `${BASE}/pagu/get-rekenings?sub_kegiatan_id=${this.value}`, '', '-- Pilih Rekening --');
    });

    // Prefill dari rekening TU
    (function () {
        const savedProgram  = <?= json_encode((string) $selectedProgramId) ?>;
        const savedKegiatan = <?= json_encode((string) $selectedKegiatanId) ?>;
        const savedSub      = <?= json_encode((string) $selectedSubKegId) ?>;
        const savedRekening = <?= json_encode((string) $selectedRekeningId) ?>;

        if (!savedProgram) return;
        byProgram.value = savedProgram;
        bayarLoadOptions(byKegiatan, `${BASE}/pagu/get-kegiatans?program_id=${savedProgram}`, savedKegiatan, '-- Pilih Kegiatan --')
        .then(() => {
            if (!savedKegiatan) return;
            return bayarLoadOptions(bySubKeg, `${BASE}/pagu/get-sub-kegiatans?kegiatan_id=${savedKegiatan}`, savedSub, '-- Pilih Sub Kegiatan --');
        })
        .then(() => {
            if (!savedSub) return;
            return bayarLoadOptions(byRekening, `${BASE}/pagu/get-rekenings?sub_kegiatan_id=${savedSub}`, savedRekening, '-- Pilih Rekening --');
        });
    })();
}
<?php endif; ?>
</script>
