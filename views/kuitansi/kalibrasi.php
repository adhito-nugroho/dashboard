<?php
/**
 * View: Editor Visual Kalibrasi Cetak Kuitansi (admin saja).
 * $kalibrasiData: positions [key=>[x_mm,y_mm,max_width_mm?]], labels, widths, refUrl, flash, flashType.
 */
$data = $kalibrasiData ?? [];
$positions = $data['positions'] ?? [];
$labels = $data['labels'] ?? [];
$widths = $data['widths'] ?? [];
$defaultWidths = $data['defaultWidths'] ?? $widths;
$refUrl = $data['refUrl'] ?? null;
$refKind = $data['refKind'] ?? null;
$flash = $data['flash'] ?? null;
$flashType = $data['flashType'] ?? 'info';
$printers = $data['printers'] ?? [];
$printerId = (int) ($data['printerId'] ?? 0);
$trxList = $data['trxList'] ?? [];
$sampleId = (int) ($data['sampleId'] ?? 0);
$sampleTexts = $data['sampleTexts'] ?? [];
$sampleLabel = $data['sampleLabel'] ?? null;
$pustaka = $data['pustaka'] ?? [];
$blankoAktifId = (int) ($data['blankoAktifId'] ?? 0);
$fonts = $data['fonts'] ?? [];
$activePrinter = null;
foreach ($printers as $p) {
    if ((int) ($p['id'] ?? 0) === $printerId) { $activePrinter = $p; break; }
}
if ($activePrinter === null && !empty($printers)) { $activePrinter = $printers[0]; $printerId = (int) $activePrinter['id']; }

