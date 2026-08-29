<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isEdit = isset($transaksi['id']) && !empty($transaksi['id']);
$tanggal = $transaksi['tanggal'] ?? date('Y-m-d');
$programId = $transaksi['program_id'] ?? '';
$kegiatanId = $transaksi['kegiatan_id'] ?? '';
$subKegiatanId = $transaksi['sub_kegiatan_id'] ?? '';
$rekeningId = $transaksi['rekening_id'] ?? '';
$uraian = $transaksi['uraian'] ?? '';
$nilai = $transaksi['nilai'] ?? '';
$nomorBukti = $transaksi['nomor_bukti'] ?? '';
$errors = $validationErrors ?? [];
$formAction = $isEdit ? base_url('seksi/transaksi/update/' . $transaksi['id']) : base_url('seksi/transaksi/store');
?>
<div class="row">
    <div class="col-lg-8">
        <div class="bsa-card">
            <div class="p-4">
                <h4 class="mb-1" style="font-weight:700;color:#0f172a;">
                    <i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-2 text-primary"></i>
                    <?= $isEdit ? 'Edit Transaksi' : 'Tambah Transaksi' ?>
                </h4>
                <p class="text-muted">Hanya rekening milik seksi Anda yang bisa dipilih. Transaksi diajukan untuk verifikasi admin.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2">
                        <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $formAction ?>" id="seksiTransaksiForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Program <span class="text-danger">*</span></label>
                            <select name="program_id" id="program_id" class="form-select" required>
                                <option value="">-- Pilih Program --</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $programId == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['kode_program'] . ' - ' . $p['nama_program']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kegiatan <span class="text-danger">*</span></label>
                            <select name="kegiatan_id" id="kegiatan_id" class="form-select" required disabled>
                                <option value="">-- Pilih Kegiatan --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sub Kegiatan <span class="text-danger">*</span></label>
                            <select name="sub_kegiatan_id" id="sub_kegiatan_id" class="form-select" required disabled>
                                <option value="">-- Pilih Sub Kegiatan --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rekening <span class="text-danger">*</span></label>
                            <select name="rekening_id" id="rekening_id" class="form-select" required disabled>
                                <option value="">-- Pilih Rekening --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Bukti <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_bukti" class="form-control" value="<?= htmlspecialchars($nomorBukti) ?>" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Uraian <span class="text-danger">*</span></label>
                            <textarea name="uraian" class="form-control" rows="2" required><?= htmlspecialchars($uraian) ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nilai (Rp) <span class="text-danger">*</span></label>
                            <input type="text" name="nilai" id="nilai" class="form-control" value="<?= htmlspecialchars($nilai) ?>" placeholder="Contoh: 5000000" required>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4"><?= $isEdit ? 'Simpan Perubahan' : 'Ajukan Transaksi' ?></button>
                        <a href="<?= base_url('seksi/transaksi') ?>" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Preload options saat edit
const selectedKegiatan = '<?= (int)$kegiatanId ?>';
const selectedSub = '<?= (int)$subKegiatanId ?>';
const selectedRekening = '<?= (int)$rekeningId ?>';
const seksiBase = BASE_URL.replace(/\/$/, '');

function loadOptions(selId, url, selectEl, keepValue) {
    return fetch(url).then(r => r.json()).then(data => {
        let opts = '<option value="">-- Pilih --</option>';
        data.forEach(d => opts += '<option value="' + d.id + '">' + (d.kode_rekening ? d.kode_rekening + ' - ' + (d.nama_rekening||'') : (d.kode_sub_kegiatan ? d.kode_sub_kegiatan + ' - ' + (d.nama_sub_kegiatan||'') : (d.kode_kegiatan ? d.kode_kegiatan + ' - ' + (d.nama_kegiatan||'') : ''))) + '</option>');
        selectEl.innerHTML = opts;
        selectEl.disabled = false;
        if (keepValue) selectEl.value = keepValue;
        return data;
    });
}

document.getElementById('program_id').addEventListener('change', function() {
    const programId = this.value;
    const keg = document.getElementById('kegiatan_id');
    const sub = document.getElementById('sub_kegiatan_id');
    const rek = document.getElementById('rekening_id');
    keg.innerHTML = '<option value="">-- Pilih Kegiatan --</option>'; keg.disabled = true;
    sub.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>'; sub.disabled = true;
    rek.innerHTML = '<option value="">-- Pilih Rekening --</option>'; rek.disabled = true;
    if (programId) loadOptions('kegiatan_id', seksiBase + '/seksi/transaksi/kegiatans?program_id=' + programId, keg, '');
});

document.getElementById('kegiatan_id').addEventListener('change', function() {
    const kegiatanId = this.value;
    const sub = document.getElementById('sub_kegiatan_id');
    const rek = document.getElementById('rekening_id');
    sub.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>'; sub.disabled = true;
    rek.innerHTML = '<option value="">-- Pilih Rekening --</option>'; rek.disabled = true;
    if (kegiatanId) loadOptions('sub_kegiatan_id', seksiBase + '/seksi/transaksi/subkegiatans?kegiatan_id=' + kegiatanId, sub, '');
});

document.getElementById('sub_kegiatan_id').addEventListener('change', function() {
    const subKegiatanId = this.value;
    const rek = document.getElementById('rekening_id');
    rek.innerHTML = '<option value="">-- Pilih Rekening --</option>'; rek.disabled = true;
    if (subKegiatanId) loadOptions('rekening_id', seksiBase + '/seksi/transaksi/rekenings?sub_kegiatan_id=' + subKegiatanId, rek, '');
});

// Inisialisasi saat edit
document.addEventListener('DOMContentLoaded', function() {
    const programId = document.getElementById('program_id').value;
    if (programId) {
        loadOptions('kegiatan_id', seksiBase + '/seksi/transaksi/kegiatans?program_id=' + programId, document.getElementById('kegiatan_id'), selectedKegiatan)
            .then(() => selectedKegiatan && loadOptions('sub_kegiatan_id', seksiBase + '/seksi/transaksi/subkegiatans?kegiatan_id=' + selectedKegiatan, document.getElementById('sub_kegiatan_id'), selectedSub))
            .then(() => selectedSub && loadOptions('rekening_id', seksiBase + '/seksi/transaksi/rekenings?sub_kegiatan_id=' + selectedSub, document.getElementById('rekening_id'), selectedRekening));
    }
});
</script>
