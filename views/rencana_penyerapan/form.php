<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isEdit = ($action ?? 'store') === 'update' && !empty($form['id']);
$errors = $errors ?? [];
$formAction = $isEdit
    ? base_url('rencana-penyerapan/update/' . (int) $form['id'])
    : base_url('rencana-penyerapan/store');

$bulanFull = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulanShort = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$nilaiDisplay = ((float) $form['nilai_rencana']) > 0 ? number_format((float) $form['nilai_rencana'], 0, ',', '.') : '';
$mekanisme = $form['mekanisme'] ?? 'GU';

$bentukSuggestions = [
    'Pengadaan Barang',
    'Pengadaan Bibit',
    'Pengadaan Jasa',
    'Pengadaan Jasa Konsultansi',
    'Belanja Modal',
    'Perjalanan Dinas',
    'Honorarium / Narasumber',
    'Belanja ATK',
    'Sewa',
    'Pemeliharaan',
    'Lainnya',
];
?>

<style>
    .rp-form-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
    }

    .rp-form-card .card-header {
        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        border-bottom: 1px solid #e2e8f0;
        border-radius: 16px 16px 0 0 !important;
        padding: 14px 18px;
    }

    .rp-form-card .card-header h6 {
        margin: 0;
        font-weight: 800;
        color: #0f172a;
    }

    .rp-section-label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #4338ca;
        margin-bottom: 8px;
    }

    .rp-mek-options {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .rp-mek-option {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 11px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        background: #fff;
        height: 100%;
    }

    .rp-mek-option:hover { border-color: #c7d2fe; background: #f8faff; }

    .rp-mek-option:has(input:checked) {
        border-color: #4338ca;
        background: #eef2ff;
        box-shadow: 0 0 0 3px rgba(67, 56, 202, .10);
    }

    .rp-mek-option input {
        margin-top: 3px;
        flex: 0 0 auto;
    }

    .rp-mek-option strong {
        display: block;
        font-size: .8rem;
        color: #0f172a;
    }

    .rp-mek-option small {
        display: block;
        font-size: .69rem;
        color: #64748b;
        line-height: 1.35;
        margin-top: 2px;
    }

    .rp-mek {
        display: inline-flex;
        align-items: center;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .04em;
        padding: 2px 7px;
        border-radius: 999px;
        line-height: 1.4;
        white-space: nowrap;
    }

    .rp-mek--GU { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
    .rp-mek--TU { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .rp-mek--LS { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

    .rp-side-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
        overflow: hidden;
        position: sticky;
        top: 16px;
    }

    .rp-side-head {
        padding: 13px 16px;
        background: linear-gradient(135deg, #0f172a 0%, #312e81 100%);
        color: #fff;
    }

    .rp-side-head h6 { margin: 0; font-size: .85rem; font-weight: 800; }
    .rp-side-head small { color: #c7d2fe; font-size: .7rem; }

    .rp-side-body { padding: 14px 16px; }

    .rp-info-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px;
        padding: 6px 0;
        border-bottom: 1px dashed #e2e8f0;
        font-size: .78rem;
    }

    .rp-info-row:last-child { border-bottom: 0; }
    .rp-info-row span { color: #64748b; }
    .rp-info-row strong { color: #0f172a; text-align: right; }

    .rp-preview {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        min-height: 34px;
        border-radius: 8px;
        background: #f8fafc;
        overflow: hidden;
        position: relative;
    }

    .rp-preview-cell {
        grid-row: 1;
        align-self: stretch;
        min-height: 34px;
        border-left: 1px solid #eef2f7;
    }

    .rp-preview-cell:nth-child(3n+1) { border-left: 2px solid #e2e8f0; }

    .rp-preview-bar {
        grid-row: 1;
        align-self: center;
        height: 22px;
        border-radius: 6px;
        margin: 0 2px;
        z-index: 2;
        transition: grid-column .15s ease;
    }

    .rp-preview-bar.rp-bar--GU { background: linear-gradient(90deg, #4338ca, #6366f1); }
    .rp-preview-bar.rp-bar--TU { background: linear-gradient(90deg, #047857, #10b981); }
    .rp-preview-bar.rp-bar--LS { background: linear-gradient(90deg, #b45309, #f59e0b); }

    .rp-preview-labels {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        margin-top: 3px;
    }

    .rp-preview-labels span {
        text-align: center;
        font-size: .6rem;
        color: #94a3b8;
        font-weight: 700;
    }

    .rp-preview-cell--past { background: rgba(148, 163, 184, .13); }

    .rp-mini-real {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        min-height: 34px;
        border-radius: 8px;
        background: #f8fafc;
        overflow: hidden;
    }

    .rp-mini-cell {
        position: relative;
        min-height: 34px;
        border-left: 1px solid #eef2f7;
    }

    .rp-mini-cell:nth-child(3n+1) { border-left: 2px solid #e2e8f0; }

    .rp-mini-cell--past { background: rgba(148, 163, 184, .13); }

    .rp-mini-bar {
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 62%;
        border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, #34d399, #059669);
        box-shadow: 0 1px 3px rgba(15, 23, 42, .2);
    }

    .rp-side-actions {
        display: flex;
        gap: 8px;
        padding: 0 16px 16px;
    }

    @media (max-width: 767.98px) {
        .rp-mek-options { grid-template-columns: 1fr; }
        .rp-side-card { position: static; }
    }
</style>

<div class="container-fluid py-4">
    <div class="page-header mb-4">
        <h2 class="mb-1">
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?> text-primary me-2"></i><?= htmlspecialchars($pageTitle ?? 'Rencana Penyerapan') ?>
        </h2>
        <p class="text-muted mb-0"><?= htmlspecialchars($subtitle ?? '') ?></p>
    </div>

    <?php if (!empty($errors['umum'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errors['umum']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $formAction ?>" id="rpForm" novalidate>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="rp-form-card mb-4">
                    <div class="card-header">
                        <h6><i class="bi bi-diagram-3 text-primary me-1"></i>Rekening Sasaran</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="program_id" class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                                <select class="form-select" id="program_id" name="program_id" required>
                                    <option value="">-- Pilih Program --</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?= (int) $program['id'] ?>" <?= $form['program_id'] == $program['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($program['kode_program'] . ' - ' . $program['nama_program'] . ' (' . $program['tahun'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="kegiatan_id" class="form-label fw-semibold">Kegiatan <span class="text-danger">*</span></label>
                                <select class="form-select" id="kegiatan_id" name="kegiatan_id" required <?= empty($form['program_id']) ? 'disabled' : '' ?>>
                                    <option value="">-- Pilih Kegiatan --</option>
                                    <?php foreach ($kegiatans as $kegiatan): ?>
                                        <option value="<?= (int) $kegiatan['id'] ?>" <?= $form['kegiatan_id'] == $kegiatan['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kegiatan['kode_kegiatan'] . ' - ' . $kegiatan['nama_kegiatan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sub_kegiatan_id" class="form-label fw-semibold">Sub Kegiatan <span class="text-danger">*</span></label>
                                <select class="form-select" id="sub_kegiatan_id" name="sub_kegiatan_id" required <?= empty($form['kegiatan_id']) ? 'disabled' : '' ?>>
                                    <option value="">-- Pilih Sub Kegiatan --</option>
                                    <?php foreach ($subKegiatans as $subKegiatan): ?>
                                        <option value="<?= (int) $subKegiatan['id'] ?>" <?= $form['sub_kegiatan_id'] == $subKegiatan['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subKegiatan['kode_sub_kegiatan'] . ' - ' . $subKegiatan['nama_sub_kegiatan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="rekening_id" class="form-label fw-semibold">Rekening <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['rekening_id']) ? 'is-invalid' : '' ?>" id="rekening_id" name="rekening_id" required <?= empty($form['sub_kegiatan_id']) ? 'disabled' : '' ?>>
                                    <option value="">-- Pilih Rekening --</option>
                                    <?php foreach ($rekenings as $rekening): ?>
                                        <option value="<?= (int) $rekening['id'] ?>" <?= $form['rekening_id'] == $rekening['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rekening['kode_rekening'] . ' - ' . $rekening['nama_rekening']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['rekening_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['rekening_id']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="tahun" class="form-label fw-semibold">Tahun Anggaran <span class="text-danger">*</span></label>
                                <input type="number" class="form-control <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>" id="tahun" name="tahun"
                                       value="<?= (int) $form['tahun'] ?>" min="2000" max="2100" required>
                                <?php if (isset($errors['tahun'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['tahun']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rp-form-card mb-4">
                    <div class="card-header">
                        <h6><i class="bi bi-clipboard-check text-primary me-1"></i>Detail Rencana Kegiatan</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="bentuk_kegiatan" class="form-label fw-semibold">Bentuk Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['bentuk_kegiatan']) ? 'is-invalid' : '' ?>"
                                   id="bentuk_kegiatan" name="bentuk_kegiatan" list="bentukKegiatanList"
                                   value="<?= htmlspecialchars((string) $form['bentuk_kegiatan']) ?>"
                                   placeholder="Contoh: Pengadaan Bibit" maxlength="150" required>
                            <datalist id="bentukKegiatanList">
                                <?php foreach ($bentukSuggestions as $suggestion): ?>
                                    <option value="<?= htmlspecialchars($suggestion) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (isset($errors['bentuk_kegiatan'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['bentuk_kegiatan']) ?></div>
                            <?php endif; ?>
                            <div class="form-text">Contoh: Pengadaan Barang, Pengadaan Bibit, Perjalanan Dinas, Honorarium.</div>
                        </div>

                        <div class="mb-3">
                            <label for="uraian" class="form-label fw-semibold">Uraian Kegiatan</label>
                            <textarea class="form-control <?= isset($errors['uraian']) ? 'is-invalid' : '' ?>"
                                      id="uraian" name="uraian" rows="2" maxlength="500"
                                      placeholder="Detail singkat kegiatan yang akan dilaksanakan"><?= htmlspecialchars((string) $form['uraian']) ?></textarea>
                            <?php if (isset($errors['uraian'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['uraian']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mekanisme Pembayaran <span class="text-danger">*</span></label>
                            <div class="rp-mek-options">
                                <label class="rp-mek-option">
                                    <input type="radio" name="mekanisme" value="GU" <?= $mekanisme === 'GU' ? 'checked' : '' ?>>
                                    <span>
                                        <span class="rp-mek rp-mek--GU mb-1">GU</span>
                                        <strong>Ganti Uang</strong>
                                        <small>Belanja UP yang di-SPJ-kan, lalu kas kembali utuh.</small>
                                    </span>
                                </label>
                                <label class="rp-mek-option">
                                    <input type="radio" name="mekanisme" value="TU" <?= $mekanisme === 'TU' ? 'checked' : '' ?>>
                                    <span>
                                        <span class="rp-mek rp-mek--TU mb-1">TU</span>
                                        <strong>Tambah Uang</strong>
                                        <small>Pengajuan dana TU, dibayarkan, lalu sisa disetor.</small>
                                    </span>
                                </label>
                                <label class="rp-mek-option">
                                    <input type="radio" name="mekanisme" value="LS" <?= $mekanisme === 'LS' ? 'checked' : '' ?>>
                                    <span>
                                        <span class="rp-mek rp-mek--LS mb-1">LS</span>
                                        <strong>Langsung</strong>
                                        <small>Dibayar langsung Kas Daerah ke rekanan.</small>
                                    </span>
                                </label>
                            </div>
                            <?php if (isset($errors['mekanisme'])): ?>
                                <div class="text-danger small mt-1"><?= htmlspecialchars($errors['mekanisme']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-sm-4">
                                <label for="bulan_mulai" class="form-label fw-semibold">Bulan Mulai <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['bulan_mulai']) ? 'is-invalid' : '' ?>" id="bulan_mulai" name="bulan_mulai">
                                    <?php for ($b = 1; $b <= 12; $b++): ?>
                                        <option value="<?= $b ?>" <?= (int) $form['bulan_mulai'] === $b ? 'selected' : '' ?>><?= $bulanFull[$b] ?></option>
                                    <?php endfor; ?>
                                </select>
                                <?php if (isset($errors['bulan_mulai'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['bulan_mulai']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-4">
                                <label for="bulan_selesai" class="form-label fw-semibold">Bulan Selesai <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['bulan_selesai']) ? 'is-invalid' : '' ?>" id="bulan_selesai" name="bulan_selesai">
                                    <?php for ($b = 1; $b <= 12; $b++): ?>
                                        <option value="<?= $b ?>" <?= (int) $form['bulan_selesai'] === $b ? 'selected' : '' ?>><?= $bulanFull[$b] ?></option>
                                    <?php endfor; ?>
                                </select>
                                <?php if (isset($errors['bulan_selesai'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['bulan_selesai']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-4">
                                <label for="nilai_rencana" class="form-label fw-semibold">Nilai Rencana <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" inputmode="numeric" class="form-control text-end <?= isset($errors['nilai_rencana']) ? 'is-invalid' : '' ?>"
                                           id="nilai_rencana" name="nilai_rencana" value="<?= $nilaiDisplay ?>" placeholder="0" autocomplete="off" required>
                                    <?php if (isset($errors['nilai_rencana'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['nilai_rencana']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="keterangan" class="form-label fw-semibold">Keterangan Tambahan</label>
                            <input type="text" class="form-control <?= isset($errors['keterangan']) ? 'is-invalid' : '' ?>"
                                   id="keterangan" name="keterangan" maxlength="255"
                                   value="<?= htmlspecialchars((string) $form['keterangan']) ?>"
                                   placeholder="Opsional, mis. nama rekanan atau catatan pelaksanaan">
                            <?php if (isset($errors['keterangan'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['keterangan']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="rp-side-card">
                    <div class="rp-side-head">
                        <h6><i class="bi bi-info-circle me-1"></i>Ringkasan Rekening</h6>
                        <small id="rpInfoRekening">Pilih rekening untuk melihat pagu dan realisasi.</small>
                    </div>
                    <div class="rp-side-body">
                        <div class="rp-info-row">
                            <span>Pagu</span>
                            <strong id="rpInfoPagu">-</strong>
                        </div>
                        <div class="rp-info-row">
                            <span>Rencana tersimpan</span>
                            <strong id="rpInfoRencana">-</strong>
                        </div>
                        <div class="rp-info-row">
                            <span>Sisa pagu (rencana)</span>
                            <strong id="rpInfoSisa">-</strong>
                        </div>
                        <div class="rp-info-row">
                            <span>Realisasi tahun ini</span>
                            <strong id="rpInfoRealisasi">-</strong>
                        </div>
                        <div class="rp-info-row">
                            <span>Sisa kapasitas</span>
                            <strong id="rpInfoSisaKapasitas">-</strong>
                        </div>
                        <div class="rp-info-row">
                            <span>Total RAK</span>
                            <strong id="rpInfoRak">-</strong>
                        </div>

                        <div class="mt-3">
                            <div class="rp-section-label">Preview Timeline</div>
                            <div class="rp-preview">
                                <?php for ($b = 1; $b <= 12; $b++): ?><div class="rp-preview-cell"></div><?php endfor; ?>
                                <div class="rp-preview-bar rp-bar--GU" id="rpPreviewBar" style="grid-column: 1 / span 1;"></div>
                            </div>
                            <div class="rp-preview-labels">
                                <?php for ($b = 1; $b <= 12; $b++): ?><span><?= $bulanShort[$b] ?></span><?php endfor; ?>
                            </div>
                            <div class="small text-muted mt-2" id="rpPreviewText">Januari</div>
                        </div>

                        <div class="mt-3">
                            <div class="rp-section-label">Realisasi per bulan</div>
                            <div class="rp-mini-real" id="rpMiniReal">
                                <?php for ($b = 1; $b <= 12; $b++): ?><div class="rp-mini-cell" data-bulan="<?= $b ?>"></div><?php endfor; ?>
                            </div>
                            <div class="rp-preview-labels">
                                <?php for ($b = 1; $b <= 12; $b++): ?><span><?= $bulanShort[$b] ?></span><?php endfor; ?>
                            </div>
                            <div class="small text-muted mt-2" id="rpMiniRealText">Pilih rekening untuk melihat rincian realisasi bulanan.</div>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0 d-none" id="rpPaguWarning" role="alert" style="font-size:.75rem;"></div>
                        <div class="alert alert-info mt-3 mb-0" style="font-size:.75rem;">
                            <i class="bi bi-lightbulb me-1"></i>Sisa kapasitas = pagu − realisasi terverifikasi − rencana tersimpan. Realisasi dirinci per bulan dari transaksi terverifikasi.
                        </div>
                    </div>
                    <div class="rp-side-actions">
                        <a href="<?= base_url('rencana-penyerapan?tahun=' . urlencode((string) $form['tahun'])) ?>" class="btn btn-outline-secondary flex-fill">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-check-circle me-1"></i><?= $isEdit ? 'Simpan' : 'Simpan Rencana' ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4 pt-3 border-top">
            <a href="<?= base_url('rencana-penyerapan?tahun=' . urlencode((string) $form['tahun'])) ?>" class="btn btn-secondary d-lg-none">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
            <span></span>
            <button type="submit" class="btn btn-primary d-lg-none">
                <i class="bi bi-check-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Rencana' ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE = '<?= base_url() ?>';
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    const excludeId = <?= $isEdit ? (int) $form['id'] : 'null' ?>;

    const programSel = document.getElementById('program_id');
    const kegiatanSel = document.getElementById('kegiatan_id');
    const subKegiatanSel = document.getElementById('sub_kegiatan_id');
    const rekeningSel = document.getElementById('rekening_id');
    const tahunInput = document.getElementById('tahun');
    const bulanMulai = document.getElementById('bulan_mulai');
    const bulanSelesai = document.getElementById('bulan_selesai');
    const nilaiInput = document.getElementById('nilai_rencana');
    const previewBar = document.getElementById('rpPreviewBar');
    const previewText = document.getElementById('rpPreviewText');
    const paguWarning = document.getElementById('rpPaguWarning');
    const form = document.getElementById('rpForm');

    const infoRekening = document.getElementById('rpInfoRekening');
    const infoPagu = document.getElementById('rpInfoPagu');
    const infoRencana = document.getElementById('rpInfoRencana');
    const infoSisa = document.getElementById('rpInfoSisa');
    const infoRealisasi = document.getElementById('rpInfoRealisasi');
    const infoSisaKapasitas = document.getElementById('rpInfoSisaKapasitas');
    const infoRak = document.getElementById('rpInfoRak');
    const miniReal = document.getElementById('rpMiniReal');
    const miniRealText = document.getElementById('rpMiniRealText');

    const bulanNama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    let infoData = null;

    function rp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function parseNominal(value) {
        const digits = String(value || '').replace(/[^\d]/g, '');
        return digits ? Number(digits) : 0;
    }

    function formatInput() {
        const value = parseNominal(nilaiInput.value);
        nilaiInput.value = value > 0 ? value.toLocaleString('id-ID') : '';
        updatePreview();
        checkPagu();
    }

    function updatePreview() {
        let start = Number(bulanMulai.value);
        let end = Number(bulanSelesai.value);

        if (start > end) {
            end = start;
            bulanSelesai.value = String(start);
        }

        const span = Math.max(1, end - start + 1);
        const mekanisme = (form.querySelector('input[name="mekanisme"]:checked') || {}).value || 'GU';

        previewBar.className = 'rp-preview-bar rp-bar--' + mekanisme;
        previewBar.style.gridColumn = start + ' / span ' + span;

        const label = start === end
            ? bulanNama[start - 1]
            : bulanNama[start - 1] + ' – ' + bulanNama[end - 1];
        previewText.textContent = label + ' · ' + mekanisme + ' · ' + rp(parseNominal(nilaiInput.value));
    }

    function pastMonthLimit() {
        const tahun = Number(tahunInput.value || 0);
        if (!tahun) return 0;

        const now = new Date();
        const currentYear = now.getFullYear();
        if (tahun < currentYear) return 12;
        if (tahun === currentYear) return now.getMonth() + 1;
        return 0;
    }

    function updatePreviewPastMarks() {
        const limit = pastMonthLimit();
        document.querySelectorAll('.rp-preview-cell').forEach(function (cell, idx) {
            cell.classList.toggle('rp-preview-cell--past', idx + 1 <= limit);
        });
    }

    function renderMonthlyReal(bulanan, tahun) {
        bulanan = bulanan || {};
        const limit = pastMonthLimit();
        const cells = miniReal ? miniReal.querySelectorAll('.rp-mini-cell') : [];
        const totals = [];
        let max = 0;
        let grand = 0;

        for (let b = 1; b <= 12; b++) {
            const total = Number((bulanan[b] || {}).total || 0);
            totals[b] = total;
            grand += total;
            if (total > max) max = total;
        }

        cells.forEach(function (cell, idx) {
            const b = idx + 1;
            const total = totals[b];
            cell.classList.toggle('rp-mini-cell--past', b <= limit);
            cell.innerHTML = '';

            if (total > 0 && max > 0) {
                const row = bulanan[b] || {};
                const parts = [];
                ['GU', 'TU', 'LS', 'UP'].forEach(function (m) {
                    if (Number(row[m] || 0) > 0) parts.push(m + ' ' + rp(row[m]));
                });

                const bar = document.createElement('div');
                bar.className = 'rp-mini-bar';
                bar.style.height = Math.max(4, (total / max) * 100) + '%';
                bar.title = bulanNama[b - 1] + ': ' + rp(total) + (parts.length ? ' (' + parts.join(' · ') + ')' : '');
                cell.appendChild(bar);
            }
        });

        if (max <= 0) {
            miniRealText.textContent = 'Belum ada realisasi terverifikasi pada tahun ini.';
        } else {
            miniRealText.textContent = 'Total ' + rp(grand) + ' · batang lebih tinggi = penyerapan bulan itu lebih besar.';
        }
    }

    function checkPagu() {
        if (!infoData || infoData.pagu === null || infoData.pagu === undefined) {
            paguWarning.classList.add('d-none');
            return;
        }

        const rencanaBaru = Number(infoData.total_rencana || 0) + parseNominal(nilaiInput.value);
        const realisasi = Number((infoData.realisasi && infoData.realisasi.total) || 0);
        const kapasitas = Number(infoData.pagu) - realisasi;
        const totalSemua = rencanaBaru + realisasi;

        if (rencanaBaru > kapasitas) {
            paguWarning.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Total rencana + realisasi (' + rp(totalSemua) + ') melebihi pagu rekening (' + rp(infoData.pagu) + '). Sisa kapasitas ' + rp(kapasitas) + '. Simpan hanya jika memang direncanakan melebihi pagu.';
            paguWarning.classList.remove('d-none');
        } else {
            paguWarning.classList.add('d-none');
        }
    }

    function resetInfo(message) {
        infoData = null;
        infoRekening.textContent = message || 'Pilih rekening untuk melihat pagu dan realisasi.';
        infoPagu.textContent = '-';
        infoRencana.textContent = '-';
        infoSisa.textContent = '-';
        infoRealisasi.textContent = '-';
        infoSisaKapasitas.textContent = '-';
        infoSisaKapasitas.classList.remove('text-danger');
        infoRak.textContent = '-';
        paguWarning.classList.add('d-none');
        renderMonthlyReal({}, Number(tahunInput.value || 0));
    }

    function fetchInfo() {
        const rekeningId = Number(rekeningSel.value || 0);
        const tahun = Number(tahunInput.value || 0);

        if (!rekeningId || !tahun) {
            resetInfo();
            return;
        }

        let url = BASE + 'rencana-penyerapan/rekening-info?rekening_id=' + encodeURIComponent(rekeningId) + '&tahun=' + encodeURIComponent(tahun);
        if (excludeId) {
            url += '&exclude_id=' + encodeURIComponent(excludeId);
        }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    resetInfo(data && data.message ? data.message : 'Gagal memuat info rekening.');
                    return;
                }

                infoData = data;
                infoRekening.textContent = data.rekening.kode_rekening + ' · ' + data.rekening.nama_rekening;
                infoPagu.textContent = data.pagu !== null ? rp(data.pagu) : 'Belum ditetapkan';
                infoRencana.textContent = rp(data.total_rencana);
                infoSisa.textContent = data.sisa_rencana !== null ? rp(data.sisa_rencana) : '-';
                infoSisa.classList.toggle('text-danger', data.sisa_rencana !== null && data.sisa_rencana < 0);
                infoRealisasi.textContent = rp(data.realisasi.total);
                infoSisaKapasitas.textContent = data.sisa_kapasitas !== null && data.sisa_kapasitas !== undefined ? rp(data.sisa_kapasitas) : '-';
                infoSisaKapasitas.classList.toggle('text-danger', data.sisa_kapasitas !== null && data.sisa_kapasitas !== undefined && data.sisa_kapasitas < 0);
                infoRak.textContent = rp(data.total_rak);
                renderMonthlyReal(data.realisasi_bulanan, Number(tahunInput.value));
                checkPagu();
            })
            .catch(function () {
                resetInfo('Gagal memuat info rekening.');
            });
    }

    function clearSelect(select, placeholder) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        select.disabled = true;
    }

    programSel.addEventListener('change', function () {
        clearSelect(kegiatanSel, '-- Pilih Kegiatan --');
        clearSelect(subKegiatanSel, '-- Pilih Sub Kegiatan --');
        clearSelect(rekeningSel, '-- Pilih Rekening --');
        resetInfo();

        if (!this.value) return;

        kegiatanSel.disabled = false;
        fetch(BASE + 'pagu/get-kegiatans?program_id=' + encodeURIComponent(this.value))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                (data || []).forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.kode_kegiatan + ' - ' + item.nama_kegiatan;
                    kegiatanSel.appendChild(opt);
                });
            });
    });

    kegiatanSel.addEventListener('change', function () {
        clearSelect(subKegiatanSel, '-- Pilih Sub Kegiatan --');
        clearSelect(rekeningSel, '-- Pilih Rekening --');
        resetInfo();

        if (!this.value) return;

        subKegiatanSel.disabled = false;
        fetch(BASE + 'pagu/get-sub-kegiatans?kegiatan_id=' + encodeURIComponent(this.value))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                (data || []).forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.kode_sub_kegiatan + ' - ' + item.nama_sub_kegiatan;
                    subKegiatanSel.appendChild(opt);
                });
            });
    });

    subKegiatanSel.addEventListener('change', function () {
        clearSelect(rekeningSel, '-- Pilih Rekening --');
        resetInfo();

        if (!this.value) return;

        rekeningSel.disabled = false;
        fetch(BASE + 'pagu/get-rekenings?sub_kegiatan_id=' + encodeURIComponent(this.value))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                (data || []).forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.kode_rekening + ' - ' + item.nama_rekening;
                    rekeningSel.appendChild(opt);
                });
            });
    });

    rekeningSel.addEventListener('change', fetchInfo);
    tahunInput.addEventListener('change', function () {
        updatePreviewPastMarks();
        fetchInfo();
    });
    nilaiInput.addEventListener('input', formatInput);
    bulanMulai.addEventListener('change', updatePreview);
    bulanSelesai.addEventListener('change', updatePreview);
    form.querySelectorAll('input[name="mekanisme"]').forEach(function (radio) {
        radio.addEventListener('change', updatePreview);
    });

    form.addEventListener('submit', function () {
        nilaiInput.value = String(parseNominal(nilaiInput.value));
    });

    // Initial state
    updatePreview();
    updatePreviewPastMarks();
    renderMonthlyReal({}, Number(tahunInput.value || 0));
    if (rekeningSel.value && tahunInput.value) {
        fetchInfo();
    }
});
</script>
