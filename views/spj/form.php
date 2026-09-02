<?php
/**
 * View: SPJ — Form Input / Edit Rincian Biaya Perjalanan Dinas
 *
 * Variabel dari controller:
 *   $rincian    : null (create) | array header row (edit)
 *   $details    : array baris komponen (default 4 baris atau dari DB)
 *   $suratTugas : header ST dari db_surat_tugas (null jika tidak tersedia)
 *   $pegawai    : ['nip', 'nama', 'pangkat', 'jabatan']
 *   $errors     : array pesan error validasi
 */
$rincian    = $rincian    ?? null;
$details    = $details    ?? [];
$suratTugas = $suratTugas ?? null;
$pegawai    = $pegawai    ?? [];
$errors     = $errors     ?? [];

$isEdit     = $rincian !== null;
$stId       = $rincian['surat_tugas_id'] ?? ($suratTugas['id'] ?? ($_POST['surat_tugas_id'] ?? 0));
$nip        = $rincian['pegawai_nip']    ?? ($pegawai['nip']   ?? ($_POST['pegawai_nip'] ?? ''));

// Nilai form (persist POST on error)
$ditetapkan   = $rincian['ditetapkan_sejumlah'] ?? ($_POST['ditetapkan_sejumlah'] ?? '');
$dibayar      = $rincian['dibayar_semula']       ?? ($_POST['dibayar_semula']       ?? '');
$tempatTanggal = $rincian['tempat_tanggal']      ?? ($_POST['tempat_tanggal']       ?? 'Bojonegoro, ' . date('d') . ' ' . ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][(int)date('n')] . ' ' . date('Y'));

$formAction = $isEdit
    ? base_url('spj/update/' . $rincian['id'])
    : base_url('spj/store');

// Format Rp untuk display (input masking)
function fmtRp($val): string {
    if ($val === '' || $val === null) return '';
    return number_format((float) $val, 0, ',', '.');
}
?>

