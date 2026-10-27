<?php
/**
 * View: Form Pengajuan TU (Seksi)
 * Variabel: $form (array data form / data existing), $errors (array)
 */
$form   = $form   ?? [];
$errors = $errors ?? [];
$isEdit = !empty($form['id']);

$formAction = $isEdit
    ? base_url('seksi/tu/update/' . $form['id'])
    : base_url('seksi/tu/store');

$namaBulanMap = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                 7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

// Nilai form
$tanggalPengajuan = $form['tanggal_pengajuan'] ?? date('Y-m-d');
$bulan            = $form['bulan']    ?? (int) date('n');
$tahun            = $form['tahun']    ?? (int) date('Y');
$kebutuhan        = $form['kebutuhan'] ?? '';
$keperluan        = $form['keperluan'] ?? '';

// Format kebutuhan untuk tampil
$kebutuhanFmt = $kebutuhan ? number_format((float) $kebutuhan, 0, ',', '.') : '';
?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-plus-circle-dotted text-primary me-2"></i>
                    <?= $isEdit ? 'Edit Pengajuan TU' : 'Ajukan Tambah Uang (TU)' ?>
                </h4>
                <p class="text-muted mb-0" style="font-size:.875rem;">Isi form berikut untuk mengajukan kebutuhan Tambah Uang Persediaan.</p>
            </div>
            <a href="<?= base_url('seksi/tu') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <!-- Error -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 mb-3">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Mohon perbaiki:</div>
                <ul class="mb-0 ps-3" style="font-size:.875rem;">
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="<?= $formAction ?>" id="tuForm">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-f8fafc fw-semibold" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Informasi Pengajuan
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Tanggal Pengajuan <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pengajuan" class="form-control" value="<?= htmlspecialchars($tanggalPengajuan) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Bulan Kebutuhan <span class="text-danger">*</span></label>
                            <select name="bulan" class="form-select" <?= $isEdit ? 'disabled' : '' ?> required>
                                <?php foreach ($namaBulanMap as $m => $nm): ?>
                                    <option value="<?= $m ?>" <?= $bulan == $m ? 'selected' : '' ?>><?= $nm ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEdit): ?><input type="hidden" name="bulan" value="<?= $bulan ?>"><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Tahun <span class="text-danger">*</span></label>
                            <select name="tahun" class="form-select" <?= $isEdit ? 'disabled' : '' ?> required>
                                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <?php if ($isEdit): ?><input type="hidden" name="tahun" value="<?= $tahun ?>"><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-f8fafc fw-semibold" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;">
                    <i class="bi bi-cash-coin me-2 text-primary"></i>Kebutuhan Dana & Keperluan
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Kebutuhan TU (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">Rp</span>
                                <input type="text" name="kebutuhan" id="kebutuhanInput" class="form-control"
                                       placeholder="Contoh: 5.000.000"
                                       value="<?= htmlspecialchars($kebutuhanFmt) ?>"
                                       required inputmode="numeric">
                            </div>
                            <small class="text-muted">Masukkan estimasi total kebutuhan belanja bulan ini.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Keperluan / Uraian Kegiatan <span class="text-danger">*</span></label>
                            <textarea name="keperluan" class="form-control" rows="4"
                                      placeholder="Contoh: Perjalanan dinas dalam rangka monitoring kegiatan lapangan bulan Oktober 2026, pembayaran honorarium narasumber, dan belanja ATK seksi RLPM"
                                      required maxlength="500"><?= htmlspecialchars($keperluan) ?></textarea>
                            <small class="text-muted">Uraian lengkap kegiatan yang akan dibiayai dari TU ini. Maks 500 karakter.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info -->
            <div class="alert alert-info py-2" style="font-size:.82rem;">
                <i class="bi bi-info-circle me-1"></i>
                Pengajuan TU akan diteruskan ke admin/bendahara untuk persetujuan dan pencairan SP2D-TU.
                Setelah dana cair, gunakan untuk membiayai kegiatan dan input transaksi masing-masing.
                Sisa dana <strong>wajib disetor kembali</strong> ke kas setelah batas waktu SPJ.
            </div>

            <!-- Action bar -->
            <div style="position:sticky;bottom:0;z-index:1020;background:#fff;border-top:1px solid #e2e8f0;padding:.85rem 0;margin-top:1rem;">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= base_url('seksi/tu') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send me-1"></i><?= $isEdit ? 'Perbarui Pengajuan' : 'Ajukan TU' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Format ribuan input kebutuhan
const kebutuhanInput = document.getElementById('kebutuhanInput');
if (kebutuhanInput) {
    kebutuhanInput.addEventListener('blur', function() {
        const v = parseInt(this.value.replace(/[^0-9]/g, ''), 10);
        if (!isNaN(v) && v > 0) this.value = v.toLocaleString('id-ID');
        else this.value = '';
    });
    kebutuhanInput.addEventListener('focus', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    document.getElementById('tuForm').addEventListener('submit', function() {
        kebutuhanInput.value = kebutuhanInput.value.replace(/[^0-9]/g, '');
    });
}
</script>
