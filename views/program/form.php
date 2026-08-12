<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($program['id']);
$kodeProgram = $program['kode_program'] ?? '';
$namaProgram = $program['nama_program'] ?? '';
$tahun = $program['tahun'] ?? date('Y');
$errors = $validationErrors ?? [];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <h2>
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
            <?= $isEdit ? 'Edit Program' : 'Tambah Program' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah data program' : 'Tambahkan program baru' ?></p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= base_url('program/' . $action . ($isEdit ? '/' . $program['id'] : '')) ?>" novalidate>
                <!-- Kode Program -->
                <div class="mb-3">
                    <label for="kode_program" class="form-label">
                        Kode Program <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['kode_program']) ? 'is-invalid' : '' ?>" 
                           id="kode_program" 
                           name="kode_program" 
                           value="<?= htmlspecialchars($kodeProgram) ?>"
                           placeholder="Contoh: PRG001"
                           maxlength="50"
                           required>
                    <?php if (isset($errors['kode_program'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kode_program']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan kode program (maksimal 50 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Nama Program -->
                <div class="mb-3">
                    <label for="nama_program" class="form-label">
                        Nama Program <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['nama_program']) ? 'is-invalid' : '' ?>" 
                           id="nama_program" 
                           name="nama_program" 
                           value="<?= htmlspecialchars($namaProgram) ?>"
                           placeholder="Contoh: Program Peningkatan Pelayanan Publik"
                           maxlength="255"
                           required>
                    <?php if (isset($errors['nama_program'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['nama_program']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan nama program (maksimal 255 karakter)</div>
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
                        <div class="form-text">Masukkan tahun program (2000-2100)</div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between">
                    <a href="<?= base_url('program') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Client-side validation enhancement
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const kodeInput = document.getElementById('kode_program');
    const namaInput = document.getElementById('nama_program');
    const tahunInput = document.getElementById('tahun');

    // Auto-uppercase kode program
    kodeInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;

        // Validate kode program
        if (kodeInput.value.trim() === '') {
            kodeInput.classList.add('is-invalid');
            isValid = false;
        } else {
            kodeInput.classList.remove('is-invalid');
        }

        // Validate nama program
        if (namaInput.value.trim() === '') {
            namaInput.classList.add('is-invalid');
            isValid = false;
        } else {
            namaInput.classList.remove('is-invalid');
        }

        // Validate tahun
        const tahun = parseInt(tahunInput.value);
        if (!tahunInput.value || tahun < 2000 || tahun > 2100) {
            tahunInput.classList.add('is-invalid');
            isValid = false;
        } else {
            tahunInput.classList.remove('is-invalid');
        }

        if (!isValid) {
            e.preventDefault();
        }
    });
});
</script>

