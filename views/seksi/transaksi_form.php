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
$namaPenerima = $transaksi['nama_penerima'] ?? '';
$jenisTransaksi = $transaksi['jenis_transaksi'] ?? '';
$nomorSuratTugas = $transaksi['nomor_surat_tugas'] ?? '';
$tanggalSuratTugas = $transaksi['tanggal_surat_tugas'] ?? '';
$tanggalPelaksanaan = $transaksi['tanggal_pelaksanaan'] ?? '';
$lokasiKegiatan = $transaksi['lokasi_kegiatan'] ?? '';
$suratTugasRefId = $transaksi['surat_tugas_ref_id'] ?? '';

$errors = $validationErrors ?? [];
$formAction = $isEdit ? base_url('seksi/transaksi/update/' . $transaksi['id']) : base_url('seksi/transaksi/store');
?>

<style>
.form-section-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.form-section-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.9rem 1.25rem;
}
.form-section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.form-section-body {
    padding: 1.25rem;
}
.custom-form-select, .custom-form-input, .custom-form-textarea {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 0.875rem;
    padding: 0.55rem 0.75rem;
    transition: all 0.15s ease-in-out;
}
.custom-form-select:focus, .custom-form-input:focus, .custom-form-textarea:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    outline: none;
}
.custom-form-select:disabled, .custom-form-input:disabled {
    background-color: #f1f5f9;
    cursor: not-allowed;
    opacity: 0.8;
}
.sisa-pagu-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    margin-top: 0.4rem;
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.sisa-pagu-badge.warning {
    background: #fefce8;
    color: #854d0e;
    border-color: #fef08a;
}
.sisa-pagu-badge.danger {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}
.btn-auto-draft {
    font-size: 0.775rem;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-auto-draft:hover {
    background: #dbeafe;
    color: #1e40af;
}
.btn-surat-tugas {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-weight: 700;
    font-size: 0.825rem;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.15s ease;
}
.btn-surat-tugas:hover {
    background: #dcfce7;
    color: #166534;
}
.batch-item-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
    border-left: 4px solid #3b82f6;
}
.batch-item-card .btn-remove-item {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
}
.spin {
    display: inline-block;
    animation: spin 0.9s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.sticky-action-bar {
    position: sticky;
    bottom: 0;
    z-index: 1020;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 0.85rem 1.25rem;
    margin-top: 1.5rem;
    margin-left: -0.75rem;
    margin-right: -0.75rem;
    border-radius: 0 0 14px 14px;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.06);
}
#batchTotalNilai {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    font-weight: 700;
    font-size: 0.9rem;
    color: #0f172a;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
#batchTotalNilai .total-label {
    color: #64748b;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.cascade-spinner {
    font-size: 0.75rem;
    color: #3b82f6;
    margin-left: 0.4rem;
    vertical-align: middle;
}
.sisa-pagu-badge.unknown {
    background: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}