<style>
.spj-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.spj-card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: .85rem 1.25rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.spj-card-header h5 { margin:0; font-size:.95rem; font-weight:700; color:#1e293b; }
.spj-card-body { padding: 1.25rem; }
.spj-form-input, .spj-form-select {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: .875rem;
    padding: .5rem .75rem;
    transition: border-color .15s, box-shadow .15s;
}
.spj-form-input:focus, .spj-form-select:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,.12);
    outline: none;
}
.komponen-row { border-bottom: 1px solid #f1f5f9; padding: .65rem 0; }
.komponen-row:last-child { border-bottom: none; }
.komponen-label {
    font-size: .72rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: .2rem;
}
.btn-remove-row {
    background: none;
    border: 1px solid #fca5a5;
    color: #dc2626;
    border-radius: 6px;
    padding: .25rem .45rem;
    font-size: .8rem;
    cursor: pointer;
    transition: all .15s;
}
.btn-remove-row:hover { background: #fee2e2; }
.btn-add-row {
    background: #eff6ff;
    border: 1px dashed #93c5fd;
    color: #1d4ed8;
    border-radius: 8px;
    padding: .45rem 1rem;
    font-size: .825rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    width: 100%;
}
.btn-add-row:hover { background: #dbeafe; }
.total-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    padding: .85rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.terbilang-box {
    background: #fefce8;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: .6rem 1rem;
    font-size: .825rem;
    font-style: italic;
    color: #78350f;
    margin-top: .5rem;
}
.sppd-row label { font-size: .8rem; font-weight: 600; color: #475569; }
.saldo-sppd {
    background: #f1f5f9;
    border-radius: 8px;
    padding: .55rem 1rem;
    font-size: .9rem;
    font-weight: 700;
}
.saldo-kurang { color: #dc2626; }
.saldo-lebih  { color: #059669; }
.saldo-pas    { color: #4f46e5; }
</style>

<div class="container-fluid py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('spj') ?>">SPJ</a></li>
            <?php if ($stId): ?>
                <li class="breadcrumb-item"><a href="<?= base_url('spj/detail/' . (int)$stId) ?>">Detail ST</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Isi' ?> Rincian Biaya</li>
        </ol>
    </nav>

    <!-- Header info pegawai & ST -->
    <div class="spj-card mb-3">
        <div class="spj-card-body py-3">
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Pegawai</div>
                    <div class="fw-bold"><?= htmlspecialchars($pegawai['nama'] ?? ($rincian['pegawai_nama'] ?? '-')) ?></div>
                    <div class="text-muted" style="font-size:.8rem;">
                        NIP: <?= htmlspecialchars($nip) ?>
                        <?php $pangkat = $pegawai['pangkat'] ?? ($rincian['pegawai_pangkat'] ?? null); ?>
                        <?php if ($pangkat): ?> &middot; <?= htmlspecialchars($pangkat) ?><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;">Surat Tugas</div>
                    <div class="fw-semibold" style="font-size:.875rem;">
                        <?= htmlspecialchars($suratTugas['nomor_surat'] ?? ($rincian['nomor_surat'] ?? '-')) ?>
                    </div>
                    <?php if (!empty($suratTugas['untuk'])): ?>
                        <div class="text-muted" style="font-size:.8rem;"><?= htmlspecialchars($suratTugas['untuk']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Error -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 mb-3">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Mohon perbaiki kesalahan:</div>
            <ul class="mb-0 ps-3" style="font-size:.875rem;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $formAction ?>" id="spjForm">
        <!-- Hidden fields -->
        <input type="hidden" name="surat_tugas_id" value="<?= (int)$stId ?>">
        <input type="hidden" name="pegawai_nip"    value="<?= htmlspecialchars($nip) ?>">

        <!-- ══ SECTION 1: KOMPONEN BIAYA ════════════════════════════════ -->
        <div class="spj-card">
            <div class="spj-card-header">
                <span class="badge bg-primary" style="font-size:.72rem;padding:.2rem .45rem;">1</span>
                <h5>Komponen Biaya</h5>
                <span class="text-muted ms-1" style="font-size:.8rem;">(bisa tambah / hapus baris)</span>
            </div>
            <div class="spj-card-body">

                <!-- Header kolom -->
                <div class="row g-1 mb-1 d-none d-md-flex" style="font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">
                    <div class="col-md-3">Nama Komponen</div>
                    <div class="col-md-2">Harga Satuan (Rp)</div>
                    <div class="col-md-1">Jml Hari</div>
                    <div class="col-md-2">Jumlah (Rp)</div>
                    <div class="col-md-3">Keterangan</div>
                    <div class="col-md-1"></div>
                </div>

                <div id="komponenList">
                    <?php foreach ($details as $idx => $d): ?>
                        <?php include __DIR__ . '/form_komponen_row.php'; ?>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn-add-row mt-2" id="btnAddRow">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Baris Komponen
                </button>

                <!-- Total -->
                <div class="total-box mt-3">
                    <span class="fw-bold text-success" style="font-size:.875rem;"><i class="bi bi-wallet2 me-1"></i>Total Biaya</span>
                    <span class="fw-bold" style="font-size:1.05rem;" id="totalDisplay">Rp 0</span>
                </div>
                <div class="terbilang-box" id="terbilangBox">Terbilang: —</div>

            </div>
        </div>

        <!-- ══ SECTION 2: PERHITUNGAN SPPD RAMPUNG ══════════════════════ -->
        <div class="spj-card">
            <div class="spj-card-header">
                <span class="badge bg-primary" style="font-size:.72rem;padding:.2rem .45rem;">2</span>
                <h5>Perhitungan SPPD Rampung</h5>
            </div>
            <div class="spj-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="sppd-row form-label">Ditetapkan Sejumlah (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light" style="font-size:.8rem;">Rp</span>
                            <input type="text"
                                   name="ditetapkan_sejumlah"
                                   id="ditetapkan_sejumlah"
                                   class="form-control spj-form-input rp-input"
                                   placeholder="0"
                                   value="<?= htmlspecialchars($ditetapkan !== '' ? fmtRp($ditetapkan) : '') ?>"
                                   inputmode="numeric">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="sppd-row form-label">Yang Telah Dibayar Semula (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light" style="font-size:.8rem;">Rp</span>
                            <input type="text"
                                   name="dibayar_semula"
                                   id="dibayar_semula"
                                   class="form-control spj-form-input rp-input"
                                   placeholder="0"
                                   value="<?= htmlspecialchars($dibayar !== '' ? fmtRp($dibayar) : '') ?>"
                                   inputmode="numeric">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="sppd-row form-label">Sisa Kurang / Lebih</label>
                        <div class="saldo-sppd" id="sisaSppd">—</div>
                        <div class="text-muted mt-1" style="font-size:.75rem;">= Ditetapkan − Dibayar Semula</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ SECTION 3: TEMPAT & TANGGAL ══════════════════════════════ -->
        <div class="spj-card">
            <div class="spj-card-header">
                <span class="badge bg-primary" style="font-size:.72rem;padding:.2rem .45rem;">3</span>
                <h5>Tempat &amp; Tanggal (untuk cetak)</h5>
            </div>
            <div class="spj-card-body">
                <input type="text"
                       name="tempat_tanggal"
                       id="tempat_tanggal"
                       class="form-control spj-form-input"
                       placeholder="Bojonegoro, 21 April 2026"
                       value="<?= htmlspecialchars($tempatTanggal) ?>"
                       style="max-width:380px;">
                <small class="text-muted" style="font-size:.75rem;">Contoh: Bojonegoro, 21 April 2026</small>
            </div>
        </div>

        <!-- Sticky Action Bar -->
        <div style="position:sticky;bottom:0;z-index:1020;background:#fff;border-top:1px solid #e2e8f0;padding:.85rem 1.25rem;margin:-0.75rem;border-radius:0 0 14px 14px;box-shadow:0 -4px 12px rgba(0,0,0,.06);">
            <div class="d-flex justify-content-between align-items-center gap-3">
                <a href="<?= $stId ? base_url('spj/detail/' . (int)$stId) : base_url('spj') ?>"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-floppy me-1"></i><?= $isEdit ? 'Perbarui Rincian Biaya' : 'Simpan Rincian Biaya' ?>
                </button>
            </div>
        </div>

    </form>
</div>

<!-- Template baris komponen untuk JS clone -->
<template id="komponenRowTemplate">
    <?php
    $idx = '__IDX__';
    $d   = ['nama_komponen' => '', 'harga_satuan' => '', 'jumlah_hari' => '', 'jumlah' => '', 'keterangan' => ''];
    ob_start();
    include __DIR__ . '/form_komponen_row.php';
    $rowTemplate = ob_get_clean();
    echo '<script>const ROW_TEMPLATE = ' . json_encode($rowTemplate) . ';</script>';
    ?>
</template>

<script>
// ── Terbilang (angka → teks Rupiah) ────────────────────────────────────────
const SATUAN  = ['','satu','dua','tiga','empat','lima','enam','tujuh','delapan','sembilan'];
const BELASAN = ['sepuluh','sebelas','dua belas','tiga belas','empat belas','lima belas',
                 'enam belas','tujuh belas','delapan belas','sembilan belas'];
const PULUHAN = ['','','dua puluh','tiga puluh','empat puluh','lima puluh',
                 'enam puluh','tujuh puluh','delapan puluh','sembilan puluh'];

function terbilangRatusan(n) {
    if (n === 0) return '';
    if (n < 10)  return SATUAN[n];
    if (n < 20)  return BELASAN[n - 10];
    if (n < 100) {
        const s = PULUHAN[Math.floor(n / 10)];
        return n % 10 ? s + ' ' + SATUAN[n % 10] : s;
    }
    const r = Math.floor(n / 100);
    const sisa = n % 100;
    const ratus = (r === 1 ? 'seratus' : SATUAN[r] + ' ratus');
    return sisa ? ratus + ' ' + terbilangRatusan(sisa) : ratus;
}

function terbilang(n) {
    n = Math.floor(Math.abs(n));
    if (n === 0) return 'nol';
    const miliar  = Math.floor(n / 1_000_000_000);
    const juta    = Math.floor((n % 1_000_000_000) / 1_000_000);
    const ribu    = Math.floor((n % 1_000_000) / 1_000);
    const sisanya = n % 1_000;
    let parts = [];
    if (miliar) parts.push(terbilangRatusan(miliar) + ' miliar');
    if (juta)   parts.push(terbilangRatusan(juta)   + ' juta');
    if (ribu)   parts.push((ribu === 1 ? 'seribu' : terbilangRatusan(ribu) + ' ribu'));
    if (sisanya) parts.push(terbilangRatusan(sisanya));
    const hasil = parts.join(' ');
    return hasil.charAt(0).toUpperCase() + hasil.slice(1);
}

// ── Parse Rp input (hapus titik, ganti koma → titik) ──────────────────────
function parseRp(val) {
    return parseFloat(String(val).replace(/\./g, '').replace(',', '.')) || 0;
}
function formatRp(n) {
    return Math.floor(n).toLocaleString('id-ID');
}

// ── Hitung total semua baris komponen ──────────────────────────────────────
function hitungTotal() {
    let total = 0;
    document.querySelectorAll('.row-jumlah').forEach(el => {
        total += parseRp(el.value);
    });
    document.getElementById('totalDisplay').textContent = 'Rp ' + formatRp(total);
    document.getElementById('terbilangBox').textContent =
        'Terbilang: ' + terbilang(total) + ' Rupiah';

    // Otomatis isi Ditetapkan Sejumlah dan Dibayar Semula jika belum diubah manual
    const detEl = document.getElementById('ditetapkan_sejumlah');
    const dibEl = document.getElementById('dibayar_semula');
    if (detEl && (!detEl.dataset.customized || detEl.value === '' || detEl.value === '0')) {
        detEl.value = total > 0 ? formatRp(total) : '';
    }
    if (dibEl && (!dibEl.dataset.customized || dibEl.value === '' || dibEl.value === '0')) {
        dibEl.value = total > 0 ? formatRp(total) : '';
    }

    hitungSppd();
    return total;
}

function hitungSppd() {
    const ditetapkan = parseRp(document.getElementById('ditetapkan_sejumlah').value);
    const dibayar    = parseRp(document.getElementById('dibayar_semula').value);
    const sisa       = ditetapkan - dibayar;
    const el         = document.getElementById('sisaSppd');
    if (!ditetapkan && !dibayar) { el.textContent = '—'; el.className = 'saldo-sppd'; return; }
    if (sisa === 0) {
        el.textContent = 'Rp 0';
        el.className   = 'saldo-sppd saldo-pas';
    } else {
        el.textContent = (sisa < 0 ? '– ' : '+ ') + 'Rp ' + formatRp(Math.abs(sisa));
        el.className   = 'saldo-sppd ' + (sisa < 0 ? 'saldo-kurang' : 'saldo-lebih');
    }
}

// ── Auto-hitung Jumlah per baris (harga × hari) ────────────────────────────
function setupRowCalc(row) {
    const harga  = row.querySelector('.row-harga');
    const hari   = row.querySelector('.row-hari');
    const jumlah = row.querySelector('.row-jumlah');

    function recalc() {
        const h = parseRp(harga.value);
        const d = parseRp(hari.value);
        if (h > 0) {
            const hasil = d > 0 ? h * d : h;
            jumlah.value = formatRp(hasil);
        }
        hitungTotal();
    }
    harga.addEventListener('input', recalc);
    hari.addEventListener('input', recalc);

    // Format Rp saat lose focus
    [harga, jumlah].forEach(el => {
        el.addEventListener('blur', function () {
            const v = parseRp(this.value);
            if (v > 0) this.value = formatRp(v);
        });
        el.addEventListener('focus', function () {
            const v = parseRp(this.value);
            this.value = v > 0 ? String(Math.floor(v)) : '';
        });
    });

    jumlah.addEventListener('input', hitungTotal);
}

// ── Tombol hapus baris ─────────────────────────────────────────────────────
function setupRemoveBtn(row) {
    row.querySelector('.btn-remove-row')?.addEventListener('click', function () {
        row.remove();
        reindexRows();
        hitungTotal();
    });
}

// ── Re-index name attribute setelah hapus ─────────────────────────────────
function reindexRows() {
    document.querySelectorAll('#komponenList .komponen-row').forEach(function (row, idx) {
        row.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/komponen\[\d+\]/, 'komponen[' + idx + ']');
        });
    });
}

// ── Tambah baris baru ──────────────────────────────────────────────────────
document.getElementById('btnAddRow').addEventListener('click', function () {
    const count  = document.querySelectorAll('#komponenList .komponen-row').length;
    const html   = ROW_TEMPLATE.replace(/__IDX__/g, count);
    const temp   = document.createElement('div');
    temp.innerHTML = html;
    const newRow = temp.firstElementChild;
    document.getElementById('komponenList').appendChild(newRow);
    setupRowCalc(newRow);
    setupRemoveBtn(newRow);
    newRow.querySelector('.row-nama')?.focus();
});

// ── Format Rp pada SPPD inputs ────────────────────────────────────────────
document.querySelectorAll('.rp-input').forEach(function (el) {
    el.addEventListener('blur', function () {
        const v = parseRp(this.value);
        if (v > 0) this.value = formatRp(v);
        hitungSppd();
    });
    el.addEventListener('focus', function () {
        const v = parseRp(this.value);
        this.value = v > 0 ? String(Math.floor(v)) : '';
    });
    el.addEventListener('input', function() {
        this.dataset.customized = 'true';
        hitungSppd();
    });
});

// ── Init semua baris yang sudah ada ───────────────────────────────────────
document.querySelectorAll('#komponenList .komponen-row').forEach(function (row) {
    setupRowCalc(row);
    setupRemoveBtn(row);
});

// ── Sebelum submit: strip format (hapus titik pemisah ribuan) ─────────────
document.getElementById('spjForm').addEventListener('submit', function () {
    document.querySelectorAll('.rp-input, .row-harga, .row-jumlah').forEach(function (el) {
        el.value = String(parseRp(el.value));
    });
});

// ── Init hitung pertama ────────────────────────────────────────────────────
hitungTotal();
hitungSppd();
</script>