$dummy = [
    'no_program' => 'PRG.01', 'no_kegiatan' => 'KEG.02',
    'jumlah_terbilang' => 'Tiga Ratus Ribu Rupiah',
    'uraian' => 'Perjalanan Dinas dalam rangka koordinasi kehutanan ... (contoh, wrap otomatis)',
    'terbilang_rp' => '300.000', 'tempat_tanggal' => '27 Agustus 2026',
    'ttd_kpa_nama' => 'ENDANG HANDAYANI, S.P., M.Si.', 'ttd_kpa_nip' => 'NIP. 19760328 200003 2 003',
    'ttd_bendahara_nama' => 'ADHITO NUGROHO, S.Kom.', 'ttd_bendahara_nip' => 'NIP. 19840214 201001 1 011',
    'ttd_penerima_nama' => 'Budi Santoso',
];
// Hanya elemen yang benar-benar dicetak (no_bku & terima_dari selalu kosong;
// elemen berisi teks kosong pada data contoh ikut disembunyikan).
$order = $data['order'] ?? ['no_program','no_kegiatan','jumlah_terbilang','uraian','terbilang_rp','tempat_tanggal','ttd_kpa_nama','ttd_kpa_nip','ttd_bendahara_nama','ttd_bendahara_nip','ttd_penerima_nama'];
$hiddenCount = (int) ($data['hiddenCount'] ?? 0);
?>
<style>
.kal-canvas-scroll { overflow:auto; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; max-width:100%; }
.kal-canvas { position:relative; width:860px; height:660px; background:#fff; flex:0 0 auto; }
.kal-canvas .kal-bg { position:absolute; inset:0; background-size:100% 100%; background-repeat:no-repeat; opacity:.5; pointer-events:none; }
.kal-canvas canvas.kal-pdfbg { position:absolute; inset:0; width:860px; height:660px; opacity:.5; pointer-events:none; }
.kal-box { position:absolute; outline:1.5px solid #2563eb; background:rgba(37,99,235,.07); border-radius:4px; padding:0; cursor:move; user-select:none; touch-action:none; box-sizing:border-box; min-height:22px; font-family:Arial,Helvetica,sans-serif; }
.kal-box .kal-txt { display:block; font-size:10px; color:#0f172a; line-height:1.25; white-space:pre-wrap; word-break:break-word; font-kerning:none; font-variant-ligatures:none; }
.kal-box.nowrap .kal-txt { white-space:nowrap; word-break:normal; overflow:visible; }
.kal-box.is-wrap .kal-txt { white-space:pre-wrap; word-break:break-word; }
.kal-box .kal-txt-empty { color:#94a3b8; font-style:italic; }
.kal-box[data-key^="ttd_"] .kal-txt { text-align:center; }
.kal-box .kal-badge { position:absolute; top:-20px; left:0; font-size:10px; background:#0f172a; color:#fff; border-radius:4px; padding:1px 6px; white-space:nowrap; display:none; z-index:5; }
.kal-box.selected { outline-color:#dc2626; background:rgba(220,38,38,.08); box-shadow:0 0 0 2px rgba(220,38,38,.25); }
.kal-box.selected .kal-badge { display:block; }
.kal-resize { position:absolute; right:-5px; top:0; bottom:0; width:10px; cursor:ew-resize; z-index:6; }
.kal-resize::after { content:''; position:absolute; right:2px; top:15%; bottom:15%; width:3px; border-radius:2px; background:#2563eb; }
</style>

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Kalibrasi Cetak Kuitansi</li>
        </ol>
    </nav>

    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
        <h4 class="fw-bold mb-0"><i class="bi bi-printer text-primary me-2"></i>Kalibrasi Cetak Kuitansi</h4>
        <span id="kalDirty" class="badge bg-warning text-dark d-none">belum disimpan</span>
    </div>
    <p class="text-muted mb-3" style="font-size:.875rem;">
        Kertas 215&thinsp;mm &times; 165&thinsp;mm landscape (skala 1mm = 4px).
        Geser tiap kotak ke posisinya. Kalibrasi tersimpan <strong>per printer</strong> —
        tiap printer punya offset mekanis berbeda.
    </p>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label mb-1 fw-semibold" style="font-size:.8rem;" for="kalPrinter">Printer</label>
                <select class="form-select form-select-sm" id="kalPrinter" style="min-width:200px;">
                    <?php foreach ($printers as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $printerId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nama']) ?><?= !empty($p['is_default']) ? ' ★ default' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <form method="POST" action="<?= base_url('kuitansi/kalibrasi/printer/default') ?>" class="m-0">
                <input type="hidden" name="id" value="<?= $printerId ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary" <?= !empty($activePrinter['is_default']) ? 'disabled' : '' ?> title="Tombol Download memakai printer default">
                    <i class="bi bi-star me-1"></i>Jadikan Default
                </button>
            </form>
            <form method="POST" action="<?= base_url('kuitansi/kalibrasi/printer/hapus') ?>" class="m-0"
                  onsubmit="return confirm('Hapus printer <?= htmlspecialchars($activePrinter['nama'] ?? '', ENT_QUOTES) ?> beserta kalibrasinya?')">
                <input type="hidden" name="id" value="<?= $printerId ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" <?= !empty($activePrinter['is_default']) || empty($printers) ? 'disabled' : '' ?>>
                    <i class="bi bi-trash me-1"></i>Hapus
                </button>
            </form>
            <form method="POST" action="<?= base_url('kuitansi/kalibrasi/printer/tambah') ?>" class="m-0 d-flex gap-2 align-items-end ms-auto">
                <div>
                    <label class="form-label mb-1" style="font-size:.8rem;" for="kalNewNama">Printer baru</label>
                    <input type="text" class="form-control form-control-sm" id="kalNewNama" name="nama" maxlength="100" placeholder="cth: Epson LQ-310 (TU)" required>
                </div>
                <div>
                    <label class="form-label mb-1" style="font-size:.8rem;" for="kalNewKet">Keterangan</label>
                    <input type="text" class="form-control form-control-sm" id="kalNewKet" name="keterangan" maxlength="255" placeholder="opsional">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-success" title="Posisi awal disalin dari printer default">
                    <i class="bi bi-plus-lg me-1"></i>Tambah
                </button>
            </form>
        </div>
    </div>

    <?php if (empty($activePrinter['is_default'])): ?>
        <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
                Printer ini (<strong><?= htmlspecialchars($activePrinter['nama'] ?? '') ?></strong>) <em>bukan printer default</em>. Cetak kuitansi dari menu Transaksi otomatis menggunakan printer default.
            </div>
            <form method="POST" action="<?= base_url('kuitansi/kalibrasi/printer/default') ?>" class="m-0">
                <input type="hidden" name="id" value="<?= $printerId ?>">
                <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                    <i class="bi bi-star-fill me-1"></i>Jadikan Printer Default Sekarang
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div id="kalAlert"></div>

    <div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
        <form method="GET" action="<?= base_url('kuitansi/kalibrasi') ?>" class="d-flex gap-2 align-items-end m-0" id="kalTrxForm">
            <input type="hidden" name="printer" value="<?= $printerId ?>">
            <div>
                <label class="form-label mb-1 fw-semibold" style="font-size:.8rem;" for="kalTrx">Data contoh <?= $sampleLabel ? '<span class="badge bg-primary">' . htmlspecialchars($sampleLabel) . '</span>' : '' ?></label>
                <select class="form-select form-select-sm" id="kalTrx" name="transaksi" style="min-width:260px;max-width:420px;">
                    <?php if (empty($trxList)): ?>
                        <option value="0">— belum ada transaksi —</option>
                    <?php endif; ?>
                    <?php foreach ($trxList as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $sampleId ? 'selected' : '' ?>>
                            #<?= (int) $t['id'] ?> · <?= htmlspecialchars($t['nomor_bukti'] ?? '-') ?> · Rp <?= number_format((float) ($t['nilai'] ?? 0), 0, ',', '.') ?> · <?= htmlspecialchars(mb_substr((string) ($t['uraian'] ?? ''), 0, 45)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <div class="form-check form-switch me-2">
            <input class="form-check-input" type="checkbox" id="kalSnap" checked>
            <label class="form-check-label" for="kalSnap" style="font-size:.85rem;">Snap 1mm</label>
        </div>
        <label class="btn btn-sm btn-outline-secondary mb-0" for="kalUpload">
            <i class="bi bi-image me-1"></i>Upload Contoh Kuitansi
        </label>
        <input type="file" id="kalUpload" accept=".jpg,.jpeg,.png,.pdf" class="d-none">
        <button type="button" class="btn btn-sm btn-outline-secondary mb-0" data-bs-toggle="modal" data-bs-target="#modalPustakaBlanko">
            <i class="bi bi-collection me-1"></i>Pustaka Blanko
            <span class="badge bg-secondary ms-1"><?= count($pustaka) ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-0" data-bs-toggle="modal" data-bs-target="#modalPotongBlanko" <?= $refUrl ? '' : 'disabled' ?>>
            <i class="bi bi-crop me-1"></i>Potong Blanko
        </button>
        <span class="text-muted" style="font-size:.8rem;" id="kalRefStatus"><?= $refUrl ? 'Background referensi terpasang.' : 'Belum ada gambar referensi.' ?> (JPG/PNG/PDF-scan, maks 10MB)</span>
        <label class="text-muted mb-0" style="font-size:.8rem;" for="kalOpacity">Transparansi
            <input type="range" id="kalOpacity" min="10" max="100" value="50" style="width:90px;vertical-align:middle;">
        </label>
        <div id="kalAspectWarn"></div>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalPetunjukCetak" title="Petunjuk pengaturan dialog cetak">
                <i class="bi bi-question-circle me-1"></i>Petunjuk Cetak Pas
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="kalUji">
                <i class="bi bi-crosshair me-1"></i>Cetak Uji
            </button>
            <button type="button" class="btn btn-sm btn-primary" id="kalSimpan">
                <i class="bi bi-save me-1"></i>Simpan
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-9 col-lg-8">
            <div class="kal-canvas-scroll">
                <div class="kal-canvas" id="kalCanvas">
                    <div class="kal-bg" id="kalBg"<?= ($refUrl && $refKind !== 'pdf') ? " style=\"background-image:url('" . htmlspecialchars($refUrl) . "');\"" : '' ?>></div>
                    <canvas class="kal-pdfbg d-none" id="kalPdfBg" width="860" height="660"></canvas>
                </div>
            </div>
            <div class="form-text mt-1" style="font-size:.78rem;">
                Agar snap ke gambar <strong>persis sama</strong> dengan hasil print: pangkas file tepat di tepi kertas
                (tanpa meja/latar, tanpa miring — foto miring tidak akan pernah akurat).
                Proporsi kertas 215:165. Peringatan muncul otomatis bila proporsi file menyimpang.<br>
                Teks pratinjau memakai ukuran, gaya, dan posisi font cetak (10&thinsp;pt; angka Terbilang 11&thinsp;pt bold;
                baris TTD 9&thinsp;pt; teks rata kiri mulai 1&thinsp;mm di dalam kotak, wrap selebar kotak dikurangi
                2&thinsp;mm — sama seperti FPDF). Di layar bisa tampak berbeda bila skala tampilan Windows/browser
                bukan 100%.
            </div>
        </div>
        <div class="col-xl-3 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-1">Elemen terpilih</h6>
                    <div id="kalSelName" class="text-muted mb-2" style="font-size:.85rem;">Klik salah satu kotak di kanvas.</div>
                    <div id="kalSelForm" class="d-none">
                        <label class="form-label mb-1" style="font-size:.8rem;">X (mm)</label>
                        <input type="number" class="form-control form-control-sm mb-2" id="kalInX" step="0.5" min="-20" max="235">
                        <label class="form-label mb-1" style="font-size:.8rem;">Y (mm)</label>
                        <input type="number" class="form-control form-control-sm mb-2" id="kalInY" step="0.5" min="-20" max="185">
                        <div>
                            <label class="form-label mb-1" style="font-size:.8rem;">Lebar (mm) — panjang/pendek kotak</label>
                            <div class="input-group input-group-sm mb-1">
                                <input type="number" class="form-control" id="kalInW" step="0.5" min="5" max="215" placeholder="default">
                                <button type="button" class="btn btn-outline-secondary" id="kalResetW" title="Kembalikan ke lebar default">Reset</button>
                            </div>
                            <div class="form-text mb-2" style="font-size:.75rem;" id="kalDefWInfo"></div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="kalInWrap">
                                <label class="form-check-label" for="kalInWrap" style="font-size:.8rem;">Wrap teks <span class="text-muted">(baris baru otomatis)</span></label>
                            </div>
                            <div class="form-text" style="font-size:.75rem;">Wrap = teks panjang turun ke bawah (MultiCell). Mati = satu baris, teks panjang meluber tanpa wrap.</div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2" style="font-size:.85rem;">Semua elemen</h6>
                    <?php if ($hiddenCount > 0): ?>
                        <div class="text-muted mb-2" style="font-size:.75rem;">
                            <?= $hiddenCount ?> elemen tidak dicetak pada data contoh ini (kosong / sudah pre-printed) dan disembunyikan dari kanvas.
                            Pilih data contoh lain bila ingin mengatur elemen tersebut.
                        </div>
                    <?php endif; ?>
                    <div class="list-group list-group-flush" id="kalList" style="font-size:.8rem;max-height:320px;overflow:auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
const SCALE = 4; // 1mm = 4px
const state = <?= json_encode($positions, JSON_UNESCAPED_UNICODE) ?>;
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
const widths = <?= json_encode($widths, JSON_UNESCAPED_UNICODE) ?>;
const defaultWidths = <?= json_encode($defaultWidths, JSON_UNESCAPED_UNICODE) ?>;
const dummy = <?= json_encode($dummy, JSON_UNESCAPED_UNICODE) ?>;
const sample = <?= json_encode($sampleTexts, JSON_UNESCAPED_UNICODE) ?>;
const fonts = <?= json_encode($fonts, JSON_UNESCAPED_UNICODE) ?>;
const order = <?= json_encode($order) ?>;
const PRINTER_ID = <?= (int) $printerId ?>;
let initial = JSON.stringify(state);
let selected = null;

const canvas = document.getElementById('kalCanvas');
const dirtyBadge = document.getElementById('kalDirty');
const alertBox = document.getElementById('kalAlert');
const BASE = '<?= rtrim(base_url(), '/') ?>/';
const REF_URL = <?= json_encode($refUrl) ?>;
const REF_KIND = <?= json_encode($refKind) ?>;
const PAPER_W = 215, PAPER_H = 165; // mm

function showAlert(msg, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
        + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
function markDirty() {
    dirtyBadge.classList.toggle('d-none', JSON.stringify(state) === initial);
}
function defaultWidthOf(key) {
    return parseFloat(defaultWidths[key] ?? widths[key] ?? 60);
}
function widthOf(key) {
    const ov = state[key]?.max_width_mm;
    if (ov !== null && ov !== undefined && ov !== '' && !isNaN(parseFloat(ov))) return parseFloat(ov);
    return parseFloat(widths[key] ?? defaultWidthOf(key));
}
function isWrap(key) {
    const wr = state[key]?.is_wrap;
    if (wr !== null && wr !== undefined && wr !== '') return !!parseInt(wr, 10);
    return key === 'uraian'; // default: hanya uraian yang wrap
}

// --- render kotak (semua bisa di-resize lebarnya) ---
const boxes = {};
order.forEach(key => {
    if (!state[key]) state[key] = { x_mm: 10, y_mm: 10, max_width_mm: null, is_wrap: null };
    const el = document.createElement('div');
    el.className = 'kal-box';
    el.dataset.key = key;
    el.innerHTML = '<span class="kal-badge"></span>'
        + '<span class="kal-txt"></span>'
        + '<span class="kal-resize" title="Geser untuk atur lebar (panjang/pendek)"></span>';
    canvas.appendChild(el);
    boxes[key] = el;
    el.addEventListener('pointerdown', e => onBoxDown(e, key));
    const rz = el.querySelector('.kal-resize');
    rz.addEventListener('pointerdown', e => onResizeDown(e, key));
});
function layout() {
    order.forEach(key => {
        const el = boxes[key], s = state[key];
        el.style.left = (s.x_mm * SCALE) + 'px';
        el.style.top = (s.y_mm * SCALE) + 'px';
        el.style.width = (widthOf(key) * SCALE) + 'px';
        el.classList.toggle('nowrap', !isWrap(key));
        el.classList.toggle('is-wrap', isWrap(key));
        // Isi kotak: data transaksi asli bila ada; dummy bila belum ada transaksi;
        // placeholder bila nilainya memang kosong (tidak dicetak).
        const txt = (sample[key] !== undefined && sample[key] !== null && sample[key] !== '')
            ? sample[key]
            : (Object.keys(sample).length > 0 ? '' : (dummy[key] || ''));
        const txtEl = el.querySelector('.kal-txt');
        if (txt === '') {
            txtEl.textContent = '(kosong — tidak dicetak)';
            txtEl.classList.add('kal-txt-empty');
        } else {
            txtEl.textContent = txt;
            txtEl.classList.remove('kal-txt-empty');
        }
        // Pratinjau memakai ukuran/gaya font cetak (pt) pada skala kanvas 1mm=4px.
        const f = fonts[key] || {};
        const fstyle = String(f.style || '');
        txtEl.style.fontSize = ((parseFloat(f.size) || 10) * (25.4 / 72) * SCALE).toFixed(2) + 'px';
        txtEl.style.fontFamily = 'Helvetica, Arial, sans-serif';
        txtEl.style.fontWeight = /B/.test(fstyle) ? '700' : '400';
        txtEl.style.fontStyle = /I/.test(fstyle) || txt === '' ? 'italic' : 'normal';
        txtEl.style.textDecoration = /U/.test(fstyle) ? 'underline' : 'none';
        txtEl.style.textAlign = f.align === 'C' ? 'center' : 'left';
        // FPDF: Cell() mulai teks di x+1mm (cMargin); MultiCell() membungkus
        // pada w-2mm; teks rata tengah yang lebih lebar dari w tetap TERPUSAT
        // (boleh melewati kedua sisi). Tiru semuanya agar pratinjau = cetak.
        const wrapTxt = isWrap(key) || txt.includes('\n');
        const centered = f.align === 'C';
        if (!wrapTxt && centered) {
            txtEl.style.position = 'absolute';
            txtEl.style.top = '0';
            txtEl.style.left = '50%';
            txtEl.style.width = 'max-content';
            txtEl.style.marginLeft = '0';
            txtEl.style.transform = 'translateX(-50%)';
        } else {
            txtEl.style.position = '';
            txtEl.style.top = '';
            txtEl.style.left = '';
            txtEl.style.transform = '';
            txtEl.style.width = ((wrapTxt ? widthOf(key) - 2 : widthOf(key)) * SCALE) + 'px';
            txtEl.style.marginLeft = ((wrapTxt || !centered) ? SCALE : 0) + 'px';
        }
        // Tinggi baris = h_mm cetak: Cell()/MultiCell() menaruh teks di tengah
        // kotak setinggi itu, jadi baseline pratinjau ikut sama.
        txtEl.style.lineHeight = ((parseFloat(f.line_h_mm) || 6) * SCALE) + 'px';
        el.querySelector('.kal-badge').textContent = 'x: ' + Number(s.x_mm).toFixed(1) + 'mm, y: ' + Number(s.y_mm).toFixed(1) + 'mm, w: ' + widthOf(key).toFixed(1) + 'mm' + (isWrap(key) ? ', wrap' : ', 1-baris');
        el.classList.toggle('selected', selected === key);
    });
    renderList();
    syncPanel();
    markDirty();
}

// --- drag ---
let drag = null;
function snap(v) {
    return document.getElementById('kalSnap').checked ? Math.round(v) : Math.round(v * 2) / 2;
}
function onBoxDown(e, key) {
    if (e.target.classList.contains('kal-resize')) return; // ditangani resize
    e.preventDefault();
    select(key);
    const el = boxes[key];
    el.setPointerCapture(e.pointerId);
    drag = { key, startX: e.clientX, startY: e.clientY, ox: state[key].x_mm, oy: state[key].y_mm };
    const move = ev => {
        state[key].x_mm = snap(drag.ox + (ev.clientX - drag.startX) / SCALE);
        state[key].y_mm = snap(drag.oy + (ev.clientY - drag.startY) / SCALE);
        layout();
    };
    const up = () => {
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerup', up);
        el.removeEventListener('pointercancel', up);
        drag = null;
    };
    el.addEventListener('pointermove', move);
    el.addEventListener('pointerup', up);
    el.addEventListener('pointercancel', up);
}

// --- resize lebar semua elemen (drag handle kanan) ---
function onResizeDown(e, key) {
    e.preventDefault(); e.stopPropagation();
    select(key);
    const startX = e.clientX, startW = widthOf(key);
    const h = e.target;
    try { h.setPointerCapture(e.pointerId); } catch (err) {}
    const move = ev => {
        let w = startW + (ev.clientX - startX) / SCALE;
        w = Math.min(215, Math.max(5, snap(w)));
        state[key].max_width_mm = w;
        layout();
    };
    const up = () => {
        h.removeEventListener('pointermove', move);
        h.removeEventListener('pointerup', up);
        h.removeEventListener('pointercancel', up);
    };
    h.addEventListener('pointermove', move);
    h.addEventListener('pointerup', up);
    h.addEventListener('pointercancel', up);
}

// --- panel + list ---
function select(key) {
    selected = key;
    layout();
    if (key) document.getElementById('kalSelName').textContent = (labels[key] || key);
}
function syncPanel() {
    const form = document.getElementById('kalSelForm');
    if (!selected || !state[selected]) { form.classList.add('d-none'); return; }
    form.classList.remove('d-none');
    if (document.activeElement !== document.getElementById('kalInX')) {
        document.getElementById('kalInX').value = state[selected].x_mm;
    }
    if (document.activeElement !== document.getElementById('kalInY')) {
        document.getElementById('kalInY').value = state[selected].y_mm;
    }
    const inW = document.getElementById('kalInW');
    if (document.activeElement !== inW) {
        const ov = state[selected].max_width_mm;
        inW.value = (ov === null || ov === undefined || ov === '') ? '' : ov;
        inW.placeholder = 'default ' + defaultWidthOf(selected).toFixed(1) + 'mm';
    }
    document.getElementById('kalDefWInfo').textContent =
        'Aktif: ' + widthOf(selected).toFixed(1) + 'mm (default ' + defaultWidthOf(selected).toFixed(1) + 'mm). Kosongkan untuk pakai default.';
    const inWrap = document.getElementById('kalInWrap');
    if (document.activeElement !== inWrap) {
        inWrap.checked = isWrap(selected);
    }
}
document.getElementById('kalInX').addEventListener('input', e => {
    if (!selected) return;
    const v = parseFloat(e.target.value);
    if (!isNaN(v)) {
        state[selected].x_mm = v;
        layout();
    }
});
document.getElementById('kalInY').addEventListener('input', e => {
    if (!selected) return;
    const v = parseFloat(e.target.value);
    if (!isNaN(v)) {
        state[selected].y_mm = v;
        layout();
    }
});
document.getElementById('kalInW').addEventListener('input', e => {
    if (!selected) return;
    const raw = e.target.value;
    if (raw === '' || raw === null) {
        state[selected].max_width_mm = null; // kembali ke default config
        layout();
        return;
    }
    const v = parseFloat(raw);
    if (!isNaN(v)) {
        state[selected].max_width_mm = Math.min(215, Math.max(5, v));
        layout();
    }
});
document.getElementById('kalResetW')?.addEventListener('click', () => {
    if (!selected) return;
    state[selected].max_width_mm = null;
    layout();
});
document.getElementById('kalInWrap').addEventListener('change', e => {
    if (!selected) return;
    state[selected].is_wrap = e.target.checked ? 1 : 0;
    layout();
});
function renderList() {
    const list = document.getElementById('kalList');
    list.innerHTML = '';
    order.forEach(key => {
        const s = state[key];
        const a = document.createElement('button');
        a.type = 'button';
        a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1 px-2'
            + (selected === key ? ' active' : '');
        a.innerHTML = '<span></span><small class="font-monospace"></small>';
        a.querySelector('span').textContent = (labels[key] || key) + (isWrap(key) ? ' ⏎' : ' →');
        a.querySelector('small').textContent = Number(s.x_mm).toFixed(1) + ', ' + Number(s.y_mm).toFixed(1) + ' · ' + widthOf(key).toFixed(0) + 'mm';
        a.title = (labels[key] || key) + ' — ' + widthOf(key).toFixed(1) + 'mm, ' + (isWrap(key) ? 'wrap' : 'satu baris');
        a.addEventListener('click', () => select(key));
        list.appendChild(a);
    });
}

// --- background: opacity, cek proporsi, render PDF ---
const bgDiv = document.getElementById('kalBg');
const pdfCanvas = document.getElementById('kalPdfBg');
document.getElementById('kalOpacity').addEventListener('input', function() {
    const op = (parseInt(this.value, 10) || 50) / 100;
    bgDiv.style.opacity = op;
    pdfCanvas.style.opacity = op;
});
function checkAspect(w, h, label) {
    if (!w || !h) return;
    const fileRatio = w / h, paperRatio = PAPER_W / PAPER_H;
    const dev = Math.abs(fileRatio - paperRatio) / paperRatio;
    const box = document.getElementById('kalAspectWarn');
    if (dev > 0.005) {
        box.innerHTML = '<div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:.8rem;">'
            + '<i class="bi bi-exclamation-triangle me-1"></i>Proporsi ' + label + ' (' + fileRatio.toFixed(3) + ') '
            + 'menyimpang ' + (dev * 100).toFixed(1) + '% dari kertas 215:165 (' + paperRatio.toFixed(3) + '). '
            + 'Snap ke gambar TIDAK akan sama dengan hasil print. Gunakan tombol <strong>Potong Blanko</strong> untuk memangkas tepat di tepi kertas.</div>';
    } else {
        box.innerHTML = '';
    }
}
function showImageBackground(url) {
    pdfCanvas.classList.add('d-none');
    bgDiv.style.backgroundImage = "url('" + url + "')";
    document.getElementById('kalRefStatus').textContent = 'Background referensi terpasang. (gambar)';
    const img = new Image();
    img.onload = () => checkAspect(img.naturalWidth, img.naturalHeight, 'gambar');
    img.src = url;
}
function loadPdfJs() {
    if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        s.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            resolve(window.pdfjsLib);
        };
        s.onerror = () => reject(new Error('gagal memuat PDF.js (perlu internet)'));
        document.head.appendChild(s);
    });
}
function renderPdfBackground(url) {
    // Halaman 1 PDF digambar pas 860x660 (mapping mm tetap 1mm=4px).
    // Akurat hanya bila halaman PDF memang seproporsi kertas 215:165.
    loadPdfJs().then(pdfjsLib => pdfjsLib.getDocument(url).promise).then(pdf => pdf.getPage(1)).then(page => {
        const vp0 = page.getViewport({ scale: 1 });
        checkAspect(vp0.width, vp0.height, 'halaman 1 PDF');
        // Render tajam lalu petakan pas 860x660 (sama seperti gambar: 1mm=4px).
        // Tepat hanya bila halaman PDF seproporsi kertas — lihat peringatan proporsi.
        const vp = page.getViewport({ scale: 2 });
        const off = document.createElement('canvas');
        off.width = vp.width; off.height = vp.height;
        return page.render({ canvasContext: off.getContext('2d'), viewport: vp }).promise.then(() => {
            const ctx = pdfCanvas.getContext('2d');
            ctx.clearRect(0, 0, 860, 660);
            ctx.drawImage(off, 0, 0, off.width, off.height, 0, 0, 860, 660);
            bgDiv.style.backgroundImage = 'none';
            pdfCanvas.classList.remove('d-none');
            document.getElementById('kalRefStatus').textContent = 'Background referensi terpasang. (PDF, hal. 1)';
        });
    }).catch(err => showAlert('Gagal menampilkan PDF: ' + err.message, 'danger'));
}
if (REF_KIND === 'pdf' && REF_URL) {
    renderPdfBackground(REF_URL);
} else if (REF_KIND === 'image' && REF_URL) {
    const img = new Image();
    img.onload = () => checkAspect(img.naturalWidth, img.naturalHeight, 'gambar');
    img.src = REF_URL;
}

// --- upload ---
document.getElementById('kalUpload').addEventListener('change', function() {
    if (!this.files.length) return;
    const fd = new FormData();
    fd.append('referensi', this.files[0]);
    fetch(BASE + 'kuitansi/kalibrasi/upload', { method: 'POST', body: fd })
        .then(r => r.json().then(j => ({ status: r.status, body: j })))
        .then(({ status, body }) => {
            if (!body.ok) throw new Error(body.message || ('HTTP ' + status));
            if (!isDirty()) {
                window.location.reload();
                return;
            }
            // Ada posisi belum disimpan: jangan buang dengan reload.
            if (body.kind === 'pdf') {
                renderPdfBackground(body.url);
            } else {
                showImageBackground(body.url);
            }
            showAlert('Blanko tersimpan di pustaka dan aktif. Simpan posisi lalu muat ulang halaman untuk melihat daftar pustaka.', 'success');
        })
        .catch(err => showAlert('Upload gagal: ' + err.message, 'danger'))
        .finally(() => { document.getElementById('kalUpload').value = ''; });
});

// --- peringatan jika ada perubahan yang belum disimpan ---
function isDirty() {
    return JSON.stringify(state) !== initial;
}

window.addEventListener('beforeunload', function(e) {
    if (isDirty()) {
        e.preventDefault();
        e.returnValue = 'Ada perubahan posisi kalibrasi yang belum disimpan!';
        return e.returnValue;
    }
});

// --- ganti data contoh: cek dirty dulu ---
const trxSelect = document.getElementById('kalTrx');
if (trxSelect) {
    let prevTrx = trxSelect.value;
    trxSelect.addEventListener('change', function() {
        if (isDirty()) {
            if (!confirm('Ada perubahan posisi kalibrasi yang belum disimpan. Yakin ingin mengganti data contoh tanpa menyimpan?')) {
                this.value = prevTrx;
                return;
            }
        }
        prevTrx = this.value;
        document.getElementById('kalTrxForm').submit();
    });
}

// --- pilih printer: muat ulang kanvas untuk printer tersebut (cek dirty) ---
const printerSelect = document.getElementById('kalPrinter');
if (printerSelect) {
    let prevPrinter = printerSelect.value;
    printerSelect.addEventListener('change', function() {
        if (isDirty()) {
            if (!confirm('Ada perubahan posisi kalibrasi yang belum disimpan. Yakin ingin mengganti printer tanpa menyimpan?')) {
                this.value = prevPrinter;
                return;
            }
        }
        prevPrinter = this.value;
        window.location.href = BASE + 'kuitansi/kalibrasi?printer=' + encodeURIComponent(this.value);
    });
}

// --- simpan ---
const btnSimpan = document.getElementById('kalSimpan');
btnSimpan.addEventListener('click', () => {
    btnSimpan.disabled = true;
    const oldHtml = btnSimpan.innerHTML;
    btnSimpan.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';

    const items = order.map(key => {
        const ov = state[key].max_width_mm;
        return {
            elemen_key: key,
            label: labels[key] || key,
            x_mm: state[key].x_mm,
            y_mm: state[key].y_mm,
            max_width_mm: (ov === null || ov === undefined || ov === '') ? null : parseFloat(ov),
            is_wrap: (() => {
                const wr = state[key].is_wrap;
                if (wr === null || wr === undefined || wr === '') return null;
                return parseInt(wr, 10) ? 1 : 0;
            })()
        };
    });
    fetch(BASE + 'kuitansi/kalibrasi/simpan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ printer_id: PRINTER_ID, items })
    })
        .then(r => r.json().then(j => ({ status: r.status, body: j })))
        .then(({ status, body }) => {
            if (!body.ok) throw new Error(body.message || ('HTTP ' + status));
            btnSimpan.className = 'btn btn-sm btn-success text-white shadow-sm';
            btnSimpan.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Tersimpan!';
            showAlert('Posisi kalibrasi berhasil disimpan (' + body.saved + ' elemen) ke printer ini. Setting tidak akan berubah.', 'success');
            initial = JSON.stringify(state);
            markDirty();
            setTimeout(() => {
                btnSimpan.className = 'btn btn-sm btn-primary';
                btnSimpan.innerHTML = oldHtml;
                btnSimpan.disabled = false;
            }, 2500);
        })
        .catch(err => {
            btnSimpan.className = 'btn btn-sm btn-danger';
            btnSimpan.innerHTML = '<i class="bi bi-x-circle me-1"></i>Gagal Simpan';
            showAlert('Simpan gagal: ' + err.message, 'danger');
            setTimeout(() => {
                btnSimpan.className = 'btn btn-sm btn-primary';
                btnSimpan.innerHTML = oldHtml;
                btnSimpan.disabled = false;
            }, 3000);
        });
});

// --- cetak uji (state kanvas saat ini, belum tentu tersimpan) ---
const btnUji = document.getElementById('kalUji');
btnUji.addEventListener('click', () => {
    btnUji.disabled = true;
    const oldUjiHtml = btnUji.innerHTML;
    btnUji.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';

    const positions = {};
    order.forEach(key => {
        const ov = state[key].max_width_mm;
        const wr = state[key].is_wrap;
        positions[key] = {
            x_mm: state[key].x_mm,
            y_mm: state[key].y_mm,
            max_width_mm: (ov === null || ov === undefined || ov === '') ? null : parseFloat(ov),
            is_wrap: (wr === null || wr === undefined || wr === '') ? null : (parseInt(wr, 10) ? 1 : 0)
        };
    });
    fetch(BASE + 'kuitansi/kalibrasi/uji', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ positions })
    })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.blob();
        })
        .then(blob => {
            const url = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
            window.open(url, '_blank');
        })
        .catch(err => showAlert('Cetak uji gagal: ' + err.message, 'danger'))
        .finally(() => {
            btnUji.disabled = false;
            btnUji.innerHTML = oldUjiHtml;
        });
});

// --- potong blanko: pangkas file scan agar pas rasio kertas 215:165 ---
// Modal berada setelah tag script, jadi tunggu DOM selesai diparsing.
document.addEventListener('DOMContentLoaded', function () {
const POTONG_RATIO = PAPER_W / PAPER_H;
const potong = { loadedFor: null, srcCanvas: null, srcW: 0, srcH: 0, pageWmm: 0, pageHmm: 0, rect: null, drag: null };
const pCanvas = document.getElementById('potongSrc');
const pRectEl = document.getElementById('potongRect');
const pLock = document.getElementById('potongLock');
const pSimpan = document.getElementById('potongSimpan');
const potongModal = document.getElementById('modalPotongBlanko');

function pStatus(html) {
    document.getElementById('potongWarn').innerHTML = html || '';
}
// Rasio target dalam piksel sumber (mm horizontal & vertikal bisa beda skala).
function pPxRatio() {
    return POTONG_RATIO * (potong.srcW / potong.pageWmm) / (potong.srcH / potong.pageHmm);
}
function pRefresh() {
    if (!potong.rect) return;
    const r = potong.rect;
    const pct = (v, total) => (v / total * 100) + '%';
    pRectEl.style.left = pct(r.x, potong.srcW);
    pRectEl.style.top = pct(r.y, potong.srcH);
    pRectEl.style.width = pct(r.w, potong.srcW);
    pRectEl.style.height = pct(r.h, potong.srcH);

    const mmX = potong.srcW / potong.pageWmm;
    const mmY = potong.srcH / potong.pageHmm;
    document.getElementById('potongX').value = (r.x / mmX).toFixed(1);
    document.getElementById('potongY').value = (r.y / mmY).toFixed(1);
    document.getElementById('potongW').value = (r.w / mmX).toFixed(1);
    document.getElementById('potongH').value = (r.h / mmY).toFixed(1);

    const wmm = r.w / mmX, hmm = r.h / mmY;
    const ratio = wmm / hmm;
    const dev = Math.abs(ratio - POTONG_RATIO) / POTONG_RATIO;
    document.getElementById('potongInfo').innerHTML =
        'Hasil potong: <strong>' + wmm.toFixed(1) + ' × ' + hmm.toFixed(1) + ' mm</strong>' +
        ' · rasio ' + ratio.toFixed(3) +
        (dev > 0.005
            ? ' <span class="text-danger">— menyimpang ' + (dev * 100).toFixed(1) + '% dari 215:165, hasil akan diregangkan di kanvas.</span>'
            : ' <span class="text-success">— rasio pas dengan kertas.</span>');
}
function pSetRect(r) {
    const W = potong.srcW, H = potong.srcH;
    const minW = Math.max(16, W * 0.02), minH = Math.max(16, H * 0.02);
    r.w = Math.min(Math.max(r.w, minW), W);
    r.h = Math.min(Math.max(r.h, minH), H);
    r.x = Math.min(Math.max(r.x, 0), W - r.w);
    r.y = Math.min(Math.max(r.y, 0), H - r.h);
    potong.rect = r;
    pRefresh();
}
// Kotak terbesar di tengah dengan rasio kertas (membuang sisa tepi).
function pFitRect() {
    const W = potong.srcW, H = potong.srcH, k = pPxRatio();
    const h = Math.min(H, W / k);
    const w = h * k;
    pSetRect({ x: (W - w) / 2, y: (H - h) / 2, w: w, h: h });
}
function pFullRect() {
    pSetRect({ x: 0, y: 0, w: potong.srcW, h: potong.srcH });
}
function pLoad() {
    if (!REF_URL) {
        pStatus('Belum ada blanko aktif. Upload blanko dulu.');
        return;
    }
    if (potong.loadedFor === REF_URL + '|' + REF_KIND && potong.srcCanvas) {
        pRefresh();
        return;
    }
    pStatus('<span class="spinner-border spinner-border-sm me-1" role="status"></span>Memuat pratinjau…');
    pSimpan.disabled = true;
    const finish = (canvas, wmm, hmm) => {
        potong.srcCanvas = canvas;
        potong.srcW = canvas.width;
        potong.srcH = canvas.height;
        potong.pageWmm = wmm;
        potong.pageHmm = hmm;
        pCanvas.width = canvas.width;
        pCanvas.height = canvas.height;
        pCanvas.getContext('2d').drawImage(canvas, 0, 0);
        potong.loadedFor = REF_URL + '|' + REF_KIND;
        pStatus('');
        pFitRect();
        pSimpan.disabled = false;
    };
    if (REF_KIND === 'pdf') {
        loadPdfJs().then(lib => lib.getDocument(REF_URL).promise)
            .then(pdf => pdf.getPage(1))
            .then(page => {
                const vp1 = page.getViewport({ scale: 1 });
                const scale = Math.min(3, 2400 / Math.max(vp1.width, vp1.height));
                const vp = page.getViewport({ scale: scale });
                const c = document.createElement('canvas');
                c.width = Math.max(1, Math.round(vp.width));
                c.height = Math.max(1, Math.round(vp.height));
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, c.width, c.height);
                return page.render({ canvasContext: ctx, viewport: vp }).promise.then(() => {
                    // Ukuran halaman PDF sesungguhnya (pt → mm) untuk input mm.
                    finish(c, vp1.width * 25.4 / 72, vp1.height * 25.4 / 72);
                });
            })
            .catch(err => pStatus('<i class="bi bi-exclamation-triangle me-1"></i>Gagal memuat PDF: ' + err.message));
    } else {
        const img = new Image();
        img.onload = () => {
            const s = Math.min(1, 3000 / Math.max(img.naturalWidth, img.naturalHeight));
            const c = document.createElement('canvas');
            c.width = Math.max(1, Math.round(img.naturalWidth * s));
            c.height = Math.max(1, Math.round(img.naturalHeight * s));
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(img, 0, 0, c.width, c.height);
            // Sama seperti kanvas: seluruh gambar dianggap kertas 215x165 mm.
            finish(c, PAPER_W, PAPER_H);
        };
        img.onerror = () => pStatus('<i class="bi bi-exclamation-triangle me-1"></i>Gagal memuat gambar referensi.');
        img.src = REF_URL;
    }
}
pRectEl.addEventListener('pointerdown', e => {
    if (!potong.rect) return;
    potong.drag = {
        h: (e.target.dataset && e.target.dataset.h) ? e.target.dataset.h : 'move',
        sx: e.clientX,
        sy: e.clientY,
        o: Object.assign({}, potong.rect)
    };
    pRectEl.setPointerCapture(e.pointerId);
    e.preventDefault();
});
pRectEl.addEventListener('pointermove', e => {
    if (!potong.drag) return;
    const dispW = document.getElementById('potongWrap').getBoundingClientRect().width || 1;
    const scale = potong.srcW / dispW;
    const dx = (e.clientX - potong.drag.sx) * scale;
    const dy = (e.clientY - potong.drag.sy) * scale;
    const { h, o } = potong.drag;
    const W = potong.srcW, H = potong.srcH;
    const minPx = Math.max(16, W * 0.02);
    const r = Object.assign({}, o);
    if (h === 'move') {
        r.x = o.x + dx;
        r.y = o.y + dy;
    } else {
        const west = h === 'nw' || h === 'sw';
        const north = h === 'nw' || h === 'ne';
        const ax = west ? o.x + o.w : o.x;
        const ay = north ? o.y + o.h : o.y;
        const px = (west ? o.x : o.x + o.w) + dx;
        const py = (north ? o.y : o.y + o.h) + dy;
        const maxW = px < ax ? ax : W - ax;
        const maxH = py < ay ? ay : H - ay;
        let w = Math.min(Math.abs(px - ax), maxW);
        let hh = Math.min(Math.abs(py - ay), maxH);
        if (pLock.checked) {
            const k = pPxRatio();
            w = Math.min(w, maxH * k);
            w = Math.max(minPx, Math.min(w, maxW));
            hh = w / k;
        } else {
            w = Math.max(minPx, w);
            hh = Math.max(minPx, hh);
        }
        r.w = w;
        r.h = hh;
        r.x = px < ax ? ax - w : ax;
        r.y = py < ay ? ay - hh : ay;
    }
    pSetRect(r);
});
const pEndDrag = () => { potong.drag = null; };
pRectEl.addEventListener('pointerup', pEndDrag);
pRectEl.addEventListener('pointercancel', pEndDrag);

['x', 'y', 'w', 'h'].forEach(function (dim) {
    document.getElementById('potong' + dim.toUpperCase()).addEventListener('change', function () {
        if (!potong.rect) return;
        const mmX = potong.srcW / potong.pageWmm;
        const mmY = potong.srcH / potong.pageHmm;
        const v = id => parseFloat(document.getElementById('potong' + id.toUpperCase()).value);
        let x = v('x'), y = v('y'), w = v('w'), hh = v('h');
        if (![x, y, w, hh].every(Number.isFinite)) { pRefresh(); return; }
        w = Math.max(10, w);
        hh = Math.max(10, hh);
        const r = { x: x * mmX, y: y * mmY, w: w * mmX, h: hh * mmY };
        if (pLock.checked) {
            const k = pPxRatio();
            if (dim === 'h') r.w = r.h * k; else r.h = r.w / k;
            if (r.w > potong.srcW) { r.w = potong.srcW; r.h = r.w / k; }
            if (r.h > potong.srcH) { r.h = potong.srcH; r.w = r.h * k; }
        }
        pSetRect(r);
    });
});
document.getElementById('potongFit').addEventListener('click', () => { if (potong.srcW) pFitRect(); });
document.getElementById('potongFull').addEventListener('click', () => { if (potong.srcW) pFullRect(); });
pSimpan.addEventListener('click', function () {
    if (!potong.srcCanvas || !potong.rect) return;
    const r = potong.rect;
    const outScale = Math.min(1, 2200 / r.w);
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(r.w * outScale));
    c.height = Math.max(1, Math.round(r.h * outScale));
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(potong.srcCanvas, r.x, r.y, r.w, r.h, 0, 0, c.width, c.height);
    c.toBlob(function (blob) {
        if (!blob) { showAlert('Gagal membuat hasil potongan.', 'danger'); return; }
        const fd = new FormData();
        fd.append('referensi', blob, 'potong_kuitansi_' + Date.now() + '.jpg');
        pSimpan.disabled = true;
        fetch(BASE + 'kuitansi/kalibrasi/upload', { method: 'POST', body: fd })
            .then(resp => resp.json().then(j => ({ status: resp.status, body: j })))
            .then(({ status, body }) => {
                if (!body.ok) throw new Error(body.message || ('HTTP ' + status));
                if (!isDirty()) {
                    window.location.reload();
                    return;
                }
                bootstrap.Modal.getOrCreateInstance(potongModal).hide();
                showAlert('Potongan tersimpan di pustaka dan aktif. Simpan posisi lalu muat ulang halaman.', 'success');
            })
            .catch(err => showAlert('Gagal menyimpan potongan: ' + err.message, 'danger'))
            .finally(() => { pSimpan.disabled = false; });
    }, 'image/jpeg', 0.92);
});
potongModal.addEventListener('shown.bs.modal', pLoad);
});