.field-highlight {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239,68,68,0.18) !important;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <!-- Header Halaman -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 style="font-weight:800;color:#0f172a;letter-spacing:-0.02em;margin-bottom:0.25rem;">
                    <i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' ?> text-primary me-2"></i>
                    <?= $isEdit ? 'Edit Transaksi' : 'Tambah Transaksi Baru' ?>
                </h3>
                <p class="text-muted mb-0" style="font-size:0.875rem;">Input rincian transaksi belanja atau kegiatan seksi Anda untuk diajukan ke admin.</p>
            </div>
            <a href="<?= base_url('seksi/transaksi') ?>" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 mb-3" style="border-radius:10px;">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Mohon perbaiki kesalahan berikut:</div>
                <ul class="mb-0 ps-3" style="font-size:0.875rem;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $formAction ?>" id="seksiTransaksiForm">
            <!-- Hidden field for Surat Tugas ID jika single -->
            <input type="hidden" name="surat_tugas_ref_id" id="surat_tugas_ref_id" value="<?= htmlspecialchars((string)$suratTugasRefId) ?>">

            <!-- SECTION 1: KLASIFIKASI ANGGARAN -->
            <div class="form-section-card">
                <div class="form-section-header">
                    <h5 class="form-section-title">
                        <span class="badge bg-primary text-white" style="font-size:0.75rem;padding:0.25rem 0.5rem;border-radius:4px;">1</span>
                        Klasifikasi Anggaran
                    </h5>
                </div>
                <div class="form-section-body">
                    <div class="row g-3">
                        <!-- Program -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                Program <span class="text-danger">*</span>
                            </label>
                            <select name="program_id" id="program_id" class="form-select custom-form-select" required>
                                <option value="">-- 1. Pilih Program --</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $programId == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['kode_program'] . ' - ' . $p['nama_program']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Kegiatan -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                Kegiatan <span class="text-danger">*</span>
                                <span id="spinner-kegiatan" class="cascade-spinner" style="display:none;"><i class="bi bi-arrow-repeat spin"></i> Memuat...</span>
                            </label>
                            <select name="kegiatan_id" id="kegiatan_id" class="form-select custom-form-select" required disabled>
                                <option value="">-- 2. Pilih Kegiatan --</option>
                            </select>
                        </div>

                        <!-- Sub Kegiatan -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                Sub Kegiatan <span class="text-danger">*</span>
                                <span id="spinner-sub_kegiatan" class="cascade-spinner" style="display:none;"><i class="bi bi-arrow-repeat spin"></i> Memuat...</span>
                            </label>
                            <select name="sub_kegiatan_id" id="sub_kegiatan_id" class="form-select custom-form-select" required disabled>
                                <option value="">-- 3. Pilih Sub Kegiatan --</option>
                            </select>
                        </div>

                        <!-- Rekening -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                Rekening Belanja <span class="text-danger">*</span>
                                <span id="spinner-rekening" class="cascade-spinner" style="display:none;"><i class="bi bi-arrow-repeat spin"></i> Memuat...</span>
                            </label>
                            <select name="rekening_id" id="rekening_id" class="form-select custom-form-select" required disabled>
                                <option value="">-- 4. Pilih Rekening Belanja --</option>
                            </select>
                            
                            <!-- Info Sisa Anggaran Badge -->
                            <div id="sisaPaguContainer" style="display:none;">
                                <div class="sisa-pagu-badge" id="sisaPaguBadge">
                                    <i class="bi bi-wallet2"></i>
                                    <span>Sisa Anggaran: <strong id="sisaPaguText">Rp 0</strong></span>
                                    <span class="text-muted" style="font-size:0.75rem;">(dari Pagu <span id="totalPaguText">Rp 0</span>)</span>
                                    <span id="spinner-sisa-pagu" class="cascade-spinner" style="display:none;"><i class="bi bi-arrow-repeat spin"></i></span>
                                </div>
                                <div class="sisa-pagu-badge unknown" id="sisaPaguError" style="display:none;">
                                    <i class="bi bi-exclamation-circle"></i>
                                    <span>Sisa anggaran tidak dapat dimuat — transaksi tetap bisa diajukan tanpa validasi otomatis.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: DETAIL TRANSAKSI & BKU -->
            <div class="form-section-card">
                <div class="form-section-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="form-section-title">
                        <span class="badge bg-primary text-white" style="font-size:0.75rem;padding:0.25rem 0.5rem;border-radius:4px;">2</span>
                        Detail Transaksi & BKU
                    </h5>
                    <?php if (!$isEdit): ?>
                        <button type="button" class="btn-surat-tugas" id="btnOpenModalST" style="<?= $jenisTransaksi === 'perjalanan_dinas' ? '' : 'display:none;' ?>">
                            <i class="bi bi-cloud-arrow-down-fill"></i>
                            Ambil dari Surat Tugas
                        </button>
                    <?php endif; ?>
                </div>
                <div class="form-section-body">
                    <!-- Global Tanggal Transaksi -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                Tanggal Transaksi <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control custom-form-input" value="<?= htmlspecialchars($tanggal) ?>" required>
                        </div>

                        <!-- Jenis Transaksi -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                Jenis Transaksi <span class="text-danger">*</span>
                            </label>
                            <select name="jenis_transaksi" id="jenis_transaksi" class="form-select custom-form-select" required <?= $isEdit ? 'disabled' : '' ?>>
                                <option value="" disabled <?= empty($jenisTransaksi) ? 'selected' : '' ?>>-- Pilih Jenis Transaksi --</option>
                                <option value="perjalanan_dinas" <?= $jenisTransaksi === 'perjalanan_dinas' ? 'selected' : '' ?>>Perjalanan Dinas</option>
                                <option value="belanja" <?= $jenisTransaksi === 'belanja' ? 'selected' : '' ?>>Belanja Barang / Jasa</option>
                                <option value="honorarium" <?= $jenisTransaksi === 'honorarium' ? 'selected' : '' ?>>Honorarium</option>
                                <option value="lainnya" <?= $jenisTransaksi === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                            </select>
                            <?php if ($isEdit): ?>
                                <input type="hidden" name="jenis_transaksi" value="<?= htmlspecialchars($jenisTransaksi) ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- BATCH ITEMS CONTAINER (Jika memilih beberapa pegawai dari Surat Tugas) -->
                    <div id="batchItemsContainer" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark" style="font-size:0.9rem;">
                                <i class="bi bi-people-fill text-primary me-1"></i> Rincian Transaksi per Pegawai (<span id="batchCountText">0</span> Orang)
                            </span>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="btnResetBatch" style="font-size:0.75rem;">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Kembali ke Mode 1 Transaksi
                            </button>
                        </div>
                        <div id="batchItemsList"></div>
                        <div id="batchTotalNilai" class="mt-3">
                            <span class="total-label">Total Nilai</span>
                            <span id="batchTotalNilaiText">Rp 0</span>
                        </div>
                    </div>

                    <!-- SINGLE ITEM FIELDS (Form Normal) -->
                    <div id="singleItemContainer">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                    Nomor Bukti / Kwitansi <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nomor_bukti" id="nomor_bukti" class="form-control custom-form-input" placeholder="Contoh: BKU/2026/001" value="<?= htmlspecialchars($nomorBukti) ?>" required>
                            </div>

                            <!-- Nama Penerima -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                    Nama Penerima / Pihak Ketiga
                                </label>
                                <input type="text" name="nama_penerima" id="nama_penerima" class="form-control custom-form-input" placeholder="Contoh: Budi Santoso, S.Hut" value="<?= htmlspecialchars($namaPenerima) ?>">
                                <small class="text-muted" style="font-size:0.75rem;">Nama pelaksana/toko/penerima pembayaran</small>
                            </div>

                            <!-- CONDITIONAL SECTION: SURAT TUGAS (KHUSUS PERJALANAN DINAS) -->
                            <div class="col-12" id="sectionPerjalananDinas" style="<?= $jenisTransaksi === 'perjalanan_dinas' ? '' : 'display:none;' ?>">
                                <div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:1rem;margin-top:0.25rem;">
                                    <div class="fw-bold text-primary mb-2" style="font-size:0.85rem;">
                                        <i class="bi bi-file-earmark-person me-1"></i> Data Surat Tugas (Perjalanan Dinas)
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label" style="font-size:0.8rem;font-weight:600;">
                                                Nomor Surat Tugas <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="nomor_surat_tugas" id="nomor_surat_tugas" class="form-control custom-form-input form-control-sm" placeholder="094/012/101.4/2026" value="<?= htmlspecialchars($nomorSuratTugas) ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" style="font-size:0.8rem;font-weight:600;">
                                                Tanggal Surat Tugas <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" name="tanggal_surat_tugas" id="tanggal_surat_tugas" class="form-control custom-form-input form-control-sm" value="<?= htmlspecialchars($tanggalSuratTugas) ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" style="font-size:0.8rem;font-weight:600;">
                                                Tanggal Pelaksanaan <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" name="tanggal_pelaksanaan" id="tanggal_pelaksanaan" class="form-control custom-form-input form-control-sm" value="<?= htmlspecialchars($tanggalPelaksanaan) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Uraian & Auto Draft Button -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold mb-0" style="font-size:0.85rem;">
                                        Uraian Transaksi <span class="text-danger">*</span>
                                    </label>
                                    <button type="button" class="btn-auto-draft" id="btnAutoDraft" style="<?= $jenisTransaksi === 'perjalanan_dinas' ? '' : 'display:none;' ?>">
                                        <i class="bi bi-magic me-1"></i>Buat draf uraian otomatis
                                    </button>
                                </div>
                                <textarea name="uraian" id="uraian" class="form-control custom-form-textarea" rows="3" placeholder="Uraian lengkap untuk pembukuan BKU..." required><?= htmlspecialchars($uraian) ?></textarea>
                                <small class="text-muted" style="font-size:0.75rem;">Uraian dapat diedit secara manual kapan saja.</small>
                            </div>

                            <!-- Nilai Transaksi -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" style="font-size:0.85rem;">
                                    Nilai Transaksi (Rp) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold" style="border-radius:8px 0 0 8px;font-size:0.875rem;">Rp</span>
                                    <input type="text" name="nilai" id="nilai" class="form-control custom-form-input" style="border-radius:0 8px 8px 0;" placeholder="0" value="<?= htmlspecialchars($nilai) ?>" required inputmode="numeric">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons (Sticky) -->
            <div class="sticky-action-bar d-flex align-items-center justify-content-end gap-2" id="stickyActionBar">
                <a href="<?= base_url('seksi/transaksi') ?>" class="btn btn-light px-4" style="border-radius:8px;font-weight:600;">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius:8px;font-weight:700;box-shadow:0 2px 6px rgba(37,99,235,0.3);">
                    <i class="bi <?= $isEdit ? 'bi-check-lg' : 'bi-send-fill' ?> me-1"></i>
                    <?= $isEdit ? 'Simpan Perubahan' : 'Ajukan Transaksi' ?>
                </button>
            </div>
            <!-- Spacer supaya konten terakhir tidak tertutup sticky bar -->
            <div style="height: 1.5rem;"></div>
        </form>
    </div>
</div>

