<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($pagu['id']);
$programId = $pagu['program_id'] ?? '';
$kegiatanId = $pagu['kegiatan_id'] ?? '';
$subKegiatanId = $pagu['sub_kegiatan_id'] ?? '';
$rekeningId = $pagu['rekening_id'] ?? '';
$tahun = $pagu['tahun'] ?? date('Y');
$nilaiPagu = $pagu['nilai_pagu'] ?? '';
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
            <?= $isEdit ? 'Edit Pagu' : 'Tambah Pagu' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah alokasi anggaran' : 'Tambahkan alokasi anggaran baru' ?></p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <!-- Mode Toggle (only for create mode) -->
            <?php if (!$isEdit): ?>
            <div class="mb-3">
                <div class="btn-group" role="group" aria-label="Input mode">
                    <input type="radio" class="btn-check" name="inputMode" id="modeSingle" value="single" checked>
                    <label class="btn btn-outline-primary" for="modeSingle">
                        <i class="bi bi-file-earmark-plus me-1"></i> Input Satu Rekening
                    </label>
                    <input type="radio" class="btn-check" name="inputMode" id="modeBatch" value="batch">
                    <label class="btn btn-outline-primary" for="modeBatch">
                        <i class="bi bi-files me-1"></i> Input Banyak Rekening
                    </label>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= base_url('pagu/' . $action . ($isEdit ? '/' . $pagu['id'] : '')) ?>" novalidate id="paguForm">
                <!-- Program Selection -->
                <div class="mb-3">
                    <label for="program_id" class="form-label">
                        Program <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['program_id']) ? 'is-invalid' : '' ?>" 
                            id="program_id" 
                            name="program_id" 
                            required>
                        <option value="">-- Pilih Program --</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= $program['id'] ?>" 
                                    <?= ($programId == $program['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($program['kode_program'] . ' - ' . $program['nama_program'] . ' (' . $program['tahun'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['program_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['program_id']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kegiatan Selection -->
                <div class="mb-3">
                    <label for="kegiatan_id" class="form-label">
                        Kegiatan <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['kegiatan_id']) ? 'is-invalid' : '' ?>" 
                            id="kegiatan_id" 
                            name="kegiatan_id" 
                            required
                            <?= empty($programId) ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Kegiatan --</option>
                        <?php foreach ($kegiatans as $kegiatan): ?>
                            <option value="<?= $kegiatan['id'] ?>" 
                                    <?= ($kegiatanId == $kegiatan['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kegiatan['kode_kegiatan'] . ' - ' . $kegiatan['nama_kegiatan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['kegiatan_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kegiatan_id']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sub Kegiatan Selection -->
                <div class="mb-3">
                    <label for="sub_kegiatan_id" class="form-label">
                        Sub Kegiatan <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['sub_kegiatan_id']) ? 'is-invalid' : '' ?>" 
                            id="sub_kegiatan_id" 
                            name="sub_kegiatan_id" 
                            required
                            <?= empty($kegiatanId) ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Sub Kegiatan --</option>
                        <?php foreach ($subKegiatans as $subKegiatan): ?>
                            <option value="<?= $subKegiatan['id'] ?>" 
                                    <?= ($subKegiatanId == $subKegiatan['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subKegiatan['kode_sub_kegiatan'] . ' - ' . $subKegiatan['nama_sub_kegiatan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['sub_kegiatan_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['sub_kegiatan_id']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Rekening Selection -->
                <div class="mb-3">
                    <label for="rekening_id" class="form-label">
                        Rekening <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['rekening_id']) ? 'is-invalid' : '' ?>" 
                            id="rekening_id" 
                            name="rekening_id" 
                            required
                            <?= empty($subKegiatanId) ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Rekening --</option>
                        <?php foreach ($rekenings as $rekening): ?>
                            <option value="<?= $rekening['id'] ?>" 
                                    <?= ($rekeningId == $rekening['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rekening['kode_rekening'] . ' - ' . $rekening['nama_rekening']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['rekening_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['rekening_id']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Pilih rekening untuk alokasi anggaran</div>
                    <?php endif; ?>
                </div>

                <!-- Tahun -->
                <div class="mb-3">
                    <label for="tahun" class="form-label">
                        Tahun <span class="text-danger">*</span>
                    </label>
                    <input type="number" 
                           class="form-control <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>" 
                           id="tahun" 
                           name="tahun" 
                           value="<?= htmlspecialchars($tahun) ?>"
                           min="2000"
                           max="2100"
                           required>
                    <?php if (isset($errors['tahun'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['tahun']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan tahun alokasi anggaran (2000-2100)</div>
                    <?php endif; ?>
                </div>

                <!-- Nilai Pagu -->
                <div class="mb-3">
                    <label for="nilai_pagu" class="form-label">
                        Nilai Pagu <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" 
                               class="form-control <?= isset($errors['nilai_pagu']) ? 'is-invalid' : '' ?>" 
                               id="nilai_pagu" 
                               name="nilai_pagu" 
                               value="<?= $nilaiPagu ? number_format($nilaiPagu, 0, ',', '.') : '' ?>"
                               placeholder="0"
                               required>
                    </div>
                    <?php if (isset($errors['nilai_pagu'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['nilai_pagu']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan nilai pagu (contoh: 1000000 atau 1.000.000)</div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top" id="singleFormActions">
                    <a href="<?= base_url('pagu') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                </div>
            </form>

            <!-- Batch Form (Hidden by default, only for create mode) -->
            <?php if (!$isEdit): ?>
            <?php 
            // Get batch data if exists (from validation errors)
            $batchData = $batchData ?? null;
            $batchProgramId = $batchData['program_id'] ?? '';
            $batchKegiatanId = $batchData['kegiatan_id'] ?? '';
            $batchSubKegiatanId = $batchData['sub_kegiatan_id'] ?? '';
            $batchTahun = $batchData['tahun'] ?? date('Y');
            $batchPagus = $batchData['pagus'] ?? [];
            ?>
            <form method="POST" action="<?= base_url('pagu/store-batch') ?>" novalidate id="batchPaguForm" style="display: none;" enctype="multipart/form-data">
                <!-- Program Selection -->
                <div class="mb-3">
                    <label for="batch_program_id" class="form-label">
                        Program <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['program_id']) ? 'is-invalid' : '' ?>" 
                            id="batch_program_id" 
                            name="program_id" 
                            required>
                        <option value="">-- Pilih Program --</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= $program['id'] ?>" 
                                    <?= ($batchProgramId == $program['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($program['kode_program'] . ' - ' . $program['nama_program'] . ' (' . $program['tahun'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['program_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['program_id']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kegiatan Selection -->
                <div class="mb-3">
                    <label for="batch_kegiatan_id" class="form-label">
                        Kegiatan <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['kegiatan_id']) ? 'is-invalid' : '' ?>" 
                            id="batch_kegiatan_id" 
                            name="kegiatan_id" 
                            required
                            disabled>
                        <option value="">-- Pilih Kegiatan --</option>
                    </select>
                    <?php if (isset($errors['kegiatan_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kegiatan_id']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sub Kegiatan Selection -->
                <div class="mb-3">
                    <label for="batch_sub_kegiatan_id" class="form-label">
                        Sub Kegiatan <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['sub_kegiatan_id']) ? 'is-invalid' : '' ?>" 
                            id="batch_sub_kegiatan_id" 
                            name="sub_kegiatan_id" 
                            required
                            disabled>
                        <option value="">-- Pilih Sub Kegiatan --</option>
                    </select>
                    <?php if (isset($errors['sub_kegiatan_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['sub_kegiatan_id']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Pilih sub kegiatan, kemudian tambahkan pagu untuk rekening di bawah</div>
                    <?php endif; ?>
                </div>

                <!-- Tahun -->
                <div class="mb-3">
                    <label for="batch_tahun" class="form-label">
                        Tahun <span class="text-danger">*</span>
                    </label>
                    <input type="number" 
                           class="form-control <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>" 
                           id="batch_tahun" 
                           name="tahun" 
                           value="<?= htmlspecialchars($batchTahun) ?>"
                           min="2000"
                           max="2100"
                           required>
                    <?php if (isset($errors['tahun'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['tahun']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Tahun berlaku untuk semua pagu di bawah</div>
                    <?php endif; ?>
                </div>

                <!-- Pagu Table -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">
                            Daftar Pagu Rekening <span class="text-danger">*</span>
                        </label>
                        <button type="button" class="btn btn-sm btn-success" id="addPaguRow">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Rekening
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="paguTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="35%">Rekening</th>
                                    <th width="50%">Nilai Pagu</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="paguTableBody">
                                <!-- Rows will be added dynamically -->
                                <?php if (!empty($batchPagus)): ?>
                                    <?php foreach ($batchPagus as $index => $pagu): ?>
                                        <tr data-row-id="<?= $index + 1 ?>">
                                            <td>
                                                <select class="form-select form-select-sm rekening-select <?= isset($errors['pagus'][$index]['rekening_id']) ? 'is-invalid' : '' ?>" 
                                                        name="pagus[<?= $index + 1 ?>][rekening_id]" 
                                                        required>
                                                    <option value="">-- Pilih Rekening --</option>
                                                    <!-- Options will be populated by JavaScript -->
                                                </select>
                                                <?php if (isset($errors['pagus'][$index]['rekening_id'])): ?>
                                                    <div class="invalid-feedback d-block">
                                                        <?= htmlspecialchars($errors['pagus'][$index]['rekening_id']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="text" 
                                                           class="form-control text-end nilai-pagu-input <?= isset($errors['pagus'][$index]['nilai_pagu']) ? 'is-invalid' : '' ?>" 
                                                           name="pagus[<?= $index + 1 ?>][nilai_pagu]" 
                                                           value="<?= htmlspecialchars($pagu['nilai_pagu'] ?? '') ?>"
                                                           placeholder="0" 
                                                           required>
                                                </div>
                                                <?php if (isset($errors['pagus'][$index]['nilai_pagu'])): ?>
                                                    <div class="invalid-feedback d-block">
                                                        <?= htmlspecialchars($errors['pagus'][$index]['nilai_pagu']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-danger remove-row" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text">Minimal harus ada 1 rekening</div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <a href="<?= base_url('pagu') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Simpan Semua Pagu
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Cascading dropdowns and form validation
document.addEventListener('DOMContentLoaded', function() {
    const programSelect = document.getElementById('program_id');
    const kegiatanSelect = document.getElementById('kegiatan_id');
    const subKegiatanSelect = document.getElementById('sub_kegiatan_id');
    const rekeningSelect = document.getElementById('rekening_id');
    const tahunInput = document.getElementById('tahun');
    const nilaiPaguInput = document.getElementById('nilai_pagu');
    const form = document.getElementById('paguForm');

    // Format number input
    nilaiPaguInput.addEventListener('input', function() {
        let value = this.value.replace(/[^\d]/g, '');
        if (value) {
            this.value = parseInt(value).toLocaleString('id-ID');
        }
    });

    // Program change - load kegiatan
    programSelect.addEventListener('change', function() {
        const programId = this.value;
        
        // Reset dependent dropdowns
        kegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
        kegiatanSelect.disabled = true;
        subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
        subKegiatanSelect.disabled = true;
        rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
        rekeningSelect.disabled = true;
        
        if (programId) {
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
                })
                .catch(error => console.error('Error:', error));
        }
    });

    // Kegiatan change - load sub kegiatan
    kegiatanSelect.addEventListener('change', function() {
        const kegiatanId = this.value;
        
        // Reset dependent dropdowns
        subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
        subKegiatanSelect.disabled = true;
        rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
        rekeningSelect.disabled = true;
        
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
                })
                .catch(error => console.error('Error:', error));
        }
    });

    // Sub Kegiatan change - load rekening
    subKegiatanSelect.addEventListener('change', function() {
        const subKegiatanId = this.value;
        
        // Reset dependent dropdown
        rekeningSelect.innerHTML = '<option value="">-- Pilih Rekening --</option>';
        rekeningSelect.disabled = true;
        
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
                })
                .catch(error => console.error('Error:', error));
        }
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;

        // Validate program
        if (!programSelect.value) {
            programSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            programSelect.classList.remove('is-invalid');
        }

        // Validate kegiatan
        if (!kegiatanSelect.value) {
            kegiatanSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            kegiatanSelect.classList.remove('is-invalid');
        }

        // Validate sub kegiatan
        if (!subKegiatanSelect.value) {
            subKegiatanSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            subKegiatanSelect.classList.remove('is-invalid');
        }

        // Validate rekening
        if (!rekeningSelect.value) {
            rekeningSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            rekeningSelect.classList.remove('is-invalid');
        }

        // Validate tahun
        const tahun = parseInt(tahunInput.value);
        if (!tahunInput.value || tahun < 2000 || tahun > 2100) {
            tahunInput.classList.add('is-invalid');
            isValid = false;
        } else {
            tahunInput.classList.remove('is-invalid');
        }

        // Validate nilai pagu
        const nilaiPagu = nilaiPaguInput.value.replace(/[^\d]/g, '');
        if (!nilaiPagu || parseFloat(nilaiPagu) <= 0) {
            nilaiPaguInput.classList.add('is-invalid');
            isValid = false;
        } else {
            nilaiPaguInput.classList.remove('is-invalid');
        }

        if (!isValid) {
            e.preventDefault();
        }
    });

    // Mode Toggle (only for create mode)
    <?php if (!$isEdit): ?>
    const modeSingle = document.getElementById('modeSingle');
    const modeBatch = document.getElementById('modeBatch');
    const singleForm = document.getElementById('paguForm');
    const batchForm = document.getElementById('batchPaguForm');
    const singleFormActions = document.getElementById('singleFormActions');
    
    // Check if batch form has data (from validation errors)
    const hasBatchData = <?= !empty($batchData) ? 'true' : 'false' ?>;
    
    // If batch form has data, show it by default
    if (hasBatchData && batchForm) {
        modeBatch.checked = true;
        singleForm.style.display = 'none';
        batchForm.style.display = 'block';
        initBatchForm();
    } else {
        modeSingle.addEventListener('change', function() {
            if (this.checked) {
                singleForm.style.display = 'block';
                batchForm.style.display = 'none';
            }
        });

        modeBatch.addEventListener('change', function() {
            if (this.checked) {
                singleForm.style.display = 'none';
                batchForm.style.display = 'block';
                initBatchForm();
            }
        });
    }

    // Batch Form Functions
    function initBatchForm() {
        const batchProgramSelect = document.getElementById('batch_program_id');
        const batchKegiatanSelect = document.getElementById('batch_kegiatan_id');
        const batchSubKegiatanSelect = document.getElementById('batch_sub_kegiatan_id');
        const batchTahunInput = document.getElementById('batch_tahun');
        const paguTableBody = document.getElementById('paguTableBody');
        const addRowBtn = document.getElementById('addPaguRow');
        let rowCounter = 0;
        let availableRekenings = [];
        
        // Initialize row counter from existing rows
        const existingRows = paguTableBody.querySelectorAll('tr');
        if (existingRows.length > 0) {
            rowCounter = existingRows.length;
        }

        // Cascading dropdowns for batch form
        batchProgramSelect.addEventListener('change', function() {
            const programId = this.value;
            batchKegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
            batchKegiatanSelect.disabled = true;
            batchSubKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
            batchSubKegiatanSelect.disabled = true;
            if (!hasBatchData) {
                paguTableBody.innerHTML = '';
            }
            availableRekenings = [];
            
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
                        
                        // If kegiatan was selected (from error), trigger change
                        <?php if (!empty($batchKegiatanId)): ?>
                        if (batchKegiatanSelect.value) {
                            batchKegiatanSelect.dispatchEvent(new Event('change'));
                        }
                        <?php endif; ?>
                    });
            }
        });

        batchKegiatanSelect.addEventListener('change', function() {
            const kegiatanId = this.value;
            batchSubKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
            batchSubKegiatanSelect.disabled = true;
            if (!hasBatchData) {
                paguTableBody.innerHTML = '';
            }
            availableRekenings = [];
            
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
                        
                        // If sub kegiatan was selected (from error), trigger change
                        <?php if (!empty($batchSubKegiatanId)): ?>
                        if (batchSubKegiatanSelect.value) {
                            batchSubKegiatanSelect.dispatchEvent(new Event('change'));
                        }
                        <?php endif; ?>
                    });
            }
        });

        batchSubKegiatanSelect.addEventListener('change', function() {
            const subKegiatanId = this.value;
            
            if (subKegiatanId) {
                fetch(`${BASE_URL}pagu/get-rekenings?sub_kegiatan_id=${subKegiatanId}`)
                    .then(response => response.json())
                    .then(data => {
                        availableRekenings = data;
                        
                        // Update existing rows with rekening options
                        const existingRows = paguTableBody.querySelectorAll('tr');
                        existingRows.forEach(row => {
                            const select = row.querySelector('.rekening-select');
                            if (select) {
                                const currentValue = select.value;
                                select.innerHTML = '<option value="">-- Pilih Rekening --</option>' +
                                    data.map(r => 
                                        `<option value="${r.id}" ${r.id == currentValue ? 'selected' : ''}>${r.kode_rekening} - ${r.nama_rekening}</option>`
                                    ).join('');
                            }
                        });
                        
                        // Add first row automatically if no rows exist
                        if (existingRows.length === 0) {
                            addPaguRow();
                        }
                    });
            } else {
                paguTableBody.innerHTML = '';
                availableRekenings = [];
            }
        });

        // Add row function
        function addPaguRow() {
            rowCounter++;
            const row = document.createElement('tr');
            row.setAttribute('data-row-id', rowCounter);
            row.innerHTML = `
                <td>
                    <select class="form-select form-select-sm rekening-select" 
                            name="pagus[${rowCounter}][rekening_id]" 
                            required>
                        <option value="">-- Pilih Rekening --</option>
                        ${availableRekenings.map(r => 
                            `<option value="${r.id}">${r.kode_rekening} - ${r.nama_rekening}</option>`
                        ).join('')}
                    </select>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Rp</span>
                        <input type="text" 
                               class="form-control text-end nilai-pagu-input" 
                               name="pagus[${rowCounter}][nilai_pagu]" 
                               placeholder="0" 
                               required>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger remove-row" title="Hapus">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            paguTableBody.appendChild(row);

            // Format number input
            const nilaiInput = row.querySelector('.nilai-pagu-input');
            nilaiInput.addEventListener('input', function() {
                let value = this.value.replace(/[^\d]/g, '');
                if (value) {
                    this.value = parseInt(value).toLocaleString('id-ID');
                } else {
                    this.value = '';
                }
            });

            // Remove row handler
            row.querySelector('.remove-row').addEventListener('click', function() {
                if (paguTableBody.children.length > 1) {
                    row.remove();
                } else {
                    alert('Minimal harus ada 1 rekening');
                }
            });
        }

        // Initialize number formatting for existing rows
        const existingNilaiInputs = paguTableBody.querySelectorAll('.nilai-pagu-input');
        existingNilaiInputs.forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/[^\d]/g, '');
                if (value) {
                    this.value = parseInt(value).toLocaleString('id-ID');
                } else {
                    this.value = '';
                }
            });
        });
        
        // Initialize remove handlers for existing rows
        const existingRemoveBtns = paguTableBody.querySelectorAll('.remove-row');
        existingRemoveBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                if (paguTableBody.children.length > 1) {
                    row.remove();
                } else {
                    alert('Minimal harus ada 1 rekening');
                }
            });
        });

        // Add row button
        addRowBtn.addEventListener('click', function() {
            if (!batchSubKegiatanSelect.value) {
                alert('Pilih Sub Kegiatan terlebih dahulu');
                return;
            }
            addPaguRow();
        });

        // Form validation and submission
        batchForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default to use our custom submission
            
            if (paguTableBody.children.length === 0) {
                alert('Minimal harus ada 1 rekening');
                return false;
            }

            // Validate all rows and collect data
            let isValid = true;
            const rows = paguTableBody.querySelectorAll('tr');
            let validRowCount = 0;
            const pagusData = [];
            
            rows.forEach((row, index) => {
                const rekeningSelect = row.querySelector('.rekening-select');
                const nilaiInput = row.querySelector('.nilai-pagu-input');

                if (!rekeningSelect || !nilaiInput) {
                    return; // Skip if inputs not found
                }

                const rekeningValue = rekeningSelect.value;
                const nilaiValue = nilaiInput.value.replace(/[^\d]/g, '');

                if (!rekeningValue) {
                    rekeningSelect.classList.add('is-invalid');
                    isValid = false;
                } else {
                    rekeningSelect.classList.remove('is-invalid');
                }

                if (!nilaiValue || parseFloat(nilaiValue) <= 0) {
                    nilaiInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    nilaiInput.classList.remove('is-invalid');
                }
                
                // Collect valid rows
                if (rekeningValue && nilaiValue && parseFloat(nilaiValue) > 0) {
                    validRowCount++;
                    pagusData.push({
                        rekening_id: rekeningValue,
                        nilai_pagu: nilaiValue
                    });
                }
            });

            if (validRowCount === 0) {
                alert('Minimal harus ada 1 rekening yang valid');
                return false;
            }

            if (!isValid) {
                alert('Mohon lengkapi semua field yang wajib diisi');
                return false;
            }
            
            // Debug: log jumlah rows yang akan dikirim
            console.log('Jumlah rows yang akan dikirim:', validRowCount);
            console.log('Pagus data to submit:', pagusData);
            
            // Submit using FormData to ensure all data is sent
            const formData = new FormData();
            formData.append('program_id', batchProgramSelect.value);
            formData.append('kegiatan_id', batchKegiatanSelect.value);
            formData.append('sub_kegiatan_id', batchSubKegiatanSelect.value);
            formData.append('tahun', batchTahunInput.value);
            
            // Add each pagu with sequential index
            pagusData.forEach((pagu, index) => {
                formData.append(`pagus[${index + 1}][rekening_id]`, pagu.rekening_id);
                formData.append(`pagus[${index + 1}][nilai_pagu]`, pagu.nilai_pagu);
            });
            
            // Show loading
            const submitBtn = batchForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Menyimpan...';
            
            // Submit via fetch to ensure all data is sent
            fetch(batchForm.action, {
                method: 'POST',
                body: formData,
                redirect: 'follow'
            })
            .then(response => {
                // Check if response is a redirect
                if (response.redirected || response.status === 302 || response.status === 301) {
                    const redirectUrl = response.url || response.headers.get('Location');
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                        return;
                    }
                }
                
                // If not redirected, check content type
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

        // Initialize cascading if program already selected (from error)
        <?php if (!empty($batchProgramId)): ?>
        if (batchProgramSelect.value) {
            batchProgramSelect.dispatchEvent(new Event('change'));
        }
        <?php endif; ?>
    }
    <?php endif; ?>
});
</script>

