<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($transaksi['id']);
$tanggal = $transaksi['tanggal'] ?? date('Y-m-d');
$programId = $transaksi['program_id'] ?? '';
$kegiatanId = $transaksi['kegiatan_id'] ?? '';
$subKegiatanId = $transaksi['sub_kegiatan_id'] ?? '';
$rekeningId = $transaksi['rekening_id'] ?? '';
$uraian = $transaksi['uraian'] ?? '';
$nilai = $transaksi['nilai'] ?? '';
$nomorBukti = $transaksi['nomor_bukti'] ?? '';
$errors = $validationErrors ?? [];

// For edit mode, get related data
$kegiatans = $kegiatans ?? [];
$subKegiatans = $subKegiatans ?? [];
$rekenings = $rekenings ?? [];

// For batch form errors
$batchData = $batchData ?? null;
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <h2>
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
            <?= $isEdit ? 'Edit Transaksi' : 'Tambah Transaksi (Batch)' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah data transaksi' : 'Input transaksi untuk banyak rekening sekaligus' ?></p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <?php if ($isEdit): ?>
            <form method="POST" action="<?= base_url('transaksi/update/' . ($transaksi['id'] ?? '')) ?>" novalidate id="transaksiForm">
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-md-6">
                        <!-- Tanggal -->
                        <div class="mb-3">
                            <label for="tanggal" class="form-label">
                                Tanggal <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control <?= isset($errors['tanggal']) ? 'is-invalid' : '' ?>"
                                id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" required>
                            <?php if (isset($errors['tanggal'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['tanggal']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Program -->
                        <div class="mb-3">
                            <label for="program_id" class="form-label">
                                Program <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="program_id" name="program_id" required>
                                <option value="">-- Pilih Program --</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?= $program['id'] ?>" <?= ($programId == $program['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($program['kode_program'] . ' - ' . $program['nama_program']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Kegiatan -->
                        <div class="mb-3">
                            <label for="kegiatan_id" class="form-label">
                                Kegiatan <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="kegiatan_id" name="kegiatan_id" required
                                <?= (empty($programId) && !$isEdit) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Kegiatan --</option>
                                <?php foreach ($kegiatans as $kegiatan): ?>
                                    <option value="<?= $kegiatan['id'] ?>" <?= ($kegiatanId == $kegiatan['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kegiatan['kode_kegiatan'] . ' - ' . $kegiatan['nama_kegiatan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sub Kegiatan -->
                        <div class="mb-3">
                            <label for="sub_kegiatan_id" class="form-label">
                                Sub Kegiatan <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="sub_kegiatan_id" name="sub_kegiatan_id" required
                                <?= (empty($kegiatanId) && !$isEdit) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Sub Kegiatan --</option>
                                <?php foreach ($subKegiatans as $subKegiatan): ?>
                                    <option value="<?= $subKegiatan['id'] ?>" <?= ($subKegiatanId == $subKegiatan['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subKegiatan['kode_sub_kegiatan'] . ' - ' . $subKegiatan['nama_sub_kegiatan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Rekening -->
                        <div class="mb-3">
                            <label for="rekening_id" class="form-label">
                                Rekening <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?= isset($errors['rekening_id']) ? 'is-invalid' : '' ?>"
                                id="rekening_id" name="rekening_id" required <?= (empty($subKegiatanId) && !$isEdit) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Rekening --</option>
                                <?php foreach ($rekenings as $rekening): ?>
                                    <option value="<?= $rekening['id'] ?>" <?= ($rekeningId == $rekening['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rekening['kode_rekening'] . ' - ' . $rekening['nama_rekening']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['rekening_id'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['rekening_id']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <!-- Uraian -->
                        <div class="mb-3">
                            <label for="uraian" class="form-label">
                                Uraian <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control <?= isset($errors['uraian']) ? 'is-invalid' : '' ?>"
                                id="uraian" name="uraian" rows="3" maxlength="500"
                                required><?= htmlspecialchars($uraian) ?></textarea>
                            <?php if (isset($errors['uraian'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['uraian']) ?>
                                </div>
                            <?php else: ?>
                                <div class="form-text">Masukkan uraian transaksi (maksimal 500 karakter)</div>
                            <?php endif; ?>
                        </div>

                        <!-- Nilai -->
                        <div class="mb-3">
                            <label for="nilai" class="form-label">
                                Nilai <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text"
                                    class="form-control text-end <?= isset($errors['nilai']) ? 'is-invalid' : '' ?>"
                                    id="nilai" name="nilai"
                                    value="<?= $nilai ? (is_numeric($nilai) ? number_format((float) $nilai, 0, ',', '.') : $nilai) : '' ?>"
                                    placeholder="0" required>
                            </div>
                            <?php if (isset($errors['nilai'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['nilai']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Nomor Bukti -->
                        <div class="mb-3">
                            <label for="nomor_bukti" class="form-label">
                                Nomor Bukti <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                class="form-control <?= isset($errors['nomor_bukti']) ? 'is-invalid' : '' ?>"
                                id="nomor_bukti" name="nomor_bukti" value="<?= htmlspecialchars($nomorBukti) ?>"
                                placeholder="Contoh: BUK/001/2024" maxlength="100" required>
                            <?php if (isset($errors['nomor_bukti'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['nomor_bukti']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Remaining Pagu Info -->
                        <div class="mb-3">
                            <label class="form-label">Informasi Sisa Pagu</label>
                            <div id="remainingPaguInfo" class="p-3 bg-light rounded">
                                <p class="mb-0 text-muted">Pilih rekening dan tanggal untuk melihat sisa pagu</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top" id="singleFormActions">
                    <a href="<?= base_url('transaksi') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <?php if (!$isEdit): ?>
            <form method="POST" action="<?= base_url('transaksi/store-batch') ?>" novalidate id="batchTransaksiForm" enctype="multipart/form-data">
                <?php
                // Get batch data if exists (from validation errors)
                $batchData = $batchData ?? null;
                $batchTanggal = $batchData['tanggal'] ?? date('Y-m-d');
                $batchProgramId = $batchData['program_id'] ?? '';
                $batchKegiatanId = $batchData['kegiatan_id'] ?? '';
                $batchSubKegiatanId = $batchData['sub_kegiatan_id'] ?? '';
                $batchRekenings = $batchData['rekenings'] ?? [];
                ?>
                <style>
                .batch-step { display: flex; gap: 1rem; }
                .step-badge { width: 34px; height: 34px; min-width: 34px; border-radius: 50%; background: #4f46e5; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .95rem; }
                .batch-field-label { font-size: .82rem; font-weight: 700; color: #334155; }
                .batch-select-wrap { position: relative; }
                #rekeningTable thead th { background: #f1f5f9; color: #475569; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; padding: .65rem .75rem; border-bottom-width: 2px; border-color: #e2e8f0; }
                #rekeningTable td { vertical-align: middle; padding: .6rem .75rem; }
                #rekeningTable tr:hover { background: #f8fafc; }
                </style>

                <div class="row g-4">
                    <!-- Klasifikasi Anggaran -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm mb-2" style="border-radius:16px;">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-3" style="letter-spacing:-.01em;">
                                    <i class="bi bi-funnel-fill text-primary me-2"></i>Klasifikasi Anggaran
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="batch_tanggal" class="form-label batch-field-label">Tanggal Transaksi <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control <?= isset($errors['tanggal']) ? 'is-invalid' : '' ?>" id="batch_tanggal" name="tanggal" value="<?= htmlspecialchars($batchTanggal) ?>" required>
                                        <?php if (isset($errors['tanggal'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['tanggal']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12">
                                        <label for="batch_program_id" class="form-label batch-field-label">Program <span class="text-danger">*</span></label>
                                        <div class="batch-select-wrap">
                                            <select class="form-select" id="batch_program_id" name="program_id" required>
                                                <option value="">-- Pilih Program --</option>
                                                <?php foreach ($programs as $program): ?>
                                                    <option value="<?= $program['id'] ?>" <?= ($batchProgramId == $program['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($program['kode_program'] . ' - ' . $program['nama_program']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="batch_kegiatan_id" class="form-label batch-field-label">Kegiatan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="batch_kegiatan_id" name="kegiatan_id" required disabled>
                                            <option value="">-- Pilih Kegiatan --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="batch_sub_kegiatan_id" class="form-label batch-field-label">Sub Kegiatan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="batch_sub_kegiatan_id" name="sub_kegiatan_id" required disabled>
                                            <option value="">-- Pilih Sub Kegiatan --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rekening Table -->
                <div class="card border-0 shadow-sm mt-3" style="border-radius:16px;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0" style="letter-spacing:-.01em;">
                                <i class="bi bi-list-check text-primary me-2"></i>Daftar Rekening <span class="text-danger">*</span>
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2" id="rekeningCountBadge">0 rekening</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle table-hover" id="rekeningTable" style="font-size:.875rem;">
                                <thead>
                                    <tr>
                                        <th width="30%">Rekening</th>
                                        <th width="15%" class="text-end">Sisa Anggaran</th>
                                        <th width="20%">Nilai (Rp)</th>
                                        <th width="20%">Uraian</th>
                                        <th width="15%">Nomor Bukti</th>
                                    </tr>
                                </thead>
                                <tbody id="rekeningTableBody">
                                    <!-- Terisi otomatis lewat JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text text-muted">Isi nilai pada satu atau beberapa rekening. Baris dengan nilai kosong atau Rp 0 tidak akan disimpan.</div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <a href="<?= base_url('transaksi') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Simpan Semua Transaksi
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const singleForm = document.getElementById('transaksiForm');
        const batchForm = document.getElementById('batchTransaksiForm');

        // --------------------------
        // Single form (edit only)
        // --------------------------
        if (singleForm) {
            const programSelect = document.getElementById('program_id');
            const kegiatanSelect = document.getElementById('kegiatan_id');
            const subKegiatanSelect = document.getElementById('sub_kegiatan_id');
            const rekeningSelect = document.getElementById('rekening_id');
            const tanggalInput = document.getElementById('tanggal');
            const nilaiInput = document.getElementById('nilai');
            const remainingPaguInfo = document.getElementById('remainingPaguInfo');

            // Format number input
            if (nilaiInput) {
                nilaiInput.addEventListener('input', function () {
                    let value = this.value.replace(/[^\d]/g, '');
                    if (value) {
                        this.value = parseInt(value).toLocaleString('id-ID');
                    } else {
                        this.value = '';
                    }
                    checkRemainingPagu();
                });
            }

            // Check remaining pagu
            function checkRemainingPagu() {
                const rekeningId = rekeningSelect?.value;
                const tanggal = tanggalInput?.value;

                if (!rekeningId || !tanggal) {
                    if (remainingPaguInfo) {
                        remainingPaguInfo.innerHTML = '<p class="mb-0 text-muted">Pilih rekening dan tanggal untuk melihat sisa pagu</p>';
                    }
                    return;
                }

                const tahun = new Date(tanggal).getFullYear();

                fetch(`${BASE_URL}transaksi/get-remaining-pagu?rekening_id=${rekeningId}&tahun=${tahun}`)
                    .then(response => response.json())
                    .then(data => {
                        if (!remainingPaguInfo) return;
                        if (data.error) {
                            remainingPaguInfo.innerHTML = `<p class="mb-0 text-danger">${data.error}</p>`;
                            return;
                        }

                        if (!data.pagu) {
                            remainingPaguInfo.innerHTML = '<p class="mb-0 text-warning">Pagu untuk rekening dan tahun ini belum ditetapkan</p>';
                            return;
                        }

                        const currentNilai = parseFloat((nilaiInput?.value ?? '').replace(/[^\d]/g, '')) || 0;
                        const remainingAfter = data.remaining_pagu - currentNilai;

                        remainingPaguInfo.innerHTML = `
                    <div class="small">
                        <strong>Pagu:</strong> Rp ${data.pagu.toLocaleString('id-ID')}<br>
                        <strong>Total RAK:</strong> Rp ${data.total_rak.toLocaleString('id-ID')}<br>
                        <strong>Total Transaksi:</strong> Rp ${data.total_transaksi.toLocaleString('id-ID')}<br>
                        <strong>Sisa Pagu:</strong> <span class="${data.remaining_pagu < 0 ? 'text-danger' : 'text-success'}">Rp ${data.remaining_pagu.toLocaleString('id-ID')}</span><br>
                        ${currentNilai > 0 ? `<strong>Sisa Setelah Transaksi:</strong> <span class="${remainingAfter < 0 ? 'text-danger' : 'text-success'}">Rp ${remainingAfter.toLocaleString('id-ID')}</span>` : ''}
                    </div>
                `;

                        if (nilaiInput) {
                            if (remainingAfter < 0) {
                                nilaiInput.classList.add('is-invalid');
                            } else {
                                nilaiInput.classList.remove('is-invalid');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (remainingPaguInfo) {
                            remainingPaguInfo.innerHTML = '<p class="mb-0 text-danger">Gagal memuat informasi pagu</p>';
                        }
                    });
            }

            // Cascading dropdowns
            programSelect?.addEventListener('change', function () {
                const programId = this.value;
                if (kegiatanSelect) {
                    kegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
                    kegiatanSelect.disabled = true;
                }
                if (subKegiatanSelect) {
                    subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
                    subKegiatanSelect.disabled = true;
                }
                if (rekeningSelect) {
                    rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
                    rekeningSelect.disabled = true;
                }
                if (remainingPaguInfo) {
                    remainingPaguInfo.innerHTML = '<p class="mb-0 text-muted">Pilih rekening dan tanggal untuk melihat sisa pagu</p>';
                }

                if (programId && kegiatanSelect) {
                    kegiatanSelect.disabled = false;
                    fetch(`${BASE_URL}pagu/get-kegiatans?program_id=${programId}`)
                        .then(response => response.json())
                        .then(data => {
                            data.forEach(kegiatan => {
                                const option = document.createElement('option');
                                option.value = kegiatan.id;
                                option.textContent = `${kegiatan.kode_kegiatan} - ${kegiatan.nama_kegiatan}`;
                                kegiatanSelect.appendChild(option);
                            });
                            // If kegiatan was pre-selected (edit mode), restore selection
                            const preselectedKegiatan = '<?= $kegiatanId ?? '' ?>';
                            if (preselectedKegiatan && programId === '<?= $programId ?? '' ?>') {
                                kegiatanSelect.value = preselectedKegiatan;
                                if (kegiatanSelect.value) {
                                    kegiatanSelect.dispatchEvent(new Event('change'));
                                }
                            }
                        });
                }
            });

        kegiatanSelect.addEventListener('change', function () {
            const kegiatanId = this.value;
            subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
            subKegiatanSelect.disabled = true;
            rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
            rekeningSelect.disabled = true;
            remainingPaguInfo.innerHTML = '<p class="mb-0 text-muted">Pilih rekening dan tanggal untuk melihat sisa pagu</p>';

            if (kegiatanId) {
                subKegiatanSelect.disabled = false;
                fetch(`${BASE_URL}pagu/get-sub-kegiatans?kegiatan_id=${kegiatanId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(subKegiatan => {
                            const option = document.createElement('option');
                            option.value = subKegiatan.id;
                            option.textContent = `${subKegiatan.kode_sub_kegiatan} - ${subKegiatan.nama_sub_kegiatan}`;
                            subKegiatanSelect.appendChild(option);
                        });
                        // If sub_kegiatan was pre-selected (edit mode), restore selection
                        const preselectedSubKegiatan = '<?= $subKegiatanId ?? '' ?>';
                        if (preselectedSubKegiatan && kegiatanId === '<?= $kegiatanId ?? '' ?>') {
                            subKegiatanSelect.value = preselectedSubKegiatan;
                            if (subKegiatanSelect.value) {
                                subKegiatanSelect.dispatchEvent(new Event('change'));
                            }
                        }
                    });
            }
        });

        subKegiatanSelect.addEventListener('change', function () {
            const subKegiatanId = this.value;
            rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
            rekeningSelect.disabled = true;
            remainingPaguInfo.innerHTML = '<p class="mb-0 text-muted">Pilih rekening dan tanggal untuk melihat sisa pagu</p>';

            if (subKegiatanId) {
                rekeningSelect.disabled = false;
                fetch(`${BASE_URL}pagu/get-rekenings?sub_kegiatan_id=${subKegiatanId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(rekening => {
                            const option = document.createElement('option');
                            option.value = rekening.id;
                            option.textContent = `${rekening.kode_rekening} - ${rekening.nama_rekening}`;
                            rekeningSelect.appendChild(option);
                        });
                        // If rekening was pre-selected (edit mode), restore selection
                        const preselectedRekening = '<?= $rekeningId ?? '' ?>';
                        if (preselectedRekening && subKegiatanId === '<?= $subKegiatanId ?? '' ?>') {
                            rekeningSelect.value = preselectedRekening;
                            if (rekeningSelect.value && tanggalInput.value) {
                                checkRemainingPagu();
                            }
                        }
                    });
            }
        });

            rekeningSelect?.addEventListener('change', checkRemainingPagu);
            tanggalInput?.addEventListener('change', checkRemainingPagu);

            // Initial check for pagu info (works for edit mode)
            setTimeout(function () {
                if (rekeningSelect?.value && tanggalInput?.value) {
                    checkRemainingPagu();
                }
            }, 100);

            // For edit mode: trigger cascade to populate dropdowns
            <?php if ($isEdit && $programId): ?>
                setTimeout(function () {
                    if (programSelect?.value) {
                        programSelect.dispatchEvent(new Event('change'));
                    }
                }, 50);
            <?php endif; ?>
        }

        // Check if batch form has data (from validation errors)
        const hasBatchData = <?= !empty($batchData) ? 'true' : 'false' ?>;
        const oldBatchRekenings = <?= json_encode($batchRekenings) ?>;

        // Batch Form Functions
        function initBatchForm() {
            const batchTanggalInput = document.getElementById('batch_tanggal');
            const batchProgramSelect = document.getElementById('batch_program_id');
            const batchKegiatanSelect = document.getElementById('batch_kegiatan_id');
            const batchSubKegiatanSelect = document.getElementById('batch_sub_kegiatan_id');
            const rekeningTableBody = document.getElementById('rekeningTableBody');

            // Cascading dropdowns for batch form
            batchProgramSelect.addEventListener('change', function () {
                const programId = this.value;
                batchKegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
                batchKegiatanSelect.disabled = true;
                batchSubKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
                batchSubKegiatanSelect.disabled = true;
                rekeningTableBody.innerHTML = '';

                if (programId) {
                    batchKegiatanSelect.disabled = false;
                    fetch(`${BASE_URL}pagu/get-kegiatans?program_id=${programId}`)
                        .then(response => response.json())
                        .then(data => {
                            data.forEach(kegiatan => {
                                const option = document.createElement('option');
                                option.value = kegiatan.id;
                                option.textContent = `${kegiatan.kode_kegiatan} - ${kegiatan.nama_kegiatan}`;
                                batchKegiatanSelect.appendChild(option);
                            });

                            // If kegiatan was selected (from error), restore and trigger change
                            <?php if (!empty($batchKegiatanId)): ?>
                                batchKegiatanSelect.value = '<?= htmlspecialchars($batchKegiatanId) ?>';
                                if (batchKegiatanSelect.value) {
                                    batchKegiatanSelect.dispatchEvent(new Event('change'));
                                }
                            <?php endif; ?>
                        });
                }
            });

            batchKegiatanSelect.addEventListener('change', function () {
                const kegiatanId = this.value;
                batchSubKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
                batchSubKegiatanSelect.disabled = true;
                rekeningTableBody.innerHTML = '';

                if (kegiatanId) {
                    batchSubKegiatanSelect.disabled = false;
                    fetch(`${BASE_URL}pagu/get-sub-kegiatans?kegiatan_id=${kegiatanId}`)
                        .then(response => response.json())
                        .then(data => {
                            data.forEach(subKegiatan => {
                                const option = document.createElement('option');
                                option.value = subKegiatan.id;
                                option.textContent = `${subKegiatan.kode_sub_kegiatan} - ${subKegiatan.nama_sub_kegiatan}`;
                                batchSubKegiatanSelect.appendChild(option);
                            });

                            // If sub kegiatan was selected (from error), restore and trigger change
                            <?php if (!empty($batchSubKegiatanId)): ?>
                                batchSubKegiatanSelect.value = '<?= htmlspecialchars($batchSubKegiatanId) ?>';
                                if (batchSubKegiatanSelect.value) {
                                    batchSubKegiatanSelect.dispatchEvent(new Event('change'));
                                }
                            <?php endif; ?>
                        });
                }
            });

            batchSubKegiatanSelect.addEventListener('change', loadRekeningsWithBudget);
            batchTanggalInput.addEventListener('change', loadRekeningsWithBudget);

            function loadRekeningsWithBudget() {
                const subKegiatanId = batchSubKegiatanSelect.value;
                const tanggal = batchTanggalInput.value;
                if (!subKegiatanId || !tanggal) {
                    rekeningTableBody.innerHTML = '';
                    const cb = document.getElementById('rekeningCountBadge');
                    if (cb) cb.innerText = '0 rekening';
                    return;
                }

                const tahun = new Date(tanggal).getFullYear();

                fetch(`${BASE_URL}transaksi/get-rekenings-with-budget?sub_kegiatan_id=${subKegiatanId}&tahun=${tahun}`)
                    .then(response => response.json())
                    .then(data => {
                        rekeningTableBody.innerHTML = '';
                        if (data.error) {
                            rekeningTableBody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${data.error}</td></tr>`;
                            return;
                        }
                        if (data.length === 0) {
                            rekeningTableBody.innerHTML = `<tr><td colspan="5" class="text-warning text-center">Tidak ada rekening untuk Sub Kegiatan ini</td></tr>`;
                            return;
                        }

                        // Auto generate nomor bukti berurutan untuk batch ini
                        fetch(`${BASE_URL}transaksi/generate-no-bukti?tanggal=${encodeURIComponent(tanggal)}&count=${data.length}`)
                            .then(nr => nr.json())
                            .then(nres => {
                                const autoList = nres.success && nres.list ? nres.list : [];

                                data.forEach((r, index) => {
                                    const rowId = index + 1;
                                    const row = document.createElement('tr');
                                    row.setAttribute('data-rekening-id', r.id);
                                    
                                    // Find old value if exists
                                    let oldVal = '';
                                    let oldUraian = '';
                                    let oldBukti = autoList[index] || '';

                                    if (oldBatchRekenings) {
                                        const oldMatch = Object.values(oldBatchRekenings).find(old => old.rekening_id == r.id);
                                        if (oldMatch) {
                                            oldVal = oldMatch.nilai || '';
                                            oldUraian = oldMatch.uraian || '';
                                            oldBukti = oldMatch.nomor_bukti || oldBukti;
                                        }
                                    }

                                    // Format pagu and remaining budget
                                    let sisaPaguText = 'Pagu belum diatur';
                                    let sisaPaguClass = 'text-muted';
                                    if (r.pagu !== null) {
                                        sisaPaguText = 'Rp ' + r.sisa_pagu.toLocaleString('id-ID');
                                        sisaPaguClass = r.sisa_pagu < 0 ? 'text-danger font-monospace fw-bold' : 'text-success font-monospace';
                                    }

                                    row.innerHTML = `
                                        <td>
                                            <strong>${r.kode_rekening}</strong><br>
                                            <span class="text-muted small">${r.nama_rekening}</span>
                                            <input type="hidden" name="rekenings[${rowId}][rekening_id]" value="${r.id}">
                                        </td>
                                        <td class="text-end align-middle ${sisaPaguClass}" data-sisa-pagu="${r.sisa_pagu !== null ? r.sisa_pagu : ''}">
                                            ${sisaPaguText}
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" 
                                                       class="form-control text-end nilai-input" 
                                                       name="rekenings[${rowId}][nilai]" 
                                                       value="${oldVal}"
                                                       placeholder="0">
                                            </div>
                                            <div class="invalid-feedback text-end small" style="display:none;">Melebihi sisa anggaran!</div>
                                        </td>
                                        <td>
                                            <textarea class="form-control form-control-sm uraian-input" 
                                                      name="rekenings[${rowId}][uraian]" 
                                                      rows="2" 
                                                      maxlength="500">${oldUraian}</textarea>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   class="form-control form-control-sm bukti-input" 
                                                   name="rekenings[${rowId}][nomor_bukti]" 
                                                   value="${oldBukti}"
                                                   placeholder="Contoh: 123.6.6/GU/1/VIII/2026" 
                                                   maxlength="100">
                                        </td>
                                    `;
                                    rekeningTableBody.appendChild(row);

                            // Event listener for values format and limit check
                            const nilaiInput = row.querySelector('.nilai-input');
                            const feedback = row.querySelector('.invalid-feedback');
                            
                            nilaiInput.addEventListener('input', function () {
                                let value = this.value.replace(/[^\d]/g, '');
                                if (value) {
                                    this.value = parseInt(value).toLocaleString('id-ID');
                                    
                                    // Verify budget limit
                                    const rawVal = parseFloat(value);
                                    if (r.sisa_pagu !== null && rawVal > r.sisa_pagu) {
                                        this.classList.add('is-invalid');
                                        feedback.style.display = 'block';
                                    } else {
                                        this.classList.remove('is-invalid');
                                        feedback.style.display = 'none';
                                    }
                                } else {
                                    this.value = '';
                                    this.classList.remove('is-invalid');
                                    feedback.style.display = 'none';
                                }
                            });
                            
                            // If old value exists, format it initially
                            if (oldVal) {
                                nilaiInput.dispatchEvent(new Event('input'));
                            }
                        });
                        const countBadge = document.getElementById('rekeningCountBadge');
                        if (countBadge) countBadge.innerText = data.length + ' rekening';
                    })
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        rekeningTableBody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Gagal memuat daftar rekening</td></tr>';
                    });
            }

            // Initialize cascading if program/kegiatan/sub kegiatan already selected (from error)
            <?php if (!empty($batchProgramId)): ?>
                if (batchProgramSelect.value) {
                    batchProgramSelect.dispatchEvent(new Event('change'));
                }
            <?php endif; ?>

            // Form validation and submission
            batchForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const rows = rekeningTableBody.querySelectorAll('tr');
                let isValid = true;
                let filledRowCount = 0;
                const rekeningsData = [];

                rows.forEach((row) => {
                    const rekeningIdInput = row.querySelector('input[name*="[rekening_id]"]');
                    const uraianInput = row.querySelector('.uraian-input');
                    const nilaiInput = row.querySelector('.nilai-input');
                    const buktiInput = row.querySelector('.bukti-input');

                    if (!rekeningIdInput || !nilaiInput || !uraianInput || !buktiInput) {
                        return;
                    }

                    const rekeningId = rekeningIdInput.value;
                    const nilaiText = nilaiInput.value.replace(/[^\d]/g, '');
                    const uraian = uraianInput.value.trim();
                    const nomorBukti = buktiInput.value.trim();

                    if (nilaiText && parseFloat(nilaiText) > 0) {
                        filledRowCount++;
                        let rowValid = true;

                        if (!uraian) {
                            uraianInput.classList.add('is-invalid');
                            rowValid = false;
                        } else {
                            uraianInput.classList.remove('is-invalid');
                        }

                        if (!nomorBukti) {
                            buktiInput.classList.add('is-invalid');
                            rowValid = false;
                        } else {
                            buktiInput.classList.remove('is-invalid');
                        }

                        const sisaPaguTd = row.querySelector('[data-sisa-pagu]');
                        if (sisaPaguTd) {
                            const sisaPaguVal = sisaPaguTd.getAttribute('data-sisa-pagu');
                            if (sisaPaguVal !== '' && parseFloat(nilaiText) > parseFloat(sisaPaguVal)) {
                                nilaiInput.classList.add('is-invalid');
                                rowValid = false;
                            }
                        }

                        if (!rowValid) {
                            isValid = false;
                        } else {
                            rekeningsData.push({
                                rekening_id: rekeningId,
                                uraian: uraian,
                                nilai: nilaiText,
                                nomor_bukti: nomorBukti
                            });
                        }
                    } else {
                        uraianInput.classList.remove('is-invalid');
                        buktiInput.classList.remove('is-invalid');
                        nilaiInput.classList.remove('is-invalid');
                    }
                });

                if (filledRowCount === 0) {
                    alert('Mohon isi minimal 1 nilai transaksi pada rekening di atas.');
                    return false;
                }

                if (!isValid) {
                    alert('Mohon lengkapi Uraian dan Nomor Bukti pada rekening yang diisi nilainya, serta pastikan tidak melebihi sisa anggaran.');
                    return false;
                }

                const formData = new FormData();
                formData.append('tanggal', batchTanggalInput.value);
                formData.append('program_id', batchProgramSelect.value);
                formData.append('kegiatan_id', batchKegiatanSelect.value);
                formData.append('sub_kegiatan_id', batchSubKegiatanSelect.value);

                rekeningsData.forEach((rekening, index) => {
                    const rowId = index + 1;
                    formData.append(`rekenings[${rowId}][rekening_id]`, rekening.rekening_id);
                    formData.append(`rekenings[${rowId}][uraian]`, rekening.uraian);
                    formData.append(`rekenings[${rowId}][nilai]`, rekening.nilai);
                    formData.append(`rekenings[${rowId}][nomor_bukti]`, rekening.nomor_bukti);
                });

                const submitBtn = batchForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Menyimpan...';

                fetch(batchForm.action, {
                    method: 'POST',
                    body: formData,
                    redirect: 'follow'
                })
                .then(response => {
                    if (response.redirected || response.status === 302 || response.status === 301) {
                        const redirectUrl = response.url || response.headers.get('Location');
                        if (redirectUrl) {
                            window.history.pushState(null, '', redirectUrl);
                        }
                    }

                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('text/html')) {
                        return response.text().then(html => {
                            document.open();
                            document.write(html);
                            document.close();
                        });
                    } else {
                        return response.json().then(data => {
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            } else if (data.error) {
                                alert(data.error);
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }
                        }).catch(() => {
                            window.location.reload();
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menyimpan data. Silakan coba lagi.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });

                return false;
            });
        }

        if (batchForm) {
            initBatchForm();
        }
    });
</script>