<!-- MODAL AMBIL DARI SURAT TUGAS -->
<div class="modal fade" id="modalSuratTugas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" style="font-size:1rem;">
                    <i class="bi bi-file-earmark-text text-primary me-1"></i>
                    Pilih Surat Tugas & Pegawai
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Search & Filter Box -->
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="inputSearchST" class="form-control" placeholder="Cari nomor surat atau maksud kegiatan...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="filterBulanST" class="form-select">
                            <option value="">-- Semua Bulan --</option>
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100 fw-bold" type="button" id="btnSearchST">
                            <i class="bi bi-funnel me-1"></i>Cari
                        </button>
                    </div>
                </div>

                <!-- Alert/Status ST -->
                <div id="stAlertBox" style="display:none;" class="alert alert-warning py-2 mb-3 small"></div>

                <!-- STEP 1: Hasil Pencarian Surat Tugas -->
                <div id="step1ST">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-muted small text-uppercase">1. Pilih Surat Tugas:</span>
                        <span id="stLoadingText" class="text-primary small" style="display:none;"><i class="bi bi-hourglass-split me-1"></i>Memuat data...</span>
                    </div>
                    <div class="list-group" id="stResultList" style="max-height:220px;overflow-y:auto;">
                        <div class="text-center text-muted py-4 small">Ketik kata kunci atau klik Cari untuk menampilkan daftar surat tugas.</div>
                    </div>
                </div>

                <!-- STEP 2: Pilih Pegawai Ditugaskan -->
                <div id="step2ST" class="mt-4 pt-3 border-top" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-muted small text-uppercase">2. Pilih Pegawai (Bisa lebih dari 1 orang):</span>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnCheckAllPegawai">Centang Semua</button>
                    </div>
                    <div id="pegawaiListContainer" class="border rounded p-2 bg-light" style="max-height:200px;overflow-y:auto;"></div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="btnApplyST" disabled>
                    <i class="bi bi-check-circle me-1"></i>Buat Transaksi (<span id="countSelectedPegawai">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const selectedKegiatan = '<?= (int)$kegiatanId ?>';
const selectedSub = '<?= (int)$subKegiatanId ?>';
const selectedRekening = '<?= (int)$rekeningId ?>';
const isEditMode = <?= $isEdit ? 'true' : 'false' ?>;
const seksiBase = BASE_URL.replace(/\/$/, '');

let selectedSTData = null;
let currentSTPegawais = [];
window.currentSisaPagu = null;

function getSpinnerForSelect(selectEl) {
    const map = {
        'kegiatan_id': 'spinner-kegiatan',
        'sub_kegiatan_id': 'spinner-sub_kegiatan',
        'rekening_id': 'spinner-rekening'
    };
    const sid = map[selectEl.id];
    return sid ? document.getElementById(sid) : null;
}

function loadOptions(selectEl, url, keepValue, placeholder) {
    const spinner = getSpinnerForSelect(selectEl);
    selectEl.innerHTML = `<option value="">Memuat...</option>`;
    selectEl.disabled = true;
    if (spinner) spinner.style.display = 'inline-block';
    return fetch(url).then(r => r.json()).then(data => {
        let opts = `<option value="">${placeholder}</option>`;
        data.forEach(d => {
            let label = '';
            if (d.kode_rekening) label = d.kode_rekening + ' - ' + (d.nama_rekening || '');
            else if (d.kode_sub_kegiatan) label = d.kode_sub_kegiatan + ' - ' + (d.nama_sub_kegiatan || '');
            else if (d.kode_kegiatan) label = d.kode_kegiatan + ' - ' + (d.nama_kegiatan || '');
            opts += `<option value="${d.id}">${label}</option>`;
        });
        selectEl.innerHTML = opts;
        selectEl.disabled = false;
        if (keepValue) selectEl.value = keepValue;
        if (spinner) spinner.style.display = 'none';
        return data;
    }).catch(err => {
        selectEl.innerHTML = `<option value="">${placeholder}</option>`;
        selectEl.disabled = false;
        if (spinner) spinner.style.display = 'none';
        throw err;
    });
}

function updateSisaPagu() {
    const rekId = document.getElementById('rekening_id').value;
    const tglVal = document.getElementById('tanggal').value;
    const tahun = tglVal ? new Date(tglVal).getFullYear() : new Date().getFullYear();
    const container = document.getElementById('sisaPaguContainer');
    const badge = document.getElementById('sisaPaguBadge');
    const errorBadge = document.getElementById('sisaPaguError');
    const sisaText = document.getElementById('sisaPaguText');
    const paguText = document.getElementById('totalPaguText');
    const spinnerSisa = document.getElementById('spinner-sisa-pagu');

    if (!rekId) {
        container.style.display = 'none';
        window.currentSisaPagu = null;
        if (spinnerSisa) spinnerSisa.style.display = 'none';
        if (errorBadge) errorBadge.style.display = 'none';
        if (badge) badge.style.display = 'inline-flex';
        return;
    }

    container.style.display = 'block';
    // loading state for sisa pagu
    if (badge) badge.style.display = 'inline-flex';
    if (errorBadge) errorBadge.style.display = 'none';
    sisaText.innerText = 'Memuat...';
    paguText.innerText = 'Rp ...';
    badge.className = 'sisa-pagu-badge';
    if (spinnerSisa) spinnerSisa.style.display = 'inline-block';

    fetch(`${seksiBase}/seksi/transaksi/sisa-pagu?rekening_id=${rekId}&tahun=${tahun}`)
        .then(r => r.json())
        .then(res => {
            if (spinnerSisa) spinnerSisa.style.display = 'none';
            if (res.sisa_pagu !== null && res.sisa_pagu !== undefined) {
                window.currentSisaPagu = Number(res.sisa_pagu);
                container.style.display = 'block';
                if (errorBadge) errorBadge.style.display = 'none';
                if (badge) badge.style.display = 'inline-flex';
                sisaText.innerText = res.formatted_sisa || ('Rp ' + Number(res.sisa_pagu).toLocaleString('id-ID'));
                paguText.innerText = res.formatted_pagu || ('Rp ' + Number(res.pagu).toLocaleString('id-ID'));
                
                badge.className = 'sisa-pagu-badge';
                if (res.sisa_pagu < 0) {
                    badge.classList.add('danger');
                } else if (res.sisa_pagu < (res.pagu * 0.1)) {
                    badge.classList.add('warning');
                }
            } else {
                window.currentSisaPagu = null;
                container.style.display = 'none';
                if (errorBadge) errorBadge.style.display = 'none';
            }
        })
        .catch(() => {
            if (spinnerSisa) spinnerSisa.style.display = 'none';
            window.currentSisaPagu = null;
            container.style.display = 'block';
            if (badge) badge.style.display = 'none';
            if (errorBadge) errorBadge.style.display = 'inline-flex';
        });
}

function formatRibuanWithCursor(input) {
    const oldVal = input.value;
    let cursorPos = input.selectionStart;
    if (cursorPos === null || cursorPos === undefined) cursorPos = oldVal.length;
    let clean = oldVal.replace(/[^0-9]/g, '');
    if (clean) {
        // hitung jumlah digit sebelum kursor di value lama (abaikan titik)
        let digitsBeforeCursor = 0;
        for (let i = 0; i < cursorPos && i < oldVal.length; i++) {
            if (/[0-9]/.test(oldVal[i])) digitsBeforeCursor++;
        }
        // jika clean kosong setelah filter, kursor di 0
        // jika clean diawali 0 dan panjang >1, parseInt akan hilangkan leading zero — sesuaikan digitsBefore
        // tapi input numeric jarang pakai leading zero, abaikan edge ultra-rare
        const formatted = parseInt(clean, 10).toLocaleString('id-ID');
        input.value = formatted;
        // cari posisi di string baru yang punya digitsBeforeCursor digit dari kiri
        let newPos = formatted.length; // default akhir jika kursor di akhir
        if (digitsBeforeCursor === 0) {
            newPos = 0;
        } else {
            let counted = 0;
            for (let i = 0; i < formatted.length; i++) {
                if (/[0-9]/.test(formatted[i])) counted++;
                if (counted === digitsBeforeCursor) {
                    newPos = i + 1;
                    break;
                }
            }
        }
        if (newPos < 0) newPos = 0;
        if (newPos > formatted.length) newPos = formatted.length;
        try { input.setSelectionRange(newPos, newPos); } catch(e) {}
    } else {
        input.value = '';
    }
}

function parseNilaiToInt(str) {
    if (!str) return 0;
    const clean = String(str).replace(/[^0-9]/g, '');
    return clean ? parseInt(clean, 10) : 0;
}

function updateBatchTotal() {
    const inputs = document.querySelectorAll('.batch-nilai-input');
    let total = 0;
    inputs.forEach(inp => {
        total += parseNilaiToInt(inp.value);
    });
    const el = document.getElementById('batchTotalNilaiText');
    if (el) el.innerText = 'Rp ' + total.toLocaleString('id-ID');
    const container = document.getElementById('batchTotalNilai');
    if (container) container.style.display = inputs.length ? 'flex' : 'none';
}

// Cascade dropdowns
document.getElementById('program_id').addEventListener('change', function() {
    const programId = this.value;
    const keg = document.getElementById('kegiatan_id');
    const sub = document.getElementById('sub_kegiatan_id');
    const rek = document.getElementById('rekening_id');
    
    keg.innerHTML = '<option value="">-- 2. Pilih Kegiatan --</option>'; keg.disabled = true;
    sub.innerHTML = '<option value="">-- 3. Pilih Sub Kegiatan --</option>'; sub.disabled = true;
    rek.innerHTML = '<option value="">-- 4. Pilih Rekening Belanja --</option>'; rek.disabled = true;
    document.getElementById('sisaPaguContainer').style.display = 'none';

    if (programId) {
        loadOptions(keg, `${seksiBase}/seksi/transaksi/kegiatans?program_id=${programId}`, '', '-- 2. Pilih Kegiatan --');
    }
});

document.getElementById('kegiatan_id').addEventListener('change', function() {
    const kegiatanId = this.value;
    const sub = document.getElementById('sub_kegiatan_id');
    const rek = document.getElementById('rekening_id');

    sub.innerHTML = '<option value="">-- 3. Pilih Sub Kegiatan --</option>'; sub.disabled = true;
    rek.innerHTML = '<option value="">-- 4. Pilih Rekening Belanja --</option>'; rek.disabled = true;
    document.getElementById('sisaPaguContainer').style.display = 'none';

    if (kegiatanId) {
        loadOptions(sub, `${seksiBase}/seksi/transaksi/subkegiatans?kegiatan_id=${kegiatanId}`, '', '-- 3. Pilih Sub Kegiatan --');
    }
});

document.getElementById('sub_kegiatan_id').addEventListener('change', function() {
    const subKegiatanId = this.value;
    const rek = document.getElementById('rekening_id');

    rek.innerHTML = '<option value="">-- 4. Pilih Rekening Belanja --</option>'; rek.disabled = true;
    document.getElementById('sisaPaguContainer').style.display = 'none';

    if (subKegiatanId) {
        loadOptions(rek, `${seksiBase}/seksi/transaksi/rekenings?sub_kegiatan_id=${subKegiatanId}`, '', '-- 4. Pilih Rekening Belanja --');
    }
});

document.getElementById('rekening_id').addEventListener('change', updateSisaPagu);
document.getElementById('tanggal').addEventListener('change', function() {
    updateSisaPagu();
    if (!isEditMode) autoFillSingleNomorBukti(false);
});

// Toggle Jenis Transaksi & Field Perjalanan Dinas
document.getElementById('jenis_transaksi').addEventListener('change', function() {
    const isPerdin = this.value === 'perjalanan_dinas';
    document.getElementById('sectionPerjalananDinas').style.display = isPerdin ? 'block' : 'none';
    document.getElementById('btnAutoDraft').style.display = isPerdin ? 'inline-flex' : 'none';
    const btnST = document.getElementById('btnOpenModalST');
    if (btnST) btnST.style.display = isPerdin ? 'inline-flex' : 'none';
});

// Format ribuan nilai input single — dengan preservasi posisi kursor (Task 3)
document.getElementById('nilai').addEventListener('input', function(e) {
    formatRibuanWithCursor(this);
});

// Helper membersihkan duplikasi kata "Perjalanan Dinas dalam rangka"
function cleanMaksudKegiatan(text) {
    if (!text) return '';
    let cleaned = text.trim();
    // Hilangkan variasi awal seperti "Melaksanakan Perjalanan Dinas dalam rangka", "Perjalanan Dinas dalam rangka", "Melakukan Perjalanan Dinas..."
    cleaned = cleaned.replace(/^(melaksanakan|melakukan)?\s*perjalanan\s*dinas\s*(dalam\s*rangka)?\s*/i, '');
    // Hilangkan juga jika masih ada "dalam rangka" di awal
    cleaned = cleaned.replace(/^dalam\s*rangka\s*/i, '');
    // Hilangkan titik berlebih di akhir
    cleaned = cleaned.replace(/\.+$/, '');
    return cleaned.trim();
}

// Helper susun draf uraian
function buildDraftUraian(penerimaNama, tglPelaksanaan, tglST, nomorST, maksudKegiatan) {
    const subKegSelect = document.getElementById('sub_kegiatan_id');
    const subKegText = subKegSelect.selectedIndex > 0 ? subKegSelect.options[subKegSelect.selectedIndex].text : '[nama sub kegiatan]';
    const subKegNama = subKegText.replace(/^[0-9.]+\s*-\s*/, '');

    let tglPelaksanaFmt = '[tanggal_pelaksanaan]';
    if (tglPelaksanaan) {
        const parts = tglPelaksanaan.split('-');
        if (parts.length === 3) tglPelaksanaFmt = `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    let tglSTFmt = '[tanggal_surat_tugas]';
    if (tglST) {
        const parts = tglST.split('-');
        if (parts.length === 3) tglSTFmt = `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    const nomorSTVal = nomorST || '[nomor_surat_tugas]';
    const penerimaVal = penerimaNama || '[nama_penerima]';
    
    const maksudCleaned = cleanMaksudKegiatan(maksudKegiatan);
    const rangkaText = maksudCleaned ? maksudCleaned : `pelaksanaan kegiatan pada Sub Kegiatan ${subKegNama}`;

    return `Perjalanan Dinas dalam rangka ${rangkaText}. Pada tanggal ${tglPelaksanaFmt}, sesuai Surat Tugas No.: ${nomorSTVal} tanggal ${tglSTFmt}. An. ${penerimaVal}`;
}

// Auto generate nomor bukti untuk single form jika belum diisi
function autoFillSingleNomorBukti(force = false) {
    const inputNoBukti = document.getElementById('nomor_bukti');
    const tgl = document.getElementById('tanggal').value;
    const current = inputNoBukti.value.trim();
    if (!force && current && !current.startsWith('123.6.6/GU/')) {
        return;
    }
    fetch(`${seksiBase}/seksi/transaksi/generate-no-bukti?tanggal=${tgl}&count=1`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.nomor_bukti) {
                inputNoBukti.value = res.nomor_bukti;
            }
        });
}

