<?php
/**
 * View: Form Input/Edit Pengajuan TU (Admin/Bendahara)
 * Batch banyak rekening dalam satu sub kegiatan.
 * Variabel: $form, $errors, $programs
 */
$form     = $form     ?? [];
$errors   = $errors   ?? [];
$programs = $programs ?? [];
$isEdit   = !empty($form['id']);

$formAction = $isEdit
    ? base_url('pengajuan-tu/update/' . $form['id'])
    : base_url('pengajuan-tu/store');

$namaBulanMap = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                 7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$tanggalPengajuan = $form['tanggal_pengajuan'] ?? date('Y-m-d');
$bulan            = (int) ($form['bulan'] ?? date('n'));
$tahun            = (int) ($form['tahun'] ?? date('Y'));
$keperluan        = $form['keperluan'] ?? '';

$selectedProgramId  = $form['program_id']      ?? '';
$selectedKegiatanId = $form['kegiatan_id']     ?? '';
$selectedSubKegId   = $form['sub_kegiatan_id'] ?? '';
$oldRekenings       = $form['rekenings']       ?? [];
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">

            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-plus-circle-dotted text-primary me-2"></i>
                        <?= $isEdit ? 'Edit Pengajuan TU' : 'Input Pengajuan TU' ?>
                    </h4>
                    <p class="text-muted mb-0" style="font-size:.875rem;">
                        Input kebutuhan Tambah Uang (TU) untuk beberapa rekening dalam satu sub kegiatan.
                    </p>
                </div>
                <a href="<?= base_url('pengajuan-tu') ?>" class="btn btn-outline-secondary btn-sm">
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

            <form method="POST" action="<?= $formAction ?>" id="tuForm">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header fw-semibold" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;">
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
                                    <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 2; $y--): ?>
                                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                                <?php if ($isEdit): ?><input type="hidden" name="tahun" value="<?= $tahun ?>"><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header fw-semibold" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;">
                        <i class="bi bi-diagram-3 me-2 text-primary"></i>Klasifikasi Anggaran
                        <small class="text-muted fw-normal ms-1">(pilih sub kegiatan, lalu isi nominal per rekening)</small>
                    </div>
                    <div class="card-body">
                        <?php
                        $qsEndpoint = base_url('transaksi/cari-klasifikasi');
                        $qsProgram  = 'tu_program_id';
                        $qsKegiatan = 'tu_kegiatan_id';
                        $qsSub      = 'tu_sub_kegiatan_id';
                        $qsRekening = '';
                        $qsTitle    = 'Cari Sub Kegiatan';
                        include __DIR__ . '/../shared/_klasifikasi_quick_search.php';
                        ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" style="font-size:.85rem;">Program</label>
                                <select name="program_id" id="tu_program_id" class="form-select">
                                    <option value="">-- Pilih Program --</option>
                                    <?php foreach ($programs as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $selectedProgramId == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['kode_program'] . ' – ' . $p['nama_program']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" style="font-size:.85rem;">
                                    Kegiatan
                                    <span id="spinner-tu-keg" style="display:none;font-size:.75rem;color:#4f46e5;margin-left:.3rem;"><i class="bi bi-arrow-repeat"></i></span>
                                </label>
                                <select name="kegiatan_id" id="tu_kegiatan_id" class="form-select" disabled>
                                    <option value="">-- Pilih Kegiatan --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" style="font-size:.85rem;">
                                    Sub Kegiatan <span class="text-danger">*</span>
                                    <span id="spinner-tu-sub" style="display:none;font-size:.75rem;color:#4f46e5;margin-left:.3rem;"><i class="bi bi-arrow-repeat"></i></span>
                                </label>
                                <select name="sub_kegiatan_id" id="tu_sub_kegiatan_id" class="form-select" disabled required>
                                    <option value="">-- Pilih Sub Kegiatan --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;">
                        <span><i class="bi bi-list-check me-2 text-primary"></i>Rekening & Kebutuhan</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="tuRekeningCount">0 rekening dipilih</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0" style="font-size:.875rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Rekening</th>
                                        <th class="text-end pe-3" style="width:240px;">Nominal (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody id="tuRekeningBody">
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-4">
                                            Pilih Sub Kegiatan terlebih dahulu untuk menampilkan daftar rekening.
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td class="ps-3 fw-bold text-end">Total Kebutuhan TU</td>
                                        <td class="text-end pe-3 fw-bold text-primary" style="font-size:1rem;">
                                            Rp <span id="tuTotal">0</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-muted" style="font-size:.8rem;">
                        Isi nominal pada rekening yang digunakan. Baris dengan nominal kosong/0 tidak akan disimpan.
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header fw-semibold" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.9rem;">
                        <i class="bi bi-cash-coin me-2 text-primary"></i>Keperluan
                    </div>
                    <div class="card-body">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Keperluan / Uraian Kegiatan <span class="text-danger">*</span></label>
                        <textarea name="keperluan" class="form-control" rows="4"
                                  placeholder="Contoh: Perjalanan dinas dalam rangka monitoring kegiatan lapangan bulan Oktober 2026, pembayaran honorarium narasumber, dan belanja ATK"
                                  required maxlength="500"><?= htmlspecialchars($keperluan) ?></textarea>
                        <small class="text-muted">Uraian lengkap kegiatan yang akan dibiayai dari TU ini. Maks 500 karakter.</small>
                    </div>
                </div>

                <div class="alert alert-info py-2" style="font-size:.82rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Setelah pengajuan disimpan, lakukan <strong>Cairkan SP2D-TU</strong> saat dana masuk,
                    catat pembayaran dari halaman detail, lalu <strong>selesaikan</strong> dengan menginput setoran sisa.
                </div>

                <div style="position:sticky;bottom:0;z-index:1020;background:#fff;border-top:1px solid #e2e8f0;padding:.85rem 0;margin-top:1rem;">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('pengajuan-tu') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i><?= $isEdit ? 'Perbarui Pengajuan' : 'Simpan Pengajuan' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE = (typeof BASE_URL !== 'undefined' ? BASE_URL.replace(/\/$/, '') : '');
const oldRekenings = <?= json_encode($oldRekenings) ?>;

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// ── Cascade dropdown: Program → Kegiatan → Sub Kegiatan ────────────────
function tuLoadOptions(selectEl, url, keepValue, placeholder, spinnerId) {
    const spinner = document.getElementById(spinnerId);
    selectEl.innerHTML = `<option value="">Memuat…</option>`;
    selectEl.disabled  = true;
    if (spinner) spinner.style.display = 'inline';
    return fetch(url)
        .then(r => r.json())
        .then(data => {
            let html = `<option value="">${placeholder}</option>`;
            data.forEach(d => {
                let label = d.kode_sub_kegiatan
                    ? d.kode_sub_kegiatan + ' – ' + (d.nama_sub_kegiatan || '')
                    : d.kode_kegiatan
                        ? d.kode_kegiatan + ' – ' + (d.nama_kegiatan || '')
                        : '';
                html += `<option value="${d.id}">${escapeHtml(label)}</option>`;
            });
            selectEl.innerHTML = html;
            selectEl.disabled  = false;
            if (keepValue) selectEl.value = keepValue;
            if (spinner) spinner.style.display = 'none';
            return data;
        })
        .catch(() => {
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            selectEl.disabled  = false;
            if (spinner) spinner.style.display = 'none';
        });
}

const tuProgram  = document.getElementById('tu_program_id');
const tuKegiatan = document.getElementById('tu_kegiatan_id');
const tuSubKeg   = document.getElementById('tu_sub_kegiatan_id');
const tuBody     = document.getElementById('tuRekeningBody');

tuProgram.addEventListener('change', function () {
    tuKegiatan.innerHTML = '<option value="">-- Pilih Kegiatan --</option>'; tuKegiatan.disabled = true;
    tuSubKeg.innerHTML   = '<option value="">-- Pilih Sub Kegiatan --</option>'; tuSubKeg.disabled = true;
    clearRekenings();
    if (!this.value) return;
    tuLoadOptions(tuKegiatan, `${BASE}/pagu/get-kegiatans?program_id=${this.value}`,
        '', '-- Pilih Kegiatan --', 'spinner-tu-keg');
});

tuKegiatan.addEventListener('change', function () {
    tuSubKeg.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>'; tuSubKeg.disabled = true;
    clearRekenings();
    if (!this.value) return;
    tuLoadOptions(tuSubKeg, `${BASE}/pagu/get-sub-kegiatans?kegiatan_id=${this.value}`,
        '', '-- Pilih Sub Kegiatan --', 'spinner-tu-sub');
});

tuSubKeg.addEventListener('change', function () {
    loadRekenings(this.value);
});

// ── Daftar rekening per sub kegiatan ───────────────────────────────────
function clearRekenings() {
    tuBody.innerHTML = `
        <tr>
            <td colspan="2" class="text-center text-muted py-4">
                Pilih Sub Kegiatan terlebih dahulu untuk menampilkan daftar rekening.
            </td>
        </tr>`;
    updateTotal();
}

function loadRekenings(subKegiatanId) {
    if (!subKegiatanId) { clearRekenings(); return; }
    tuBody.innerHTML = `<tr><td colspan="2" class="text-center text-muted py-3"><i class="bi bi-arrow-repeat me-1"></i>Memuat rekening…</td></tr>`;
    fetch(`${BASE}/pagu/get-rekenings?sub_kegiatan_id=${encodeURIComponent(subKegiatanId)}`)
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data)) throw new Error('Respons tidak valid');
            renderRekenings(data);
        })
        .catch(() => {
            tuBody.innerHTML = `<tr><td colspan="2" class="text-center text-danger py-3">Gagal memuat daftar rekening.</td></tr>`;
            updateTotal();
        });
}

