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

$rekenings = $rekenings ?? [];
$selectedRekening = $selectedRekening ?? null;
$canEditDetail = $isEdit || (int) ($form['rekening_id'] ?? 0) > 0;
$currentTahun = (int) date('Y');
$formTahun = (int) ($form['tahun'] ?? $currentTahun);
$selectedLabel = $selectedRekening
    ? ($selectedRekening['kode_rekening'] . ' · ' . $selectedRekening['nama_rekening'])
    : '';

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

    .rp-picker-card {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
        overflow: hidden;
    }

    .rp-picker-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 18px;
        background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        border-bottom: 1px solid #e2e8f0;
    }

    .rp-picker-head h6 { margin: 0; font-weight: 800; color: #0f172a; }
    .rp-picker-head small { color: #64748b; font-size: .72rem; }

    .rp-picker-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 12px 18px;
        border-bottom: 1px solid #e2e8f0;
    }

    .rp-picker-toolbar .form-control { max-width: 420px; }

    .rp-picker-scroll { max-height: 440px; overflow-y: auto; }

    .rp-picker-table { margin: 0; font-size: .78rem; min-width: 820px; }

    .rp-picker-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8fafc;
        font-size: .66rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #64748b;
        white-space: nowrap;
        border-bottom: 1px solid #e2e8f0;
    }

    .rp-picker-table tbody tr.is-selected > td { background: #eef2ff; }
    .rp-picker-table .rp-pick-name { font-weight: 700; color: #0f172a; }
    .rp-picker-table .rp-pick-sub { font-size: .68rem; color: #64748b; line-height: 1.4; }
    .rp-picker-table td { vertical-align: middle; }
    .rp-picker-table .rp-pick-value { white-space: nowrap; font-variant-numeric: tabular-nums; }

    .rp-selected-banner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        font-size: .8rem;
        color: #475569;
    }

    .rp-selected-banner.is-active {
        border-color: #a7f3d0;
        background: #ecfdf5;
        color: #065f46;
    }

    .rp-selected-banner .bi { margin-top: 1px; }

    fieldset.rp-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
    fieldset.rp-fieldset[disabled] { opacity: .55; }

    @media (max-width: 767.98px) {
        .rp-mek-options { grid-template-columns: 1fr; }
        .rp-side-card { position: static; }
        .rp-picker-toolbar .form-control { max-width: none; }
        .rp-picker-toolbar { padding: 10px 14px; }
        .rp-picker-head { padding: 12px 14px; }
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

    <?php if (!$isEdit): ?>
        <div class="rp-picker-card mb-4" id="rpPickerCard">
            <div class="rp-picker-head">
                <div>
                    <h6><i class="bi bi-list-ul text-primary me-1"></i>Pilih Rekening</h6>
                    <small>Semua rekening yang memiliki pagu tahun <?= $formTahun ?>. Klik Pilih, lalu isi detail rencana di bawah.</small>
                </div>
                <form method="GET" action="<?= base_url('rencana-penyerapan/create') ?>" class="d-flex align-items-end gap-2 m-0">
                    <div>
                        <label for="picker-tahun" class="form-label fw-semibold mb-1" style="font-size:.7rem;">Tahun</label>
                        <select name="tahun" id="picker-tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php for ($y = $currentTahun + 1; $y >= $currentTahun - 5; $y--): ?>
                                <option value="<?= $y ?>" <?= $formTahun === $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <noscript><button type="submit" class="btn btn-sm btn-primary">Tampilkan</button></noscript>
                </form>
            </div>
            <div class="rp-picker-toolbar">
                <input type="search" id="rpRekeningSearch" class="form-control form-control-sm"
                       placeholder="Cari kode rekening, nama, sub kegiatan, seksi...">
                <span class="small text-muted" id="rpRekeningCount"><?= count($rekenings) ?> rekening</span>
            </div>
            <div class="table-responsive rp-picker-scroll">
                <table class="table table-sm table-hover rp-picker-table">
                    <thead>
                        <tr>
                            <th>Kode Rekening</th>
                            <th>Rekening</th>
                            <th class="text-end">Pagu</th>
                            <th class="text-end">Realisasi</th>
                            <th class="text-end">Rencana</th>
                            <th class="text-end">Sisa Kapasitas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekenings)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada rekening dengan pagu pada tahun <?= $formTahun ?>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rekenings as $rek): ?>
                                <?php
                                    $paguRek = (float) $rek['total_pagu'];
                                    $realRek = (float) $rek['total_realisasi'];
                                    $rencanaRek = (float) $rek['total_rencana'];
                                    $sisaRek = $paguRek - $realRek - $rencanaRek;
                                    $isSelected = (int) ($form['rekening_id'] ?? 0) === (int) $rek['id'];
                                    $searchText = mb_strtolower(implode(' ', array_filter([
                                        $rek['kode_rekening'], $rek['nama_rekening'],
                                        $rek['kode_program'], $rek['nama_program'],
                                        $rek['kode_kegiatan'], $rek['nama_kegiatan'],
                                        $rek['kode_sub_kegiatan'], $rek['nama_sub_kegiatan'],
                                        $rek['nama_seksi'],
                                    ])));
                                ?>
                                <tr data-rekening-row
                                    data-rekening-id="<?= (int) $rek['id'] ?>"
                                    data-kode="<?= htmlspecialchars($rek['kode_rekening']) ?>"
                                    data-nama="<?= htmlspecialchars($rek['nama_rekening']) ?>"
                                    data-search="<?= htmlspecialchars($searchText) ?>"
                                    class="<?= $isSelected ? 'is-selected' : '' ?>">
                                    <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($rek['kode_rekening']) ?></span></td>
                                    <td>
                                        <div class="rp-pick-name"><?= htmlspecialchars($rek['nama_rekening']) ?></div>
                                        <div class="rp-pick-sub">
                                            <?= htmlspecialchars($rek['kode_sub_kegiatan'] . ' · ' . $rek['nama_sub_kegiatan']) ?>
                                            <?php if (!empty($rek['nama_seksi'])): ?>
                                                <span> · <?= htmlspecialchars($rek['nama_seksi']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end rp-pick-value">Rp <?= number_format($paguRek, 0, ',', '.') ?></td>
                                    <td class="text-end rp-pick-value text-success">Rp <?= number_format($realRek, 0, ',', '.') ?></td>
                                    <td class="text-end rp-pick-value text-primary">Rp <?= number_format($rencanaRek, 0, ',', '.') ?></td>
                                    <td class="text-end rp-pick-value <?= $sisaRek < 0 ? 'text-danger fw-semibold' : '' ?>">Rp <?= number_format($sisaRek, 0, ',', '.') ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm <?= $isSelected ? 'btn-primary' : 'btn-outline-primary' ?>" data-pick-rekening>
                                            <?= $isSelected ? 'Dipilih' : 'Pilih' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
                        <input type="hidden" name="rekening_id" id="rekening_id" value="<?= (int) ($form['rekening_id'] ?? 0) ?>">
                        <?php if ($isEdit): ?>
                            <div class="rp-selected-banner is-active mb-3">
                                <i class="bi bi-check-circle"></i>
                                <div>
                                    <div class="fw-bold" id="rpSelectedLabel"><?= htmlspecialchars($selectedLabel) ?></div>
                                    <small id="rpSelectedHint">Rekening tidak dapat diubah saat edit. Hapus lalu buat rencana baru bila perlu pindah rekening.</small>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="tahun" class="form-label fw-semibold">Tahun Anggaran <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>" id="tahun" name="tahun"
                                           value="<?= (int) $form['tahun'] ?>" min="2000" max="2100" required>
                                    <?php if (isset($errors['tahun'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['tahun']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" id="tahun" name="tahun" value="<?= $formTahun ?>">
                            <div class="rp-selected-banner <?= $selectedRekening ? 'is-active' : '' ?>" id="rpSelectedBanner">
                                <i class="bi <?= $selectedRekening ? 'bi-check-circle' : 'bi-info-circle' ?>"></i>
                                <div>
                                    <div class="fw-bold" id="rpSelectedLabel"><?= $selectedRekening ? htmlspecialchars($selectedLabel) : 'Belum ada rekening dipilih' ?></div>
                                    <small id="rpSelectedHint"><?= $selectedRekening
                                        ? 'Nilai rencana akan dicatat untuk rekening ini · Tahun ' . $formTahun
                                        : 'Pilih salah satu rekening pada tabel di atas untuk mulai mengisi detail rencana.' ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($errors['rekening_id'])): ?>
                            <div class="alert alert-danger py-2 px-3 mt-3 mb-0" style="font-size:.8rem;"><?= htmlspecialchars($errors['rekening_id']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rp-form-card mb-4" id="rpDetailCard">
                    <div class="card-header">
                        <h6><i class="bi bi-clipboard-check text-primary me-1"></i>Detail Rencana Kegiatan</h6>
                    </div>
                    <fieldset class="rp-fieldset" id="rpDetailFieldset" <?= $canEditDetail ? '' : 'disabled' ?>>
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
                    </fieldset>
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
                        <button type="submit" class="btn btn-primary flex-fill" data-rp-submit <?= $canEditDetail ? '' : 'disabled' ?>>
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
            <button type="submit" class="btn btn-primary d-lg-none" data-rp-submit <?= $canEditDetail ? '' : 'disabled' ?>>
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

    const rekeningSel = document.getElementById('rekening_id');
    const tahunInput = document.getElementById('tahun');
    const bulanMulai = document.getElementById('bulan_mulai');
    const bulanSelesai = document.getElementById('bulan_selesai');
    const nilaiInput = document.getElementById('nilai_rencana');
    const previewBar = document.getElementById('rpPreviewBar');
    const previewText = document.getElementById('rpPreviewText');
    const paguWarning = document.getElementById('rpPaguWarning');
    const form = document.getElementById('rpForm');

    const detailFieldset = document.getElementById('rpDetailFieldset');
    const submitButtons = document.querySelectorAll('[data-rp-submit]');
    const selectedBanner = document.getElementById('rpSelectedBanner');
    const selectedLabel = document.getElementById('rpSelectedLabel');
    const selectedHint = document.getElementById('rpSelectedHint');
    const pickerSearch = document.getElementById('rpRekeningSearch');
    const pickerCount = document.getElementById('rpRekeningCount');
    const pickerRows = Array.from(document.querySelectorAll('[data-rekening-row]'));
    const formTahun = <?= (int) $formTahun ?>;

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

    function enableDetail() {
        if (detailFieldset) detailFieldset.disabled = false;
        submitButtons.forEach(function (btn) { btn.disabled = false; });
    }

    function selectRekening(row, scroll) {
        rekeningSel.value = row.dataset.rekeningId;

        pickerRows.forEach(function (item) {
            const active = item === row;
            item.classList.toggle('is-selected', active);

            const btn = item.querySelector('[data-pick-rekening]');
            if (btn) {
                btn.classList.toggle('btn-primary', active);
                btn.classList.toggle('btn-outline-primary', !active);
                btn.textContent = active ? 'Dipilih' : 'Pilih';
            }
        });

        enableDetail();

        if (selectedBanner) {
            selectedBanner.classList.add('is-active');
            const icon = selectedBanner.querySelector('.bi');
            if (icon) {
                icon.classList.remove('bi-info-circle');
                icon.classList.add('bi-check-circle');
            }
        }

        if (selectedLabel) selectedLabel.textContent = row.dataset.kode + ' · ' + row.dataset.nama;
        if (selectedHint) selectedHint.textContent = 'Nilai rencana akan dicatat untuk rekening ini · Tahun ' + (tahunInput.value || formTahun);

        fetchInfo();

        if (scroll) {
            const card = document.getElementById('rpDetailCard');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const bentuk = document.getElementById('bentuk_kegiatan');
            if (bentuk) bentuk.focus({ preventScroll: true });
        }
    }

    function filterPicker() {
        const terms = String(pickerSearch.value || '').toLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;

        pickerRows.forEach(function (row) {
            const haystack = row.dataset.search || '';
            const match = terms.every(function (term) { return haystack.indexOf(term) !== -1; });
            row.classList.toggle('d-none', !match);
            if (match) shown++;
        });

        if (pickerCount) {
            pickerCount.textContent = terms.length
                ? 'Menampilkan ' + shown + ' dari ' + pickerRows.length + ' rekening'
                : pickerRows.length + ' rekening';
        }
    }

    if (pickerSearch) pickerSearch.addEventListener('input', filterPicker);

    pickerRows.forEach(function (row) {
        const btn = row.querySelector('[data-pick-rekening]');
        if (!btn) return;
        btn.addEventListener('click', function () {
            selectRekening(row, true);
        });
    });

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
    if (rekeningSel.value && tahunInput.value) {
        fetchInfo();
    } else {
        renderMonthlyReal({}, Number(tahunInput.value || 0));
        if (miniRealText) miniRealText.textContent = 'Pilih rekening untuk melihat rincian realisasi bulanan.';
    }
});
</script>