// Buat Draf Uraian Otomatis (Single)
document.getElementById('btnAutoDraft').addEventListener('click', function() {
    const tglPelaksanaanRaw = document.getElementById('tanggal_pelaksanaan').value;
    const tglSuratRaw = document.getElementById('tanggal_surat_tugas').value;
    const nomorST = document.getElementById('nomor_surat_tugas').value.trim();
    const penerima = document.getElementById('nama_penerima').value.trim();

    const draft = buildDraftUraian(penerima, tglPelaksanaanRaw, tglSuratRaw, nomorST, '');
    const uraianEl = document.getElementById('uraian');
    uraianEl.value = draft;
    uraianEl.focus();
});

// =================== INTEGRASI MODAL SURAT TUGAS ===================
const modalSTEl = document.getElementById('modalSuratTugas');
let modalSTInstance = null;

const btnOpenST = document.getElementById('btnOpenModalST');
if (btnOpenST) {
    btnOpenST.addEventListener('click', function() {
        if (!modalSTInstance) {
            modalSTInstance = new bootstrap.Modal(modalSTEl);
        }
        modalSTInstance.show();
        searchSuratTugas('');
    });
}

document.getElementById('btnSearchST').addEventListener('click', function() {
    searchSuratTugas(document.getElementById('inputSearchST').value);
});
document.getElementById('inputSearchST').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') searchSuratTugas(this.value);
});
document.getElementById('filterBulanST').addEventListener('change', function() {
    searchSuratTugas(document.getElementById('inputSearchST').value);
});

