<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = isset($subKegiatan['id']);
$kegiatanId = $subKegiatan['kegiatan_id'] ?? '';
$seksiId = $subKegiatan['seksi_id'] ?? '';
$kodeSubKegiatan = $subKegiatan['kode_sub_kegiatan'] ?? '';
$namaSubKegiatan = $subKegiatan['nama_sub_kegiatan'] ?? '';
$tahun = $subKegiatan['tahun'] ?? '';
$errors = $validationErrors ?? [];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <h2>
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i>
            <?= $isEdit ? 'Edit Sub Kegiatan' : 'Tambah Sub Kegiatan' ?>
        </h2>
        <p class="text-muted mb-0"><?= $isEdit ? 'Ubah data sub kegiatan' : 'Tambahkan sub kegiatan baru' ?></p>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= base_url('sub-kegiatan/' . $action . ($isEdit ? '/' . $subKegiatan['id'] : '')) ?>" novalidate>
    <!-- Kegiatan Selection -->
                <div class="mb-3">
                    <label for="kegiatan_id" class="form-label">
                        Kegiatan <span class="text-danger">*</span>
                    </label>
                    <select class="form-select <?= isset($errors['kegiatan_id']) ? 'is-invalid' : '' ?>" 
                            id="kegiatan_id" 
                            name="kegiatan_id" 
                            required>
                        <option value="">-- Pilih Kegiatan --</option>
                        <?php foreach ($kegiatans as $kegiatan): ?>
                            <option value="<?= $kegiatan['id'] ?>" 
                                    data-tahun="<?= $kegiatan['tahun'] ?>"
                                    data-program="<?= htmlspecialchars($kegiatan['kode_program'] . ' - ' . $kegiatan['nama_program']) ?>"
                                    <?= ($kegiatanId == $kegiatan['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kegiatan['kode_program'] . ' / ' . $kegiatan['kode_kegiatan'] . ' - ' . $kegiatan['nama_kegiatan'] . ' (' . $kegiatan['tahun'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['kegiatan_id'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kegiatan_id']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Pilih kegiatan yang akan dikaitkan dengan sub kegiatan ini</div>
                    <?php endif; ?>
                </div>

    <!-- Seksi Selection -->
    <div class="mb-3">
        <label for="seksi_id" class="form-label">
            Seksi (Penanggung Jawab) <span class="text-danger">*</span>
        </label>
        <select class="form-select <?= isset($errors['seksi_id']) ? 'is-invalid' : '' ?>" 
                id="seksi_id" 
                name="seksi_id" 
                required>
            <option value="">-- Pilih Seksi --</option>
            <?php foreach ($seksis as $seksi): ?>
                <option value="<?= $seksi['id'] ?>" 
                        <?= ($seksiId == $seksi['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($seksi['kode_seksi'] . ' - ' . $seksi['nama_seksi']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['seksi_id'])): ?>
            <div class="invalid-feedback">
                <?= htmlspecialchars($errors['seksi_id']) ?>
            </div>
        <?php else: ?>
            <div class="form-text">Pilih seksi yang bertanggung jawab untuk sub kegiatan ini</div>
        <?php endif; ?>
    </div>

                <!-- Tahun (Display only, from selected kegiatan's program) -->
                <div class="mb-3">
                    <label class="form-label">Tahun</label>
                    <input type="text" 
                           class="form-control" 
                           id="tahun_display" 
                           value="<?= htmlspecialchars($tahun) ?>"
                           readonly
                           style="background-color: #e9ecef;">
                    <div class="form-text">Tahun akan otomatis terisi sesuai program dari kegiatan yang dipilih</div>
                </div>

                <!-- Kode Sub Kegiatan -->
                <div class="mb-3">
                    <label for="kode_sub_kegiatan" class="form-label">
                        Kode Sub Kegiatan <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['kode_sub_kegiatan']) ? 'is-invalid' : '' ?>" 
                           id="kode_sub_kegiatan" 
                           name="kode_sub_kegiatan" 
                           value="<?= htmlspecialchars($kodeSubKegiatan) ?>"
                           placeholder="Contoh: SKG001"
                           maxlength="50"
                           required>
                    <?php if (isset($errors['kode_sub_kegiatan'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['kode_sub_kegiatan']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan kode sub kegiatan (maksimal 50 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Nama Sub Kegiatan -->
                <div class="mb-3">
                    <label for="nama_sub_kegiatan" class="form-label">
                        Nama Sub Kegiatan <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control <?= isset($errors['nama_sub_kegiatan']) ? 'is-invalid' : '' ?>" 
                           id="nama_sub_kegiatan" 
                           name="nama_sub_kegiatan" 
                           value="<?= htmlspecialchars($namaSubKegiatan) ?>"
                           placeholder="Contoh: Sub Kegiatan Peningkatan Pelayanan"
                           maxlength="255"
                           required>
                    <?php if (isset($errors['nama_sub_kegiatan'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['nama_sub_kegiatan']) ?>
                        </div>
                    <?php else: ?>
                        <div class="form-text">Masukkan nama sub kegiatan (maksimal 255 karakter)</div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <a href="<?= base_url('sub-kegiatan') ?>" class="btn btn-secondary">
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
    const kegiatanSelect = document.getElementById('kegiatan_id');
    const tahunDisplay = document.getElementById('tahun_display');
    const kodeInput = document.getElementById('kode_sub_kegiatan');
    const namaInput = document.getElementById('nama_sub_kegiatan');

    // Update tahun when kegiatan changes
    kegiatanSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const tahun = selectedOption.getAttribute('data-tahun');
        tahunDisplay.value = tahun || '';
    });

    // Auto-uppercase kode sub kegiatan
    kodeInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;

        // Validate kegiatan
        if (kegiatanSelect.value === '') {
            kegiatanSelect.classList.add('is-invalid');
            isValid = false;
        } else {
            kegiatanSelect.classList.remove('is-invalid');
        }

        // Validate kode sub kegiatan
        if (kodeInput.value.trim() === '') {
            kodeInput.classList.add('is-invalid');
            isValid = false;
        } else {
            kodeInput.classList.remove('is-invalid');
        }

        // Validate nama sub kegiatan
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

