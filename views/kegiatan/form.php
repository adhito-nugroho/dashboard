<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($kegiatan['id']);
$programId = $kegiatan['program_id'] ?? '';
$kodeKegiatan = $kegiatan['kode_kegiatan'] ?? '';
$namaKegiatan = $kegiatan['nama_kegiatan'] ?? '';
$tahun = $kegiatan['tahun'] ?? '';
$errors = $validationErrors ?? [];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <h2>
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
            <?= $isEdit ? 'Edit Kegiatan' : 'Tambah Kegiatan' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah data kegiatan' : 'Tambahkan kegiatan baru' ?></p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= base_url('kegiatan/' . $action . ($isEdit ? '/' . $kegiatan['id'] : '')) ?>" novalidate>
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
                                    data-tahun="<?= $program['tahun'] ?>"
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
                        <div class="form-text">Pilih program yang akan dikaitkan dengan kegiatan ini</div>
                    <?php endif; ?>
                </div>

                <!-- Tahun (Display only, from selected program) -->
                <div class="mb-3">
                    <label class="form-label">Tahun</label>
                    <input type="text" 
                           class="form-control" 
                           id="tahun_display" 
                           value="<?= htmlspecialchars($tahun) ?>"
                           readonly
                           style="background-color: #e9ecef;">
                    <div class="form-text">Tahun akan otomatis terisi sesuai program yang dipilih</div>
                </div>

                <!-- Kode Kegiatan -->
                <div class="mb-3">
                    <label for="kode_kegiatan" class="form-label">
                        Kode Kegiatan <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['kode_kegiatan']) ? 'is-invalid' : '' ?>" 
                           id="kode_kegiatan" 
                           name="kode_kegiatan" 
                           value="<?= htmlspecialchars($kodeKegiatan) ?>"
                           placeholder="Contoh: KGT001"
                           maxlength="50"
                           required>
                    <?php if (isset($errors['kode_kegiatan'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kode_kegiatan']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan kode kegiatan (maksimal 50 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Nama Kegiatan -->
                <div class="mb-3">
                    <label for="nama_kegiatan" class="form-label">
                        Nama Kegiatan <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['nama_kegiatan']) ? 'is-invalid' : '' ?>" 
                           id="nama_kegiatan" 
                           name="nama_kegiatan" 
                           value="<?= htmlspecialchars($namaKegiatan) ?>"
                           placeholder="Contoh: Kegiatan Peningkatan Pelayanan"
                           maxlength="255"
                           required>
                    <?php if (isset($errors['nama_kegiatan'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['nama_kegiatan']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan nama kegiatan (maksimal 255 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <a href="<?= base_url('kegiatan') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Client-side validation and tahun update
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const programSelect = document.getElementById('program_id');
    const tahunDisplay = document.getElementById('tahun_display');
    const kodeInput = document.getElementById('kode_kegiatan');
    const namaInput = document.getElementById('nama_kegiatan');

    // Update tahun when program changes
    programSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const tahun = selectedOption.getAttribute('data-tahun');
        tahunDisplay.value = tahun || '';
    });

    // Auto-uppercase kode kegiatan
    kodeInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;

        // Validate program
        if (programSelect.value === '') {
            programSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            programSelect.classList.remove('is-invalid');
        }

        // Validate kode kegiatan
        if (kodeInput.value.trim() === '') {
            kodeInput.classList.add('is-invalid');
            isValid = false;
        } else {
            kodeInput.classList.remove('is-invalid');
        }

        // Validate nama kegiatan
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
});
</script>