layout();
})();
</script>

<!-- Modal Petunjuk Cetak Pas -->
<div class="modal fade" id="modalPetunjukCetak" tabindex="-1" aria-labelledby="modalPetunjukCetakLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fs-6 fw-bold" id="modalPetunjukCetakLabel">
                    <i class="bi bi-printer me-2"></i>Petunjuk Pengaturan Cetak Kuitansi Agar Pas &amp; Presisi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-4 border-warning">
                    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0 text-warning"></i>
                    <div style="font-size: 0.88rem;">
                        <strong class="text-dark">Penyebab Paling Sering Cetakan Melenceng / Bergeser:</strong><br>
                        Browser otomatis mengaktifkan fitur <em>"Fit to printable area"</em> (Sesuaikan dengan area cetak / Fit to paper). Ini menyebabkan dokumen kuitansi ukuran <strong>215 &times; 165 mm</strong> diperkecil otomatis (skala 91%&ndash;94%), sehingga semua tulisan tergeser mengecil ke tengah kertas!
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-sliders2 me-2 text-primary"></i>Pengaturan Wajib di Jendela Cetak Browser (Chrome / Edge / Acrobat):</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 26%;">Pengaturan</th>
                                <th style="width: 34%;">Pilihan yang WAJIB Dipilih</th>
                                <th>Keterangan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-bold text-dark">1. Skala / Scale</td>
                                <td>
                                    <span class="badge bg-success fs-6"><i class="bi bi-check-lg me-1"></i>100% / Actual Size</span><br>
                                    <small class="text-muted">(Ukuran Sebenarnya)</small>
                                </td>
                                <td><strong class="text-danger">JANGAN</strong> pilih <em>"Fit to printable area"</em> atau <em>"Fit to page"</em>. Skala harus murni 100% agar ukuran milimeter sama persis dengan fisik formulir kertas kuitansi.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-dark">2. Ukuran Kertas</td>
                                <td>
                                    <span class="badge bg-primary fs-6"><i class="bi bi-file-earmark me-1"></i>215 &times; 165 mm</span><br>
                                    <small class="text-muted">(Kuitansi / Custom / Half Folio)</small>
                                </td>
                                <td>Pilih ukuran kertas kuitansi <strong>215 &times; 165 mm</strong>, orientasi <strong>Landscape</strong> (mendatar).</td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-dark">3. Margin</td>
                                <td>
                                    <span class="badge bg-dark fs-6">None / Tanpa Margin</span>
                                </td>
                                <td>Pilih <strong>None</strong> atau <strong>Minimum</strong> agar browser tidak menambahkan margin tepi putih buatan sendiri.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-dark">4. Header &amp; Footer</td>
                                <td>
                                    <span class="badge bg-secondary fs-6">Nonaktifkan / Uncheck</span>
                                </td>
                                <td>Hilangkan tanda centang agar URL web, judul, dan tanggal browser tidak ikut tercetak di pojok kertas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-check2-circle me-2 text-primary"></i>Langkah Praktis Kalibrasi:</h6>
                <ol class="mb-0 text-secondary" style="font-size: 0.85rem; line-height: 1.6;">
                    <li>Pastikan printer yang aktif di menu atas adalah printer yang Anda gunakan, dan berstatus <strong>Default</strong>.</li>
                    <li>Geser kotak pada kanvas ke posisi yang tepat sesuai blangko kuitansi fisik Anda.</li>
                    <li>Klik tombol <strong>Simpan</strong> (tombol akan menyala hijau <em>✓ Tersimpan!</em> menandakan posisi tersimpan permanen di database).</li>
                    <li>Klik <strong>Cetak Uji</strong> untuk menguji hasil cetakan garis uji coba pada kertas kuitansi fisik Anda.</li>
                    <li>Jika sudah pas, kuitansi dari menu <strong>Transaksi</strong> akan tercetak presisi di titik yang sama.</li>
                </ol>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-primary px-4 fw-semibold" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPustakaBlanko" tabindex="-1" aria-labelledby="modalPustakaBlankoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6 fw-bold" id="modalPustakaBlankoLabel">
                    <i class="bi bi-collection me-1"></i>Pustaka Blanko Kuitansi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0">
                <?php if (empty($pustaka)): ?>
                    <div class="p-4 text-center text-muted" style="font-size:.9rem;">
                        Belum ada blanko di pustaka. Upload contoh kuitansi untuk mulai menyimpan.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($pustaka as $b):
                            $bId = (int) $b['id'];
                            $aktif = $bId === $blankoAktifId;
                            $isPdf = ($b['kind'] ?? '') === 'pdf';
                        ?>
                            <div class="list-group-item d-flex align-items-center gap-2 py-2">
                                <i class="bi <?= $isPdf ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-primary' ?> fs-5"></i>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="fw-semibold text-truncate" title="<?= htmlspecialchars((string) $b['nama_asli']) ?>">
                                        <?= htmlspecialchars((string) $b['nama_asli']) ?>
                                        <?php if ($aktif): ?><span class="badge bg-success ms-1">Aktif</span><?php endif; ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $b['created_at']))) ?>
                                        · <?= number_format(((int) $b['ukuran']) / 1024, 0, ',', '.') ?> KB
                                        · <?= htmlspecialchars((string) ($b['uploaded_by'] ?? '-')) ?>
                                    </div>
                                </div>
                                <?php if (!$aktif): ?>
                                    <form method="POST" action="<?= base_url('kuitansi/kalibrasi/blanko/aktifkan/' . $bId) ?>" class="m-0">
                                        <input type="hidden" name="printer" value="<?= $printerId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-check2-circle me-1"></i>Gunakan
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="<?= base_url('kuitansi/kalibrasi/blanko/hapus/' . $bId) ?>" class="m-0"
                                      onsubmit="return confirm('Hapus blanko ini dari pustaka? File akan dihapus permanen.');">
                                    <input type="hidden" name="printer" value="<?= $printerId ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus dari pustaka">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light py-2">
                <span class="text-muted me-auto" style="font-size:.78rem;">
                    Blanko aktif dipakai sebagai background kanvas. Yang lain tetap tersimpan untuk dipakai lagi tanpa upload ulang.
                </span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    #potongWrap { position: relative; display: inline-block; max-width: 100%; line-height: 0; }
    #potongSrc { display: block; max-width: 100%; height: auto; user-select: none; -webkit-user-drag: none; }
    #potongRect { position: absolute; border: 2px dashed #0d6efd; box-shadow: 0 0 0 9999px rgba(0, 0, 0, .38); cursor: move; touch-action: none; }
    .potong-handle { position: absolute; width: 13px; height: 13px; background: #0d6efd; border: 2px solid #fff; border-radius: 50%; }
    .potong-handle[data-h="nw"] { left: -7px; top: -7px; cursor: nwse-resize; }
    .potong-handle[data-h="ne"] { right: -7px; top: -7px; cursor: nesw-resize; }
    .potong-handle[data-h="sw"] { left: -7px; bottom: -7px; cursor: nesw-resize; }
    .potong-handle[data-h="se"] { right: -7px; bottom: -7px; cursor: nwse-resize; }
</style>

<div class="modal fade" id="modalPotongBlanko" tabindex="-1" aria-labelledby="modalPotongBlankoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6 fw-bold" id="modalPotongBlankoLabel">
                    <i class="bi bi-crop me-1"></i>Potong Blanko Kuitansi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="potongWarn"></div>
                <div class="text-center bg-light rounded p-2" style="max-height:58vh;overflow:auto;">
                    <div id="potongWrap">
                        <canvas id="potongSrc"></canvas>
                        <div id="potongRect">
                            <span class="potong-handle" data-h="nw"></span>
                            <span class="potong-handle" data-h="ne"></span>
                            <span class="potong-handle" data-h="sw"></span>
                            <span class="potong-handle" data-h="se"></span>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mt-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label mb-1" style="font-size:.78rem;" for="potongX">Kiri (mm)</label>
                        <input type="number" class="form-control form-control-sm" id="potongX" step="0.5" min="0" style="width:90px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-1" style="font-size:.78rem;" for="potongY">Atas (mm)</label>
                        <input type="number" class="form-control form-control-sm" id="potongY" step="0.5" min="0" style="width:90px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-1" style="font-size:.78rem;" for="potongW">Lebar (mm)</label>
                        <input type="number" class="form-control form-control-sm" id="potongW" step="0.5" min="10" style="width:90px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-1" style="font-size:.78rem;" for="potongH">Tinggi (mm)</label>
                        <input type="number" class="form-control form-control-sm" id="potongH" step="0.5" min="10" style="width:90px;">
                    </div>
                    <div class="col-auto">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="potongLock" checked>
                            <label class="form-check-label" for="potongLock" style="font-size:.82rem;">Kunci rasio kertas 215:165</label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="potongFit">Paskan ke kertas</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="potongFull">Seluruh halaman</button>
                    </div>
                </div>
                <div class="text-muted mt-2" style="font-size:.78rem;" id="potongInfo"></div>
                <div class="form-text mt-1" style="font-size:.76rem;">
                    Geser kotak atau tarik sudutnya. Hasil potongan disimpan sebagai blanko baru di pustaka dan langsung aktif —
                    file asli tetap tersimpan. Hasil akan dipetakan penuh ke kertas 215&thinsp;×&thinsp;165&thinsp;mm, jadi
                    potong tepat di tepi kertas blanko agar posisi cetak akurat.
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary" id="potongSimpan">
                    <i class="bi bi-save me-1"></i>Simpan Potongan ke Pustaka
                </button>
            </div>
        </div>
    </div>
</div>
