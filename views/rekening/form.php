<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($rekening) && isset($rekening['id']);
$programId = $rekening['program_id'] ?? '';
$kegiatanId = $rekening['kegiatan_id'] ?? '';
$subKegiatanId = $rekening['sub_kegiatan_id'] ?? '';
$kodeRekening = $rekening['kode_rekening'] ?? '';
$namaRekening = $rekening['nama_rekening'] ?? '';
$errors = $validationErrors ?? [];
$action = $action ?? 'store';

// For edit mode, related dropdown data can be provided, otherwise default to empty arrays
$programs = $programs ?? [];
$kegiatans = $kegiatans ?? [];
$subKegiatans = $subKegiatans ?? [];

// For batch form errors
$batchData = $batchData ?? null;
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <h2>
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
            <?= $isEdit ? 'Edit Rekening' : 'Tambah Rekening' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah data rekening' : 'Tambahkan rekening baru' ?></p>
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

            <form method="POST" action="<?= base_url('rekening/' . $action . ($isEdit && isset($rekening['id']) ? '/' . $rekening['id'] : '')) ?>" novalidate id="rekeningForm">
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
                    <?php else: ?>
                        <div class="form-text">Pilih program yang menaungi rekening ini</div>
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
                    <?php else: ?>
                        <div class="form-text">Pilih kegiatan terkait</div>
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
                    <?php else: ?>
                        <div class="form-text">Pilih sub kegiatan yang akan diberi rekening</div>
                    <?php endif; ?>
                </div>

                <!-- Kode Rekening -->
                <div class="mb-3">
                    <label for="kode_rekening" class="form-label">
                        Kode Rekening <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['kode_rekening']) ? 'is-invalid' : '' ?>" 
                           id="kode_rekening" 
                           name="kode_rekening" 
                           value="<?= htmlspecialchars($kodeRekening) ?>"
                           placeholder="Contoh: 5.1.02.01"
                           maxlength="50"
                           required>
                    <?php if (isset($errors['kode_rekening'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kode_rekening']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan kode rekening (maksimal 50 karakter, unik)</div>
                    <?php endif; ?>
                </div>

                <!-- Nama Rekening -->
                <div class="mb-3">
                    <label for="nama_rekening" class="form-label">
                        Nama Rekening <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['nama_rekening']) ? 'is-invalid' : '' ?>" 
                           id="nama_rekening" 
                           name="nama_rekening" 
                           value="<?= htmlspecialchars($namaRekening) ?>"
                           placeholder="Contoh: Belanja Barang"
                           maxlength="255"
                           required>
                    <?php if (isset($errors['nama_rekening'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['nama_rekening']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan nama rekening (maksimal 255 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top" id="singleFormActions">
                    <a href="<?= base_url('rekening') ?>" class="btn btn-secondary">
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
            $batchRekenings = $batchData['rekenings'] ?? [];
            ?>
            <form method="POST" action="<?= base_url('rekening/store-batch') ?>" novalidate id="batchRekeningForm" style="display: none;" enctype="multipart/form-data">
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
                        <div class="form-text">Pilih sub kegiatan, kemudian tambahkan rekening di bawah</div>
                    <?php endif; ?>
                </div>

                <!-- Rekening Table -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">
                            Daftar Rekening <span class="text-danger">*</span>
                        </label>
                        <button type="button" class="btn btn-sm btn-success" id="addRekeningRow">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Rekening
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="rekeningTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="30%">Kode Rekening</th>
                                    <th width="65%">Nama Rekening</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="rekeningTableBody">
                                <!-- Rows will be added dynamically -->
                                <?php if (!empty($batchRekenings)): ?>
                                    <?php foreach ($batchRekenings as $index => $rekening): ?>
                                        <tr data-row-id="<?= $index + 1 ?>">
                                            <td>
                                                <input type="text" 
                                                       class="form-control form-control-sm <?= isset($errors['rekenings'][$index]['kode_rekening']) ? 'is-invalid' : '' ?>" 
                                                       name="rekenings[<?= $index + 1 ?>][kode_rekening]" 
                                                       value="<?= htmlspecialchars($rekening['kode_rekening'] ?? '') ?>"
                                                       placeholder="5.1.02.01" 
                                                       maxlength="50" 
                                                       required>
                                                <?php if (isset($errors['rekenings'][$index]['kode_rekening'])): ?>
                                                    <div class="invalid-feedback d-block">
                                                        <?= htmlspecialchars($errors['rekenings'][$index]['kode_rekening']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       class="form-control form-control-sm <?= isset($errors['rekenings'][$index]['nama_rekening']) ? 'is-invalid' : '' ?>" 
                                                       name="rekenings[<?= $index + 1 ?>][nama_rekening]" 
                                                       value="<?= htmlspecialchars($rekening['nama_rekening'] ?? '') ?>"
                                                       placeholder="Belanja Barang" 
                                                       maxlength="255" 
                                                       required>
                                                <?php if (isset($errors['rekenings'][$index]['nama_rekening'])): ?>
                                                    <div class="invalid-feedback d-block">
                                                        <?= htmlspecialchars($errors['rekenings'][$index]['nama_rekening']) ?>
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
                    <a href="<?= base_url('rekening') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Simpan Semua Rekening
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Cascading dropdowns and simple validation
document.addEventListener('DOMContentLoaded', function() {
    const programSelect = document.getElementById('program_id');
    const kegiatanSelect = document.getElementById('kegiatan_id');
    const subKegiatanSelect = document.getElementById('sub_kegiatan_id');
    const kodeInput = document.getElementById('kode_rekening');
    const namaInput = document.getElementById('nama_rekening');
    const form = document.getElementById('rekeningForm');

    // Program change - load kegiatan
    programSelect.addEventListener('change', function() {
        const programId = this.value;
        kegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
        kegiatanSelect.disabled = true;
        subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
        subKegiatanSelect.disabled = true;

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
        subKegiatanSelect.innerHTML = '<option value="">-- Pilih Sub Kegiatan --</option>';
        subKegiatanSelect.disabled = true;

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

    // Simple client-side validation
    form.addEventListener('submit', function(e) {
        let isValid = true;

        if (!programSelect.value) {
            programSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            programSelect.classList.remove('is-invalid');
        }

        if (!kegiatanSelect.value) {
            kegiatanSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            kegiatanSelect.classList.remove('is-invalid');
        }

        if (!subKegiatanSelect.value) {
            subKegiatanSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            subKegiatanSelect.classList.remove('is-invalid');
        }

        if (kodeInput.value.trim() === '') {
            kodeInput.classList.add('is-invalid');
            isValid = false;
        } else {
            kodeInput.classList.remove('is-invalid');
        }

        if (namaInput.value.trim() === '') {
            namaInput.classList.add('is-invalid');
            isValid = false;
        } else {
            namaInput.classList.remove('is-invalid');
        }

        if (!isValid) {
            e.preventDefault();
        }
    });

    // Mode Toggle (only for create mode)
    <?php if (!$isEdit): ?>
    const modeSingle = document.getElementById('modeSingle');
    const modeBatch = document.getElementById('modeBatch');
    const singleForm = document.getElementById('rekeningForm');
    const batchForm = document.getElementById('batchRekeningForm');
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
        const rekeningTableBody = document.getElementById('rekeningTableBody');
        const addRowBtn = document.getElementById('addRekeningRow');
        let rowCounter = 0;
        
        // Initialize row counter from existing rows
        const existingRows = rekeningTableBody.querySelectorAll('tr');
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
                rekeningTableBody.innerHTML = '';
            }
            
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
                rekeningTableBody.innerHTML = '';
            }
            
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
                        
                        // If sub kegiatan was selected (from error), add first row if no rows exist
                        <?php if (!empty($batchSubKegiatanId)): ?>
                        if (batchSubKegiatanSelect.value && rekeningTableBody.children.length === 0) {
                            addRekeningRow();
                        }
                        <?php endif; ?>
                    });
            }
        });

        // Add row function
        function addRekeningRow() {
            rowCounter++;
            const row = document.createElement('tr');
            row.setAttribute('data-row-id', rowCounter);
            row.innerHTML = `
                <td>
                    <input type="text" 
                           class="form-control form-control-sm" 
                           name="rekenings[${rowCounter}][kode_rekening]" 
                           placeholder="5.1.02.01" 
                           maxlength="50" 
                           required>
                </td>
                <td>
                    <input type="text" 
                           class="form-control form-control-sm" 
                           name="rekenings[${rowCounter}][nama_rekening]" 
                           placeholder="Belanja Barang" 
                           maxlength="255" 
                           required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger remove-row" title="Hapus">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            rekeningTableBody.appendChild(row);

            // Auto-uppercase kode rekening
            const kodeInput = row.querySelector('input[name*="[kode_rekening]"]');
            kodeInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });

            // Remove row handler
            row.querySelector('.remove-row').addEventListener('click', function() {
                if (rekeningTableBody.children.length > 1) {
                    row.remove();
                } else {
                    alert('Minimal harus ada 1 rekening');
                }
            });
        }

        // Initialize remove handlers for existing rows
        const existingRemoveBtns = rekeningTableBody.querySelectorAll('.remove-row');
        existingRemoveBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                if (rekeningTableBody.children.length > 1) {
                    row.remove();
                } else {
                    alert('Minimal harus ada 1 rekening');
                }
            });
        });

        // Initialize auto-uppercase for existing kode inputs
        const existingKodeInputs = rekeningTableBody.querySelectorAll('input[name*="[kode_rekening]"]');
        existingKodeInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        });

        // Add row button
        addRowBtn.addEventListener('click', function() {
            if (!batchSubKegiatanSelect.value) {
                alert('Pilih Sub Kegiatan terlebih dahulu');
                return;
            }
            addRekeningRow();
        });

        // Auto-add first row when sub kegiatan is selected
        batchSubKegiatanSelect.addEventListener('change', function() {
            if (this.value && rekeningTableBody.children.length === 0) {
                addRekeningRow();
            }
        });

        // Form validation and submission
        batchForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default to use our custom submission
            
            if (rekeningTableBody.children.length === 0) {
                alert('Minimal harus ada 1 rekening');
                return false;
            }

            // Validate all rows and collect data
            let isValid = true;
            const rows = rekeningTableBody.querySelectorAll('tr');
            let validRowCount = 0;
            const rekeningsData = [];
            
            rows.forEach((row, index) => {
                const kodeInput = row.querySelector('input[name*="[kode_rekening]"]');
                const namaInput = row.querySelector('input[name*="[nama_rekening]"]');

                if (!kodeInput || !namaInput) {
                    return; // Skip if inputs not found
                }

                const kodeValue = kodeInput.value.trim();
                const namaValue = namaInput.value.trim();

                if (!kodeValue) {
                    kodeInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    kodeInput.classList.remove('is-invalid');
                }

                if (!namaValue) {
                    namaInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    namaInput.classList.remove('is-invalid');
                }
                
                // Collect valid rows
                if (kodeValue && namaValue) {
                    validRowCount++;
                    rekeningsData.push({
                        kode_rekening: kodeValue,
                        nama_rekening: namaValue
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
            console.log('Rekenings data to submit:', rekeningsData);
            
            // Submit using FormData to ensure all data is sent
            const formData = new FormData();
            formData.append('program_id', batchProgramSelect.value);
            formData.append('kegiatan_id', batchKegiatanSelect.value);
            formData.append('sub_kegiatan_id', batchSubKegiatanSelect.value);
            
            // Add each rekening with sequential index
            rekeningsData.forEach((rek, index) => {
                formData.append(`rekenings[${index + 1}][kode_rekening]`, rek.kode_rekening);
                formData.append(`rekenings[${index + 1}][nama_rekening]`, rek.nama_rekening);
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
                    // Get redirect URL from response
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
                        // If we get HTML back, it means there was an error or validation failed
                        document.open();
                        document.write(html);
                        document.close();
                    });
                } else {
                    // Try to parse as JSON
                    return response.json().then(data => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else if (data.error) {
                            alert(data.error);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    }).catch(() => {
                        // If all else fails, reload the page
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