function searchSuratTugas(keyword) {
    const resultList = document.getElementById('stResultList');
    const loadingText = document.getElementById('stLoadingText');
    const alertBox = document.getElementById('stAlertBox');
    const bulanVal = document.getElementById('filterBulanST').value;
    
    alertBox.style.display = 'none';
    loadingText.style.display = 'inline-block';
    resultList.innerHTML = '<div class="text-center text-muted py-3 small"><i class="bi bi-arrow-repeat spin"></i> Mencari surat tugas...</div>';

    let url = `${seksiBase}/seksi/transaksi/search-st?q=${encodeURIComponent(keyword)}`;
    if (bulanVal) {
        url += `&bulan=${bulanVal}`;
    }

    fetch(url)
        .then(r => r.json())
        .then(res => {
            loadingText.style.display = 'none';
            if (!res.success) {
                alertBox.innerText = res.message || 'Gagal memuat Surat Tugas.';
                alertBox.style.display = 'block';
                resultList.innerHTML = '<div class="text-center text-muted py-3 small">Layanan surat tugas tidak tersedia.</div>';
                return;
            }

            if (!res.data || res.data.length === 0) {
                resultList.innerHTML = '<div class="text-center text-muted py-3 small">Tidak ditemukan surat tugas yang sesuai.</div>';
                return;
            }

            let html = '';
            res.data.forEach(st => {
                const tglDisplay = st.tanggal_mulai ? (st.tanggal_selesai && st.tanggal_selesai !== st.tanggal_mulai ? `${st.tanggal_mulai} s.d. ${st.tanggal_selesai}` : st.tanggal_mulai) : (st.tanggal_surat || '-');
                html += `
                    <button type="button" class="list-group-item list-group-item-action item-st-select py-2" data-st='${JSON.stringify(st).replace(/'/g, "&apos;")}'>
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-primary" style="font-size:0.875rem;">No. ${st.nomor_surat || '-'}</strong>
                            <small class="text-muted"><i class="bi bi-calendar-event me-1"></i>${tglDisplay}</small>
                        </div>
                        <div class="small text-dark mt-1" style="line-height:1.35;">${st.untuk || '-'}</div>
                    </button>
                `;
            });
            resultList.innerHTML = html;

            document.querySelectorAll('.item-st-select').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.item-st-select').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    selectedSTData = JSON.parse(this.dataset.st);
                    loadPegawaiST(selectedSTData.id);
                });
            });
        })
        .catch(err => {
            loadingText.style.display = 'none';
            alertBox.innerText = 'Koneksi ke database surat tugas gagal atau terputus.';
            alertBox.style.display = 'block';
            resultList.innerHTML = '<div class="text-center text-muted py-3 small">Koneksi Surat Tugas gagal.</div>';
        });
}

