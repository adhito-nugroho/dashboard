<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($seksi['id']);
$kodeSeksi = $seksi['kode_seksi'] ?? '';
$namaSeksi = $seksi['nama_seksi'] ?? '';
$tahun = null; // tidak dipakai lagi
$errors = $validationErrors ?? [];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <h2>
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
            <?= $isEdit ? 'Edit Seksi' : 'Tambah Seksi' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah data seksi' : 'Tambahkan seksi baru' ?></p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= base_url('seksi/' . $action . ($isEdit ? '/' . $seksi['id'] : '')) ?>" novalidate>
                <!-- Kode Seksi -->
                <div class="mb-3">
                    <label for="kode_seksi" class="form-label">
                        Kode Seksi <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['kode_seksi']) ? 'is-invalid' : '' ?>" 
                           id="kode_seksi" 
                           name="kode_seksi" 
                           value="<?= htmlspecialchars($kodeSeksi) ?>"
                           placeholder="Contoh: SK001"
                           maxlength="50"
                           required>
                    <?php if (isset($errors['kode_seksi'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kode_seksi']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan kode seksi (maksimal 50 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Nama Seksi -->
                <div class="mb-3">
                    <label for="nama_seksi" class="form-label">
                        Nama Seksi <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['nama_seksi']) ? 'is-invalid' : '' ?>" 
                           id="nama_seksi" 
                           name="nama_seksi" 
                           value="<?= htmlspecialchars($namaSeksi) ?>"
                           placeholder="Contoh: Seksi Keuangan"
                           maxlength="255"
                           required>
                    <?php if (isset($errors['nama_seksi'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['nama_seksi']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan nama seksi (maksimal 255 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <a href="<?= base_url('seksi') ?>" class="btn btn-secondary">
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
// Client-side validation enhancement
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const kodeInput = document.getElementById('kode_seksi');
    const namaInput = document.getElementById('nama_seksi');
    const tahunInput = null;

    // Auto-uppercase kode seksi
    kodeInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;

        // Validate kode seksi
        if (kodeInput.value.trim() === '') {
            kodeInput.classList.add('is-invalid');
            isValid = false;
        } else {
            kodeInput.classList.remove('is-invalid');
        }

        // Validate nama seksi
        if (namaInput.value.trim() === '') {
            namaInput.classList.add('is-invalid');
            isValid = false;
        } else {
            namaInput.classList.remove('is-invalid');
        }

    // Tidak ada validasi tahun lagi

        if (!isValid) {
            e.preventDefault();
        }
    });
});
</script>