function renderRekenings(data) {
    if (!data.length) {
        tuBody.innerHTML = `<tr><td colspan="2" class="text-center text-warning py-3">Tidak ada rekening untuk sub kegiatan ini.</td></tr>`;
        updateTotal();
        return;
    }

    const oldMap = {};
    (oldRekenings || []).forEach(r => { oldMap[String(r.rekening_id)] = r.nominal; });

    tuBody.innerHTML = '';
    data.forEach((r, i) => {
        const oldVal = oldMap[String(r.id)];
        const valFmt = oldVal ? Number(oldVal).toLocaleString('id-ID') : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="ps-3">
                <div class="fw-semibold" style="font-size:.85rem;">${escapeHtml(r.kode_rekening)}</div>
                <div class="text-muted" style="font-size:.78rem;">${escapeHtml(r.nama_rekening)}</div>
                <input type="hidden" name="rekenings[${i}][rekening_id]" value="${Number(r.id)}">
            </td>
            <td class="text-end pe-3">
                <div class="input-group input-group-sm justify-content-end">
                    <span class="input-group-text">Rp</span>
                    <input type="text" class="form-control text-end tu-nominal"
                           name="rekenings[${i}][nominal]" value="${valFmt}"
                           placeholder="0" inputmode="numeric">
                </div>
            </td>`;
        tuBody.appendChild(tr);

        const input = tr.querySelector('.tu-nominal');
        input.addEventListener('input', function () {
            const digits = this.value.replace(/[^0-9]/g, '');
            this.value = digits ? parseInt(digits, 10).toLocaleString('id-ID') : '';
            updateTotal();
        });
    });

    updateTotal();
}

function updateTotal() {
    let total = 0, count = 0;
    document.querySelectorAll('.tu-nominal').forEach(inp => {
        const v = parseInt(inp.value.replace(/[^0-9]/g, ''), 10) || 0;
        total += v;
        if (v > 0) count++;
    });
    const totalEl = document.getElementById('tuTotal');
    const countEl = document.getElementById('tuRekeningCount');
    if (totalEl) totalEl.textContent = total.toLocaleString('id-ID');
    if (countEl) countEl.textContent = count + ' rekening dipilih';
}

// ── Pre-load cascade saat edit / error re-render ───────────────────────
(function () {
    const savedProgram  = <?= json_encode((string) $selectedProgramId) ?>;
    const savedKegiatan = <?= json_encode((string) $selectedKegiatanId) ?>;
    const savedSub      = <?= json_encode((string) $selectedSubKegId) ?>;

    if (!savedProgram) return;
    tuProgram.value = savedProgram;
    tuLoadOptions(tuKegiatan, `${BASE}/pagu/get-kegiatans?program_id=${savedProgram}`,
        savedKegiatan, '-- Pilih Kegiatan --', 'spinner-tu-keg')
    .then(() => {
        if (!savedKegiatan) return;
        return tuLoadOptions(tuSubKeg, `${BASE}/pagu/get-sub-kegiatans?kegiatan_id=${savedKegiatan}`,
            savedSub, '-- Pilih Sub Kegiatan --', 'spinner-tu-sub');
    })
    .then(() => {
        if (savedSub) loadRekenings(savedSub);
    });
})();
</script>