function loadPegawaiST(idST) {
    const step2 = document.getElementById('step2ST');
    const container = document.getElementById('pegawaiListContainer');
    const btnApply = document.getElementById('btnApplyST');
    
    step2.style.display = 'block';
    container.innerHTML = '<div class="text-center text-muted py-2 small">Memuat pegawai...</div>';
    btnApply.disabled = true;
    document.getElementById('countSelectedPegawai').innerText = '0';

    fetch(`${seksiBase}/seksi/transaksi/pegawai-st?id_surat_tugas=${idST}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data || res.data.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-2 small">Tidak ada data pegawai pada surat tugas ini.</div>';
                return;
            }

            currentSTPegawais = res.data;
            let html = '';
            res.data.forEach((p, idx) => {
                html += `
                    <div class="form-check py-1 border-bottom">
                        <input class="form-check-input check-pegawai-st" type="checkbox" value="${idx}" id="chkPegawai_${idx}" checked>
                        <label class="form-check-label d-block cursor-pointer" for="chkPegawai_${idx}">
                            <strong>${p.nama}</strong>
                            <span class="text-muted small ms-1">(${p.nip || 'Non-NIP'}${p.jabatan ? ' · ' + p.jabatan : ''})</span>
                        </label>
                    </div>
                `;
            });
            container.innerHTML = html;
            updateSelectedPegawaiCount();

            document.querySelectorAll('.check-pegawai-st').forEach(chk => {
                chk.addEventListener('change', updateSelectedPegawaiCount);
            });
        });
}

function updateSelectedPegawaiCount() {
    const checked = document.querySelectorAll('.check-pegawai-st:checked');
    const count = checked.length;
    document.getElementById('countSelectedPegawai').innerText = count;
    document.getElementById('btnApplyST').disabled = (count === 0);
}

document.getElementById('btnCheckAllPegawai').addEventListener('click', function() {
    const checks = document.querySelectorAll('.check-pegawai-st');
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
    updateSelectedPegawaiCount();
    this.innerText = allChecked ? 'Centang Semua' : 'Hapus Semua Centang';
});

// Terapkan hasil Surat Tugas ke Form
document.getElementById('btnApplyST').addEventListener('click', function() {
    if (!selectedSTData) return;

    const checkedBoxes = Array.from(document.querySelectorAll('.check-pegawai-st:checked'));
    if (checkedBoxes.length === 0) return;

function setSingleItemActive(isActive) {
    const container = document.getElementById('singleItemContainer');
    if (!container) return;
    container.style.display = isActive ? 'block' : 'none';
    const fields = container.querySelectorAll('input, select, textarea');
    fields.forEach(el => {
        el.disabled = !isActive;
        if (!isActive) {
            if (el.hasAttribute('required')) {
                el.dataset.wasRequired = 'true';
                el.removeAttribute('required');
            }
        } else {
            if (el.dataset.wasRequired === 'true') {
                el.setAttribute('required', '');
            }
        }
    });
}

    const selectedPegawais = checkedBoxes.map(c => currentSTPegawais[parseInt(c.value, 10)]);

    // Baik 1 maupun banyak pegawai dari Surat Tugas, gunakan batch container agar rincian biaya SPJ selalu tersedia
    setSingleItemActive(false);
    document.getElementById('batchItemsContainer').style.display = 'block';
    document.getElementById('batchCountText').innerText = selectedPegawais.length;

    // Fetch batch nomor bukti
    const tgl = document.getElementById('tanggal').value || new Date().toISOString().slice(0, 10);
    fetch(`${seksiBase}/seksi/transaksi/generate-no-bukti?tanggal=${tgl}&count=${selectedPegawais.length}`)
        .then(r => r.json())
        .then(res => {
            const nomorList = res.success ? res.list : [];
            let batchHtml = '';
            selectedPegawais.forEach((p, idx) => {
                const draft = buildDraftUraian(p.nama, selectedSTData.tanggal_mulai, selectedSTData.tanggal_surat, selectedSTData.nomor_surat, selectedSTData.untuk);
                const noBukti = nomorList[idx] || '';
                batchHtml += `
                    <div class="batch-item-card" id="batchCard_${idx}">
                        <div class="fw-bold text-primary mb-2" style="font-size:0.875rem;">
                            <i class="bi bi-person-badge me-1"></i> #${idx+1} Transaksi an. ${p.nama}
                        </div>
                        <input type="hidden" name="items[${idx}][nama_penerima]" value="${p.nama}">
                        <input type="hidden" name="items[${idx}][pegawai_nip]" value="${p.nip || ''}">
                        <input type="hidden" name="items[${idx}][nomor_surat_tugas]" value="${selectedSTData.nomor_surat || ''}">
                        <input type="hidden" name="items[${idx}][tanggal_surat_tugas]" value="${selectedSTData.tanggal_surat || ''}">
                        <input type="hidden" name="items[${idx}][tanggal_pelaksanaan]" value="${selectedSTData.tanggal_mulai || ''}">
                        <input type="hidden" name="items[${idx}][surat_tugas_ref_id]" value="${selectedSTData.id || ''}">
                        
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nomor Bukti / Kwitansi <span class="text-danger">*</span></label>
                                <input type="text" name="items[${idx}][nomor_bukti]" class="form-control custom-form-input form-control-sm" value="${noBukti}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                    <span>Nilai (Rp) <span class="text-danger">*</span></span>
                                    <span class="text-primary fw-normal" style="font-size:0.7rem;"><i class="bi bi-magic me-1"></i>Otomatis dari Rincian</span>
                                </label>
                                <input type="text" name="items[${idx}][nilai]" class="form-control custom-form-input form-control-sm batch-nilai-input" placeholder="0" required inputmode="numeric" readonly style="background-color: #f8fafc; font-weight: 600;" title="Otomatis terisi dari Total Rincian Biaya">
                            </div>
                            <div class="col-12 mt-2">
                                <label class="form-label small fw-semibold">Uraian Transaksi <span class="text-danger">*</span></label>
                                <textarea name="items[${idx}][uraian]" class="form-control custom-form-textarea form-control-sm" rows="2" required>${draft}</textarea>
                            </div>
                        </div>

                        <!-- ── Sub-section Rincian Biaya SPJ ───────────────── -->
                        <div class="rincian-biaya-section mt-3">
                            <div class="rincian-biaya-header" onclick="toggleRincianBiaya(this)">
                                <span><i class="bi bi-receipt-cutoff me-1"></i>Rincian Biaya Perjalanan Dinas</span>
                                <span class="rincian-toggle-icon"><i class="bi bi-chevron-down"></i></span>
                            </div>
                            <div class="rincian-biaya-body">
                                <!-- Komponen biaya baris -->
                                <div class="rb-col-header d-none d-md-flex mb-1">
                                    <div style="flex:2">Nama Komponen</div>
                                    <div style="flex:1.5">Harga Satuan (Rp)</div>
                                    <div style="flex:.8">Hari</div>
                                    <div style="flex:1.5">Jumlah (Rp)</div>
                                    <div style="flex:1.5">Keterangan</div>
                                    <div style="flex:.4"></div>
                                </div>
                                <div class="rb-rows" id="rbRows_${idx}">
                                    ${buildDefaultKomponenRows(idx)}
                                </div>
                                <button type="button" class="btn-rb-add mt-2" onclick="addRbRow(${idx})">
                                    <i class="bi bi-plus-circle me-1"></i>Tambah Baris
                                </button>
                                <!-- Total -->
                                <div class="rb-total-row mt-2 d-flex justify-content-between align-items-center">
                                    <span class="text-success fw-semibold" style="font-size:.8rem;"><i class="bi bi-sigma me-1"></i>Total Rincian:</span>
                                    <span class="fw-bold text-success" id="rbTotal_${idx}" style="font-size:.9rem;">Rp 0</span>
                                </div>
                                <!-- SPPD Rampung -->
                                <div class="row g-2 mt-2">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold mb-1">Ditetapkan Sejumlah (Rp)</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light" style="font-size:.75rem;">Rp</span>
                                            <input type="text" name="items[${idx}][ditetapkan_sejumlah]"
                                                class="form-control form-control-sm custom-form-input rb-rp-input"
                                                placeholder="0" inputmode="numeric"
                                                oninput="onRbSppdInput(this, ${idx})">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold mb-1">Dibayar Semula (Rp)</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light" style="font-size:.75rem;">Rp</span>
                                            <input type="text" name="items[${idx}][dibayar_semula]"
                                                class="form-control form-control-sm custom-form-input rb-rp-input"
                                                placeholder="0" inputmode="numeric"
                                                oninput="onRbSppdInput(this, ${idx})">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold mb-1">Sisa Kurang/Lebih</label>
                                        <div class="rb-sisa-box" id="rbSisa_${idx}">—</div>
                                    </div>
                                </div>
                                <!-- Tempat & Tanggal -->
                                <div class="mt-2">
                                    <label class="form-label small fw-semibold mb-1">Tempat &amp; Tanggal (cetak)</label>
                                    <input type="text" name="items[${idx}][tempat_tanggal]"
                                        class="form-control form-control-sm custom-form-input"
                                        placeholder="Bojonegoro, 31 Agustus 2026"
                                        value="Bojonegoro, ${todayStr()}">
                                </div>
                            </div><!-- /rincian-biaya-body -->
                        </div><!-- /rincian-biaya-section -->
                    </div>
                `;
            });
            document.getElementById('batchItemsList').innerHTML = batchHtml;

            // Pasang format ribuan di batch nilai inputs + update total (Task 3 & 5)
            document.querySelectorAll('.batch-nilai-input').forEach(inp => {
                inp.addEventListener('input', function() {
                    formatRibuanWithCursor(this);
                    updateBatchTotal();
                });
            });
            updateBatchTotal();
        });

    if (modalSTInstance) modalSTInstance.hide();
});

// Reset batch ke mode 1 transaksi
document.getElementById('btnResetBatch').addEventListener('click', function() {
    if (confirm('Kembali ke mode pengisian 1 transaksi biasa? Data rincian multi-pegawai akan dikosongkan.')) {
        document.getElementById('batchItemsContainer').style.display = 'none';
        document.getElementById('batchItemsList').innerHTML = '';
        setSingleItemActive(true);
        updateBatchTotal();
    }
});

// Task 2+ C: Validasi nilai vs sisa pagu sebelum submit (WARNING confirm) + scroll/focus/highlight saat cancel
function highlightField(el) {
    if (!el) return;
    el.classList.add('field-highlight');
    setTimeout(() => el.classList.remove('field-highlight'), 2000);
}
document.getElementById('seksiTransaksiForm').addEventListener('submit', function(e) {
    const batchContainer = document.getElementById('batchItemsContainer');
    const isBatchMode = batchContainer && batchContainer.style.display !== 'none' && document.querySelectorAll('.batch-nilai-input').length > 0;

    // Pastikan single inputs di-disable dan unrequired saat mode batch agar browser tidak komplain form control is not focusable
    setSingleItemActive(!isBatchMode);

    if (window.currentSisaPagu === null || window.currentSisaPagu === undefined) return;
    const sisa = Number(window.currentSisaPagu);
    if (isNaN(sisa)) return;

    let totalNilai = 0;
    if (isBatchMode) {
        document.querySelectorAll('.batch-nilai-input').forEach(inp => {
            totalNilai += parseNilaiToInt(inp.value);
        });
    } else {
        const nilaiEl = document.getElementById('nilai');
        totalNilai = parseNilaiToInt(nilaiEl ? nilaiEl.value : '0');
    }

    if (totalNilai === 0) return; // biar required yang handle
    if (totalNilai > sisa) {
        const sisaFmt = 'Rp ' + Number(sisa).toLocaleString('id-ID');
        const totalFmt = 'Rp ' + Number(totalNilai).toLocaleString('id-ID');
        const msg = `Nilai transaksi (${totalFmt}) melebihi sisa anggaran (${sisaFmt}). Tetap ajukan?`;
        if (!confirm(msg)) {
            e.preventDefault();
            // Task C: scroll & focus ke field nilai relevan + highlight
            let targetEl = null;
            if (isBatchMode) {
                const batchInputs = document.querySelectorAll('.batch-nilai-input');
                targetEl = batchInputs.length ? batchInputs[0] : null;
            } else {
                targetEl = document.getElementById('nilai');
            }
            if (targetEl) {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => targetEl.focus(), 300);
                highlightField(targetEl);
            }
        }
    }
});

// Preload saat Edit
document.addEventListener('DOMContentLoaded', function() {
    const programId = document.getElementById('program_id').value;
    if (programId) {
        loadOptions(document.getElementById('kegiatan_id'), `${seksiBase}/seksi/transaksi/kegiatans?program_id=${programId}`, selectedKegiatan, '-- 2. Pilih Kegiatan --')
            .then(() => selectedKegiatan && loadOptions(document.getElementById('sub_kegiatan_id'), `${seksiBase}/seksi/transaksi/subkegiatans?kegiatan_id=${selectedKegiatan}`, selectedSub, '-- 3. Pilih Sub Kegiatan --'))
            .then(() => selectedSub && loadOptions(document.getElementById('rekening_id'), `${seksiBase}/seksi/transaksi/rekenings?sub_kegiatan_id=${selectedSub}`, selectedRekening, '-- 4. Pilih Rekening Belanja --'))
            .then(() => {
                if (selectedRekening) updateSisaPagu();
            });
    }

    // Auto isi nomor bukti saat tambah transaksi baru
    if (!isEditMode) {
        autoFillSingleNomorBukti(false);
    }

    // Format nilai awal jika ada
    const nilaiEl = document.getElementById('nilai');
    if (nilaiEl && nilaiEl.value) {
        let clean = nilaiEl.value.replace(/[^0-9]/g, '');
        if (clean) nilaiEl.value = parseInt(clean, 10).toLocaleString('id-ID');
    }
});

// ── Rincian Biaya SPJ — helpers ──────────────────────────────────────────

function todayStr() {
    const d = new Date();
    const bl = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return `${d.getDate()} ${bl[d.getMonth()+1]} ${d.getFullYear()}`;
}

const DEFAULT_KOMPONEN = [
    { nama: 'Uang Harian', hari: '1' },
    { nama: 'BBM',         hari: '' },
    { nama: 'Tol',         hari: '' },
    { nama: 'Hotel',       hari: '1' },
];

function buildDefaultKomponenRows(itemIdx) {
    return DEFAULT_KOMPONEN.map((k, r) => buildRbRow(itemIdx, r, k.nama, k.hari)).join('');
}

function buildRbRow(itemIdx, rowIdx, namaVal = '', hariVal = '') {
    return `<div class="rb-row d-flex flex-wrap gap-1 align-items-center mb-1" data-item="${itemIdx}" data-row="${rowIdx}">
        <input type="text"   name="items[${itemIdx}][komponen][${rowIdx}][nama_komponen]"
               class="form-control form-control-sm custom-form-input" style="flex:2;min-width:110px;"
               placeholder="Komponen" value="${namaVal}">
        <div class="input-group input-group-sm" style="flex:1.5;min-width:100px;">
            <span class="input-group-text bg-light" style="font-size:.7rem;padding:.2rem .35rem;">Rp</span>
            <input type="text" name="items[${itemIdx}][komponen][${rowIdx}][harga_satuan]"
                   class="form-control form-control-sm custom-form-input rb-harga"
                   placeholder="0" inputmode="numeric"
                   oninput="recalcRbRow(this)">
        </div>
        <input type="number" name="items[${itemIdx}][komponen][${rowIdx}][jumlah_hari]"
               class="form-control form-control-sm custom-form-input rb-hari"
               placeholder="Hari" value="${hariVal}" min="0" step="0.5" style="flex:.8;min-width:60px;"
               oninput="recalcRbRow(this)">
        <div class="input-group input-group-sm" style="flex:1.5;min-width:100px;">
            <span class="input-group-text bg-light" style="font-size:.7rem;padding:.2rem .35rem;">Rp</span>
            <input type="text" name="items[${itemIdx}][komponen][${rowIdx}][jumlah]"
                   class="form-control form-control-sm custom-form-input rb-jumlah"
                   placeholder="0" inputmode="numeric"
                   oninput="onRbJumlahInput(this, ${itemIdx})">
        </div>
        <input type="text"   name="items[${itemIdx}][komponen][${rowIdx}][keterangan]"
               class="form-control form-control-sm custom-form-input"
               placeholder="Ket." style="flex:1.5;min-width:90px;">
        <button type="button" class="btn btn-link text-danger p-0" style="flex:.4;font-size:.9rem;"
                onclick="removeRbRow(this,${itemIdx})" title="Hapus baris">
            <i class="bi bi-x-circle"></i>
        </button>
    </div>`;
}

function parseRbRp(val) {
    return parseFloat(String(val || '').replace(/\./g, '').replace(',', '.')) || 0;
}
function fmtRbRp(el)   { const v = parseRbRp(el.value); if (v > 0) el.value = Math.floor(v).toLocaleString('id-ID'); else el.value = ''; }
function unfmtRbRp(el) { const v = parseRbRp(el.value); el.value = v > 0 ? String(Math.floor(v)) : ''; }

function recalcRbRow(el) {
    const row = el.closest('.rb-row');
    if (!row) return;
    const itemIdx = parseInt(row.dataset.item, 10);
    const hargaEl = row.querySelector('.rb-harga');
    const hariEl  = row.querySelector('.rb-hari');
    const jumlahEl = row.querySelector('.rb-jumlah');

    if (el === hargaEl) {
        formatRibuanWithCursor(hargaEl);
    }

    const harga = parseRbRp(hargaEl.value);
    const hariVal = hariEl ? hariEl.value.trim() : '';
    const hari = parseFloat(hariVal) || 0;

    if (harga > 0) {
        const hasil = (hariVal !== '' && hari > 0) ? harga * hari : harga;
        jumlahEl.value = Math.floor(hasil).toLocaleString('id-ID');
    } else if (hargaEl.value === '') {
        jumlahEl.value = '';
    }
    updateRbTotal(itemIdx);
}

function onRbJumlahInput(el, itemIdx) {
    formatRibuanWithCursor(el);
    updateRbTotal(itemIdx);
}

function onRbSppdInput(el, itemIdx) {
    formatRibuanWithCursor(el);
    hitungSisaSppdBatch(itemIdx);
}

function updateRbTotal(itemIdx) {
    const rows = document.querySelectorAll(`#rbRows_${itemIdx} .rb-jumlah`);
    let total = 0;
    rows.forEach(el => total += parseRbRp(el.value));

    // Update label Total Rincian
    const totalEl = document.getElementById(`rbTotal_${itemIdx}`);
    if (totalEl) {
        totalEl.textContent = 'Rp ' + Math.floor(total).toLocaleString('id-ID');
    }

    const card = document.getElementById(`batchCard_${itemIdx}`);
    if (card) {
        const formattedTotal = total > 0 ? Math.floor(total).toLocaleString('id-ID') : '0';
        const formattedValOrEmpty = total > 0 ? Math.floor(total).toLocaleString('id-ID') : '';

        // 1. Nilai transaksi otomatis dari total rincian
        const nilaiInput = card.querySelector(`[name="items[${itemIdx}][nilai]"]`);
        if (nilaiInput) {
            nilaiInput.value = formattedTotal;
        }

        // 2. Ditetapkan sejumlah otomatis dari total rincian
        const detInput = card.querySelector(`[name="items[${itemIdx}][ditetapkan_sejumlah]"]`);
        if (detInput) {
            detInput.value = formattedValOrEmpty;
        }

        // 3. Dibayarkan semula otomatis dari total rincian
        const dibInput = card.querySelector(`[name="items[${itemIdx}][dibayar_semula]"]`);
        if (dibInput) {
            dibInput.value = formattedValOrEmpty;
        }
    }

    // 4. Hitung sisa SPPD Rampung
    hitungSisaSppdBatch(itemIdx);

    // 5. Update akumulasi grand total batch
    updateBatchTotal();
}

function hitungSisaSppdBatch(itemIdx) {
    const card = document.getElementById(`batchCard_${itemIdx}`);
    if (!card) return;
    const detInput = card.querySelector(`[name="items[${itemIdx}][ditetapkan_sejumlah]"]`);
    const dibInput = card.querySelector(`[name="items[${itemIdx}][dibayar_semula]"]`);
    const detVal = detInput ? detInput.value.trim() : '';
    const dibVal = dibInput ? dibInput.value.trim() : '';
    const det    = parseRbRp(detVal);
    const dib    = parseRbRp(dibVal);
    const el     = document.getElementById(`rbSisa_${itemIdx}`);
    if (!el) return;

    if (!detVal && !dibVal) {
        el.textContent = '—';
        el.className = 'rb-sisa-box';
        return;
    }

    const sisa = det - dib;
    if (sisa === 0) {
        el.textContent = 'Rp 0';
        el.className   = 'rb-sisa-box rb-sisa-pas';
    } else if (sisa < 0) {
        el.textContent = '– Rp ' + Math.floor(Math.abs(sisa)).toLocaleString('id-ID');
        el.className   = 'rb-sisa-box rb-sisa-kurang';
    } else {
        el.textContent = '+ Rp ' + Math.floor(sisa).toLocaleString('id-ID');
        el.className   = 'rb-sisa-box rb-sisa-lebih';
    }
}

function addRbRow(itemIdx) {
    const container = document.getElementById(`rbRows_${itemIdx}`);
    if (!container) return;
    const existingRows = container.querySelectorAll('.rb-row').length;
    const newHtml = buildRbRow(itemIdx, existingRows);
    container.insertAdjacentHTML('beforeend', newHtml);
}

function removeRbRow(btn, itemIdx) {
    const row = btn.closest('.rb-row');
    if (!row) return;
    row.remove();
    // Re-index name attrs
    const container = document.getElementById(`rbRows_${itemIdx}`);
    container.querySelectorAll('.rb-row').forEach((r, i) => {
        r.dataset.row = i;
        r.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(
                /items\[(\d+)\]\[komponen\]\[\d+\]/,
                `items[$1][komponen][${i}]`
            );
        });
    });
    updateRbTotal(itemIdx);
}

