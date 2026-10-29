<?php
/**
 * View: Kalibrasi visual posisi cetak lembar 2 SPPD (per printer).
 * Kanvas = halaman A4 (1mm = 4px); blanko hasil scan ditempatkan pada
 * offset kertas. Posisi elemen disimpan dalam mm absolut pada A4.
 */
$data = $kalibrasiData ?? [];
$positions = $data['positions'] ?? [];
$defaults = $data['defaults'] ?? [];
$widths = $data['widths'] ?? [];
$defaultWidths = $data['defaultWidths'] ?? $widths;
$defaultLines = $data['defaultLines'] ?? [];
$labels = $data['labels'] ?? [];
$order = $data['order'] ?? [];
$sampleTexts = $data['sampleTexts'] ?? [];
$refUrl = $data['refUrl'] ?? null;
$refKind = $data['refKind'] ?? null;
$flash = $data['flash'] ?? null;
$flashType = $data['flashType'] ?? 'info';
$printers = $data['printers'] ?? [];
$printerId = (int) ($data['printerId'] ?? 0);
$content = $data['content'] ?? [];
$offset = $data['offset'] ?? ['x' => 0, 'y' => 0];
$contentW = (float) ($content['width_mm'] ?? 197.2);
$contentH = (float) ($content['height_mm'] ?? 283.3);
$offX = (float) ($offset['x'] ?? 0);
$offY = (float) ($offset['y'] ?? 0);
$activePrinter = null;
foreach ($printers as $p) {
    if ((int) ($p['id'] ?? 0) === $printerId) { $activePrinter = $p; break; }
}
?>
<style>
.kal-canvas-scroll { overflow:auto; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; max-width:100%; max-height:78vh; }
.kal-canvas { position:relative; width:840px; height:1188px; background:#fff; flex:0 0 auto; }
.kal-canvas .kal-bg { position:absolute; background-size:100% 100%; background-repeat:no-repeat; opacity:.5; pointer-events:none; }
.kal-canvas canvas.kal-pdfbg { position:absolute; opacity:.5; pointer-events:none; }
.kal-box { position:absolute; border:1.5px solid #2563eb; background:rgba(37,99,235,.07); border-radius:3px; padding:0 2px; cursor:move; user-select:none; touch-action:none; box-sizing:border-box; font-family:Arial,Helvetica,sans-serif; overflow:hidden; }
.kal-box .kal-txt { display:block; font-size:14.8px; color:#0f172a; line-height:1.32; height:100%; box-sizing:border-box; }
.kal-box.one-line .kal-txt { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.kal-box.multi .kal-txt { white-space:normal; word-break:break-word; text-align:center; }
.kal-box[data-align="C"] .kal-txt { text-align:center; }
.kal-box .kal-badge { position:absolute; top:-19px; left:0; font-size:10px; background:#0f172a; color:#fff; border-radius:4px; padding:1px 6px; white-space:nowrap; display:none; z-index:5; }
.kal-box.selected { border-color:#dc2626; background:rgba(220,38,38,.08); box-shadow:0 0 0 2px rgba(220,38,38,.25); z-index:4; }
.kal-box.selected .kal-badge { display:block; }
.kal-resize { position:absolute; right:-5px; top:0; bottom:0; width:10px; cursor:ew-resize; z-index:6; }
.kal-resize::after { content:''; position:absolute; right:2px; top:15%; bottom:15%; width:3px; border-radius:2px; background:#2563eb; }
</style>

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Kalibrasi Lembar 2 SPPD</li>
        </ol>
    </nav>

    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
        <h4 class="fw-bold mb-0"><i class="bi bi-printer text-primary me-2"></i>Kalibrasi Cetak Lembar 2 SPPD</h4>
        <span id="kalDirty" class="badge bg-warning text-dark d-none">belum disimpan</span>
    </div>
    <p class="text-muted mb-3" style="font-size:.875rem;">
        Kanvas = halaman <strong>A4 210&thinsp;&times;&thinsp;297&thinsp;mm</strong> (skala 1&thinsp;mm = 4&thinsp;px),
        blanko scan ditempatkan pada posisi kertasnya. Geser tiap kotak ke posisi yang tepat.
        Kalibrasi disimpan <strong>per printer</strong> dan dipakai saat mencetak lembar 2 SPPD dari printer default.
    </p>

    <?php if (empty($printers)): ?>
        <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div><i class="bi bi-exclamation-triangle-fill me-1"></i>Belum ada printer terdaftar. Posisi hasil kalibrasi butuh printer tujuan.</div>
            <a class="btn btn-sm btn-warning fw-semibold" href="<?= base_url('kuitansi/kalibrasi') ?>"><i class="bi bi-plus-lg me-1"></i>Tambah Printer di Kalibrasi Kuitansi</a>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3 d-flex gap-2 flex-wrap align-items-end">
                <div>
                    <label class="form-label mb-1 fw-semibold" style="font-size:.8rem;" for="kalPrinter">Printer</label>
                    <select class="form-select form-select-sm" id="kalPrinter" style="min-width:220px;">
                        <?php foreach ($printers as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $printerId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nama']) ?><?= !empty($p['is_default']) ? ' ★ default' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($activePrinter && empty($activePrinter['is_default'])): ?>
                    <span class="badge bg-warning text-dark py-2 mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Bukan printer default — cetak dari menu memakai printer default</span>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary mb-1 ms-auto" href="<?= base_url('kuitansi/kalibrasi') ?>">
                    <i class="bi bi-gear me-1"></i>Kelola Printer
                </a>
            </div>
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
        <div class="form-check form-switch me-2">
            <input class="form-check-input" type="checkbox" id="kalSnap" checked>
            <label class="form-check-label" for="kalSnap" style="font-size:.85rem;">Snap 1&thinsp;mm</label>
        </div>
        <label class="btn btn-sm btn-outline-secondary mb-0" for="kalUpload">
            <i class="bi bi-image me-1"></i>Upload Contoh Blanko
        </label>
        <input type="file" id="kalUpload" accept=".jpg,.jpeg,.png,.pdf" class="d-none">
        <span class="text-muted" style="font-size:.8rem;" id="kalRefStatus"><?= $refUrl ? 'Background referensi terpasang.' : 'Belum ada gambar referensi.' ?> (JPG/PNG/PDF-scan, maks 10MB)</span>
        <label class="text-muted mb-0" style="font-size:.8rem;" for="kalOpacity">Transparansi
            <input type="range" id="kalOpacity" min="10" max="100" value="50" style="width:90px;vertical-align:middle;">
        </label>
        <div id="kalAspectWarn"></div>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="kalUji">
                <i class="bi bi-crosshair me-1"></i>Cetak Uji
            </button>
            <button type="button" class="btn btn-sm btn-primary" id="kalSimpan" <?= $printerId <= 0 ? 'disabled' : '' ?>>
                <i class="bi bi-save me-1"></i>Simpan
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-9 col-lg-8">
            <div class="kal-canvas-scroll">
                <div class="kal-canvas" id="kalCanvas">
                    <div class="kal-bg" id="kalBg"<?= ($refUrl && $refKind !== 'pdf') ? " style=\"left:" . ($offX * 4) . "px;top:" . ($offY * 4) . "px;width:" . ($contentW * 4) . "px;height:" . ($contentH * 4) . "px;background-image:url('" . htmlspecialchars($refUrl) . "');\"" : '' ?>></div>
                    <canvas class="kal-pdfbg d-none" id="kalPdfBg"></canvas>
                </div>
            </div>
            <div class="form-text mt-1" style="font-size:.78rem;">
                Agar snap ke gambar sama dengan hasil print: pangkas file tepat di tepi blanko (tanpa meja/latar, tidak miring).
                Proporsi blanko <?= number_format($contentW, 1, ',', '.') ?> : <?= number_format($contentH, 1, ',', '.') ?>.
            </div>
        </div>
        <div class="col-xl-3 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-1">Elemen terpilih</h6>
                    <div id="kalSelName" class="text-muted mb-2" style="font-size:.85rem;">Klik salah satu kotak di kanvas.</div>
                    <div id="kalSelForm" class="d-none">
                        <div class="row g-2 mb-1">
                            <div class="col-6">
                                <label class="form-label mb-1" style="font-size:.8rem;">X (mm)</label>
                                <input type="number" class="form-control form-control-sm" id="kalInX" step="0.5" min="-10" max="215">
                            </div>
                            <div class="col-6">
                                <label class="form-label mb-1" style="font-size:.8rem;">Y (mm)</label>
                                <input type="number" class="form-control form-control-sm" id="kalInY" step="0.5" min="-10" max="300">
                            </div>
                        </div>
                        <label class="form-label mb-1" style="font-size:.8rem;">Lebar (mm)</label>
                        <div class="input-group input-group-sm mb-1">
                            <input type="number" class="form-control" id="kalInW" step="0.5" min="5" max="210" placeholder="default">
                            <button type="button" class="btn btn-outline-secondary" id="kalResetW" title="Kembalikan ke lebar default">Reset</button>
                        </div>
                        <div class="form-text mb-2" style="font-size:.75rem;" id="kalDefWInfo"></div>
                        <label class="form-label mb-1" style="font-size:.8rem;">Maks. baris teks</label>
                        <div class="input-group input-group-sm mb-1">
                            <input type="number" class="form-control" id="kalInLines" step="1" min="1" max="4" placeholder="default">
                            <button type="button" class="btn btn-outline-secondary" id="kalResetLines" title="Kembalikan ke default">Reset</button>
                        </div>
                        <div class="form-text" style="font-size:.75rem;">2 baris = teks panjang (mis. jabatan) turun ke baris kedua.</div>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2" style="font-size:.85rem;">Semua elemen</h6>
                    <div class="list-group list-group-flush" id="kalList" style="font-size:.8rem;max-height:420px;overflow:auto;"></div>
                </div>
            </div>
            <div class="alert alert-light border mt-3 py-2 px-3" style="font-size:.78rem;">
                <strong>Cara pakai:</strong> geser kotak sesuai blanko &rarr; <em>Cetak Uji</em> &rarr; sesuaikan &rarr; <em>Simpan</em>.
                Cetak PDF dengan skala <strong>100% / Actual size</strong>, margin <strong>None</strong>, tanpa header/footer.
            </div>
        </div>
    </div>
</div>

<script>
(function() {
const SCALE = 4;                 // 1mm = 4px
const PAGE_W = 210, PAGE_H = 297;
const CONTENT_W = <?= json_encode($contentW) ?>, CONTENT_H = <?= json_encode($contentH) ?>;
const OFF_X = <?= json_encode($offX) ?>, OFF_Y = <?= json_encode($offY) ?>;
const state = <?= json_encode($positions, JSON_UNESCAPED_UNICODE) ?>;
const defaults = <?= json_encode($defaults, JSON_UNESCAPED_UNICODE) ?>;
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
const aligns = <?= json_encode($aligns ?? [], JSON_UNESCAPED_UNICODE) ?>;
const widths = <?= json_encode($widths, JSON_UNESCAPED_UNICODE) ?>;
const defaultWidths = <?= json_encode($defaultWidths, JSON_UNESCAPED_UNICODE) ?>;
const defaultLines = <?= json_encode($defaultLines, JSON_UNESCAPED_UNICODE) ?>;
const sample = <?= json_encode($sampleTexts, JSON_UNESCAPED_UNICODE) ?>;
const order = <?= json_encode($order) ?>;
const PRINTER_ID = <?= (int) $printerId ?>;
const BASE = '<?= rtrim(base_url(), '/') ?>/';
const REF_URL = <?= json_encode($refUrl) ?>;
const REF_KIND = <?= json_encode($refKind) ?>;

let initial = JSON.stringify(state);
let selected = null;

const canvas = document.getElementById('kalCanvas');
const dirtyBadge = document.getElementById('kalDirty');
const alertBox = document.getElementById('kalAlert');

function showAlert(msg, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
        + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
function markDirty() {
    dirtyBadge.classList.toggle('d-none', JSON.stringify(state) === initial);
}
// Ukuran font cetak 10,5pt pada skala kanvas (1mm = 4px).
const FONT_PX = 10.5 * (25.4 / 72) * SCALE;
const measureCtx = document.createElement('canvas').getContext('2d');
measureCtx.font = FONT_PX + 'px Arial, Helvetica, sans-serif';
function textWidthMm(key) {
    const t = sample[key] || '';
    if (t === '') return null;
    return measureCtx.measureText(t).width / SCALE;
}
/** Lebar kotak: ikut panjang teks, dibatasi lebar default (atau override). */
function widthOf(key) {
    const ov = state[key]?.max_width_mm;
    if (ov !== null && ov !== undefined && ov !== '' && !isNaN(parseFloat(ov))) return parseFloat(ov);
    const def = parseFloat(widths[key] ?? defaultWidths[key] ?? 60);
    const tw = textWidthMm(key);
    if (tw === null) return def;
    return Math.max(5, Math.min(def, Math.round((tw + 1) * 10) / 10));
}
function defaultWidthOf(key) {
    return parseFloat(defaultWidths[key] ?? widths[key] ?? 60);
}
function linesOf(key) {
    const ov = state[key]?.max_lines;
    if (ov !== null && ov !== undefined && ov !== '' && !isNaN(parseInt(ov, 10))) return Math.max(1, parseInt(ov, 10));
    return Math.max(1, parseInt(defaultLines[key] ?? 1, 10));
}

const boxes = {};
order.forEach(key => {
    if (!state[key]) state[key] = { x_mm: 10, y_mm: 10, max_width_mm: null, max_lines: null };
    const el = document.createElement('div');
    el.className = 'kal-box';
    el.dataset.key = key;
    el.innerHTML = '<span class="kal-badge"></span><span class="kal-txt"></span>'
        + '<span class="kal-resize" title="Geser untuk atur lebar kotak"></span>';
    canvas.appendChild(el);
    boxes[key] = el;
    el.addEventListener('pointerdown', e => onBoxDown(e, key));
    el.querySelector('.kal-resize').addEventListener('pointerdown', e => onResizeDown(e, key));
});

function layout() {
    order.forEach(key => {
        const el = boxes[key], s = state[key];
        const lines = linesOf(key);
        el.style.left = (s.x_mm * SCALE) + 'px';
        el.style.top = (s.y_mm * SCALE) + 'px';
        el.style.width = (widthOf(key) * SCALE) + 'px';
        el.style.height = (5 * lines * SCALE) + 'px';
        el.classList.toggle('one-line', lines === 1);
        el.classList.toggle('multi', lines > 1);
        el.dataset.align = aligns[key] || 'L';

        const txt = sample[key] || '';
        const txtEl = el.querySelector('.kal-txt');
        txtEl.textContent = txt === '' ? '(kosong — tidak dicetak)' : txt;
        txtEl.style.color = txt === '' ? '#94a3b8' : '#0f172a';
        txtEl.style.fontStyle = txt === '' ? 'italic' : 'normal';

        el.querySelector('.kal-badge').textContent =
            key + ' — x: ' + Number(s.x_mm).toFixed(1) + ', y: ' + Number(s.y_mm).toFixed(1)
            + ', w: ' + widthOf(key).toFixed(1) + 'mm, ' + lines + ' baris';
        el.classList.toggle('selected', selected === key);
    });
    renderList();
    syncPanel();
    markDirty();
}

function snap(v) {
    return document.getElementById('kalSnap').checked ? Math.round(v) : Math.round(v * 2) / 2;
}

// --- drag ---
function onBoxDown(e, key) {
    if (e.target.classList.contains('kal-resize')) return;
    e.preventDefault();
    select(key);
    const el = boxes[key];
    el.setPointerCapture(e.pointerId);
    const startX = e.clientX, startY = e.clientY, ox = Number(state[key].x_mm), oy = Number(state[key].y_mm);
    const move = ev => {
        state[key].x_mm = snap(ox + (ev.clientX - startX) / SCALE);
        state[key].y_mm = snap(oy + (ev.clientY - startY) / SCALE);
        layout();
    };
    const up = () => {
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerup', up);
        el.removeEventListener('pointercancel', up);
    };
    el.addEventListener('pointermove', move);
    el.addEventListener('pointerup', up);
    el.addEventListener('pointercancel', up);
}

// --- resize lebar ---
function onResizeDown(e, key) {
    e.preventDefault(); e.stopPropagation();
    select(key);
    const startX = e.clientX, startW = widthOf(key);
    const h = e.target;
    try { h.setPointerCapture(e.pointerId); } catch (err) {}
    const move = ev => {
        let w = startW + (ev.clientX - startX) / SCALE;
        w = Math.min(210, Math.max(5, snap(w)));
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

function select(key) {
    selected = key;
    layout();
    if (key) document.getElementById('kalSelName').textContent = (labels[key] || key)
        .replace(/^([IVX]+) - /, 'Bagian $1 — ');
}
function syncPanel() {
    const form = document.getElementById('kalSelForm');
    if (!selected || !state[selected]) { form.classList.add('d-none'); return; }
    form.classList.remove('d-none');
    const inX = document.getElementById('kalInX'), inY = document.getElementById('kalInY');
    if (document.activeElement !== inX) inX.value = state[selected].x_mm;
    if (document.activeElement !== inY) inY.value = state[selected].y_mm;
    const inW = document.getElementById('kalInW');
    if (document.activeElement !== inW) {
        const ov = state[selected].max_width_mm;
        inW.value = (ov === null || ov === undefined || ov === '') ? '' : ov;
        inW.placeholder = 'default ' + defaultWidthOf(selected).toFixed(1) + 'mm';
    }
    document.getElementById('kalDefWInfo').textContent =
        'Lebar kotak: ' + widthOf(selected).toFixed(1) + 'mm (default cetak ' + defaultWidthOf(selected).toFixed(1) + 'mm). Kosongkan isian untuk mengikuti panjang teks.';
    const inL = document.getElementById('kalInLines');
    if (document.activeElement !== inL) {
        const ovL = state[selected].max_lines;
        inL.value = (ovL === null || ovL === undefined || ovL === '') ? '' : ovL;
        inL.placeholder = 'default ' + (defaultLines[selected] ?? 1);
    }
}
document.getElementById('kalInX').addEventListener('input', e => {
    if (!selected) return;
    const v = parseFloat(e.target.value);
    if (!isNaN(v)) { state[selected].x_mm = v; layout(); }
});
document.getElementById('kalInY').addEventListener('input', e => {
    if (!selected) return;
    const v = parseFloat(e.target.value);
    if (!isNaN(v)) { state[selected].y_mm = v; layout(); }
});
document.getElementById('kalInW').addEventListener('input', e => {
    if (!selected) return;
    const raw = e.target.value;
    if (raw === '' || raw === null) { state[selected].max_width_mm = null; layout(); return; }
    const v = parseFloat(raw);
    if (!isNaN(v)) { state[selected].max_width_mm = Math.min(210, Math.max(5, v)); layout(); }
});
document.getElementById('kalResetW').addEventListener('click', () => {
    if (!selected) return;
    state[selected].max_width_mm = null;
    layout();
});
document.getElementById('kalInLines').addEventListener('input', e => {
    if (!selected) return;
    const raw = e.target.value;
    if (raw === '' || raw === null) { state[selected].max_lines = null; layout(); return; }
    const v = parseInt(raw, 10);
    if (!isNaN(v)) { state[selected].max_lines = Math.max(1, Math.min(4, v)); layout(); }
});
document.getElementById('kalResetLines').addEventListener('click', () => {
    if (!selected) return;
    state[selected].max_lines = null;
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
        a.querySelector('span').textContent = (labels[key] || key).replace(/^([IVX]+) - /, '') + ' ⏎'.repeat(linesOf(key) > 1 ? 1 : 0);
        a.querySelector('small').textContent = Number(s.x_mm).toFixed(1) + ', ' + Number(s.y_mm).toFixed(1) + ' · ' + widthOf(key).toFixed(0) + 'mm';
        a.title = (labels[key] || key) + ' — ' + widthOf(key).toFixed(1) + 'mm, ' + linesOf(key) + ' baris';
        a.addEventListener('click', () => select(key));
        list.appendChild(a);
    });
}

// --- background: opacity, proporsi, PDF ---
const bgDiv = document.getElementById('kalBg');
const pdfCanvas = document.getElementById('kalPdfBg');
document.getElementById('kalOpacity').addEventListener('input', function() {
    const op = (parseInt(this.value, 10) || 50) / 100;
    bgDiv.style.opacity = op;
    pdfCanvas.style.opacity = op;
});
function placeLayer(el, wMm, hMm) {
    el.style.left = (OFF_X * SCALE) + 'px';
    el.style.top = (OFF_Y * SCALE) + 'px';
    el.style.width = (wMm * SCALE) + 'px';
    el.style.height = (hMm * SCALE) + 'px';
}
function checkAspect(w, h, label) {
    const box = document.getElementById('kalAspectWarn');
    if (!w || !h) { box.innerHTML = ''; return; }
    const fileRatio = w / h, paperRatio = CONTENT_W / CONTENT_H;
    const dev = Math.abs(fileRatio - paperRatio) / paperRatio;
    if (dev > 0.02) {
        box.innerHTML = '<div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:.8rem;">'
            + '<i class="bi bi-exclamation-triangle me-1"></i>Proporsi ' + label + ' (' + fileRatio.toFixed(3) + ') menyimpang '
            + (dev * 100).toFixed(1) + '% dari blanko (' + paperRatio.toFixed(3) + '). Pangkas file tepat di tepi blanko lalu unggah ulang.</div>';
    } else {
        box.innerHTML = '';
    }
}
function showImageBackground(url) {
    pdfCanvas.classList.add('d-none');
    bgDiv.style.backgroundImage = "url('" + url + "')";
    placeLayer(bgDiv, CONTENT_W, CONTENT_H);
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
    loadPdfJs().then(lib => lib.getDocument(url).promise).then(pdf => pdf.getPage(1)).then(page => {
        const vp0 = page.getViewport({ scale: 1 });
        checkAspect(vp0.width, vp0.height, 'halaman 1 PDF');
        const vp = page.getViewport({ scale: 2 });
        const off = document.createElement('canvas');
        off.width = vp.width; off.height = vp.height;
        return page.render({ canvasContext: off.getContext('2d'), viewport: vp }).promise.then(() => {
            pdfCanvas.width = Math.round(CONTENT_W * SCALE);
            pdfCanvas.height = Math.round(CONTENT_H * SCALE);
            placeLayer(pdfCanvas, CONTENT_W, CONTENT_H);
            pdfCanvas.getContext('2d').drawImage(off, 0, 0, off.width, off.height, 0, 0, pdfCanvas.width, pdfCanvas.height);
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
    fetch(BASE + 'spj/sppd2/kalibrasi/upload', { method: 'POST', body: fd })
        .then(r => r.json().then(j => ({ status: r.status, body: j })))
        .then(({ status, body }) => {
            if (!body.ok) throw new Error(body.message || ('HTTP ' + status));
            if (body.kind === 'pdf') { renderPdfBackground(body.url); }
            else { showImageBackground(body.url); }
            showAlert('Referensi terpasang sebagai background kanvas.', 'success');
        })
        .catch(err => showAlert('Upload gagal: ' + err.message, 'danger'))
        .finally(() => { document.getElementById('kalUpload').value = ''; });
});

// --- peringatan perubahan belum disimpan ---
function isDirty() { return JSON.stringify(state) !== initial; }
window.addEventListener('beforeunload', function(e) {
    if (isDirty()) {
        e.preventDefault();
        e.returnValue = 'Ada perubahan posisi kalibrasi yang belum disimpan!';
        return e.returnValue;
    }
});

// --- ganti printer ---
const printerSelect = document.getElementById('kalPrinter');
if (printerSelect) {
    let prevPrinter = printerSelect.value;
    printerSelect.addEventListener('change', function() {
        if (isDirty()) {
            if (!confirm('Ada perubahan posisi yang belum disimpan. Yakin ganti printer tanpa menyimpan?')) {
                this.value = prevPrinter;
                return;
            }
        }
        prevPrinter = this.value;
        window.location.href = BASE + 'spj/sppd2/kalibrasi?printer=' + encodeURIComponent(this.value);
    });
}

// --- simpan ---
const btnSimpan = document.getElementById('kalSimpan');
btnSimpan.addEventListener('click', () => {
    if (PRINTER_ID <= 0) return;
    btnSimpan.disabled = true;
    const oldHtml = btnSimpan.innerHTML;
    btnSimpan.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';

    const items = order.map(key => {
        const w = state[key].max_width_mm;
        const l = state[key].max_lines;
        return {
            elemen_key: key,
            label: labels[key] || key,
            x_mm: state[key].x_mm,
            y_mm: state[key].y_mm,
            max_width_mm: (w === null || w === undefined || w === '') ? null : parseFloat(w),
            max_lines: (l === null || l === undefined || l === '') ? null : parseInt(l, 10),
        };
    });
    fetch(BASE + 'spj/sppd2/kalibrasi/simpan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ printer_id: PRINTER_ID, items })
    })
        .then(r => r.json().then(j => ({ status: r.status, body: j })))
        .then(({ status, body }) => {
            if (!body.ok) throw new Error(body.message || ('HTTP ' + status));
            btnSimpan.className = 'btn btn-sm btn-success text-white shadow-sm';
            btnSimpan.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Tersimpan!';
            showAlert('Posisi kalibrasi berhasil disimpan (' + body.saved + ' elemen) ke printer ini.', 'success');
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

// --- cetak uji (posisi kanvas, belum tentu tersimpan) ---
const btnUji = document.getElementById('kalUji');
btnUji.addEventListener('click', () => {
    btnUji.disabled = true;
    const oldHtml = btnUji.innerHTML;
    btnUji.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';

    const positions = {};
    order.forEach(key => {
        const w = state[key].max_width_mm;
        const l = state[key].max_lines;
        positions[key] = {
            x_mm: state[key].x_mm,
            y_mm: state[key].y_mm,
            max_width_mm: (w === null || w === undefined || w === '') ? null : parseFloat(w),
            max_lines: (l === null || l === undefined || l === '') ? null : parseInt(l, 10),
        };
    });
    fetch(BASE + 'spj/sppd2/kalibrasi/uji', {
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
            btnUji.innerHTML = oldHtml;
        });
});

layout();
})();
</script>