function toggleRincianBiaya(headerEl) {
    const body = headerEl.nextElementSibling;
    const icon = headerEl.querySelector('.rincian-toggle-icon i');
    const isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : 'block';
    if (icon) icon.className = isOpen ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
}
</script>

<style>
/* ── Rincian Biaya SPJ — styles ─────────────────────────────── */
.rincian-biaya-section {
    border: 1px solid #e0e7ff;
    border-radius: 10px;
    overflow: hidden;
    background: #fafbff;
}
.rincian-biaya-header {
    background: linear-gradient(90deg, #ede9fe 0%, #e0e7ff 100%);
    padding: .55rem .85rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .82rem;
    font-weight: 700;
    color: #3730a3;
    user-select: none;
}
.rincian-biaya-header:hover { background: #ddd6fe; }
.rincian-biaya-body { padding: .75rem .85rem; }
.rb-col-header {
    font-size: .68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
    gap: .4rem;
}
.rb-row { gap: .35rem; }
.btn-rb-add {
    background: #eff6ff;
    border: 1px dashed #93c5fd;
    color: #1d4ed8;
    border-radius: 7px;
    padding: .3rem .75rem;
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
}
.btn-rb-add:hover { background: #dbeafe; }
.rb-total-row {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: .4rem .75rem;
}
.rb-sisa-box {
    background: #f1f5f9;
    border-radius: 6px;
    padding: .35rem .65rem;
    font-size: .82rem;
    font-weight: 700;
}
.rb-sisa-kurang { background: #fef2f2; color: #dc2626; }
.rb-sisa-lebih  { background: #f0fdf4; color: #059669; }
.rb-sisa-pas    { background: #eff6ff; color: #4f46e5; }
</style>
