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
$pustaka = $data['pustaka'] ?? [];
$blankoAktifId = (int) ($data['blankoAktifId'] ?? 0);
$content = $data['content'] ?? [];
$offset = $data['offset'] ?? ['x' => 0, 'y' => 0];
$contentW = (float) ($content['width_mm'] ?? 197.2);
$contentH = (float) ($content['height_mm'] ?? 283.3);
$offX = (float) ($offset['x'] ?? 0);
$offY = (float) ($offset['y'] ?? 0);
$fontFamily = (string) ($data['fontFamily'] ?? 'Helvetica');
$fontSize = (float) ($data['fontSize'] ?? 10.5);
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
.kal-box { position:absolute; outline:1.5px solid #2563eb; background:rgba(37,99,235,.07); border-radius:3px; padding:0; cursor:move; user-select:none; touch-action:none; box-sizing:border-box; font-family:Arial,Helvetica,sans-serif; overflow:hidden; }
.kal-box .kal-txt { display:block; font-size: var(--kal-font-px, 14.8px); font-family: var(--kal-font-family, Arial, Helvetica, sans-serif); color:#0f172a; height:100%; box-sizing:border-box; font-kerning:none; font-variant-ligatures:none; }
.kal-box.one-line .kal-txt { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.kal-box.multi .kal-txt { white-space:normal; word-break:break-word; text-align:center; }
.kal-box[data-align="C"] .kal-txt { text-align:center; }
.kal-box .kal-badge { position:absolute; top:-19px; left:0; font-size:10px; background:#0f172a; color:#fff; border-radius:4px; padding:1px 6px; white-space:nowrap; display:none; z-index:5; }
.kal-box.selected { outline-color:#dc2626; background:rgba(220,38,38,.08); box-shadow:0 0 0 2px rgba(220,38,38,.25); z-index:4; }
.kal-box:hover .kal-badge, .kal-box.dragging .kal-badge, .kal-box.nudging .kal-badge { display:block; }
.kal-box.selected:not(.dragging):not(.nudging) .kal-badge { display:none; }
.kal-box.nudging .kal-badge { background:#dc2626; }
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
        <button type="button" class="btn btn-sm btn-outline-secondary mb-0" data-bs-toggle="modal" data-bs-target="#modalPustakaBlanko">
            <i class="bi bi-collection me-1"></i>Pustaka Blanko
            <span class="badge bg-secondary ms-1"><?= count($pustaka) ?></span>
        </button>
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
                    <div class="kal-bg" id="kalBg"<?= ($refUrl && $refKind !== 'pdf') ? " style=\"left:0;top:0;width:" . (210 * 4) . "px;height:" . (297 * 4) . "px;background-image:url('" . htmlspecialchars($refUrl) . "');\"" : '' ?>></div>
                    <canvas class="kal-pdfbg d-none" id="kalPdfBg"></canvas>
                </div>
            </div>
            <div class="form-text mt-1" style="font-size:.78rem;">
                Scan <strong>A4 penuh</strong> ditempatkan 1:1 di seluruh kertas (paling aman); gambar yang sudah
                dipangkas ke area isi form ditempatkan di area isi <strong><?= number_format($offX, 1, ',', '.') ?> / <?= number_format($offY, 1, ',', '.') ?> mm</strong>
                (<?= number_format($contentW, 1, ',', '.') ?> : <?= number_format($contentH, 1, ',', '.') ?> mm).
                Pangkas tepat di tepi kertas dan jangan miring.<br>
                Teks pratinjau memakai font &amp; ukuran cetak (<?= htmlspecialchars($fontFamily) ?>
                <?= number_format($fontSize, 1, ',', '.') ?>&thinsp;pt); di layar bisa tampak lebih besar bila
                skala tampilan Windows/browser bukan 100% — posisi dan ukuran cetak tidak ikut berubah.
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
                    <div class="form-text mt-2" style="font-size:.75rem;">
                        Geser presisi: pilih kotak lalu pakai tombol panah (<kbd>&larr;</kbd><kbd>&rarr;</kbd><kbd>&uarr;</kbd><kbd>&darr;</kbd>)
                        = 1&thinsp;mm, <kbd>Shift</kbd>+panah = 0,1&thinsp;mm, <kbd>Alt</kbd>+panah = 10&thinsp;mm.
                        <kbd>Esc</kbd> atau klik area kosong untuk batal pilih.
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
const aligns = <?= json_encode($data['aligns'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
const styles = <?= json_encode($data['styles'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
const heights = <?= json_encode($data['heights'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
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
/**
 * fetch + parse JSON dengan pesan galat yang berguna: server bisa membalas
 * halaman HTML (login/404/fatal) sehingga r.json() gagal dengan pesan
 * "Unexpected token '<'" yang tidak menjelaskan apa pun.
 */
function fetchJson(url, options, label) {
    return fetch(url, options).then(r => r.text().then(text => {
        let body = null;
        try {
            body = JSON.parse(text);
        } catch (e) {
            console.error('[' + label + '] respons bukan JSON (HTTP ' + r.status + ')', text.slice(0, 500));
            const html = /^\s*</.test(text);
            if (r.status === 401 || (html && /name="username"|Masuk ke akun/i.test(text))) {
                throw new Error('Sesi login berakhir. Login ulang lalu muat ulang halaman ini — posisi yang belum disimpan akan hilang.');
            }
            if (html) {
                throw new Error('Server membalas halaman HTML (HTTP ' + r.status + '), bukan data. Cek log server; kemungkinan route/endpoint belum ada di server atau terjadi fatal error.');
            }
            throw new Error('Balasan server tidak dapat dibaca (HTTP ' + r.status + ').');
        }
        return { status: r.status, body };
    }));
}
function markDirty() {
    dirtyBadge.classList.toggle('d-none', JSON.stringify(state) === initial);
}
// Ukuran font cetak (config) dipetakan ke skala kanvas (1mm = 4px).
const FONT_FAMILY = <?= json_encode($fontFamily, JSON_UNESCAPED_UNICODE) ?>;
const FONT_PT = <?= json_encode($fontSize) ?>;
const FONT_PX = FONT_PT * (25.4 / 72) * SCALE;
canvas.style.setProperty('--kal-font-px', FONT_PX + 'px');
canvas.style.setProperty('--kal-font-family', FONT_FAMILY + ', Arial, Helvetica, sans-serif');
const measureCtx = document.createElement('canvas').getContext('2d');
measureCtx.font = FONT_PX + 'px ' + FONT_FAMILY + ', Arial, Helvetica, sans-serif';
// FPDF tidak memakai kerning — samakan pengukuran lebar teks.
if ('fontKerning' in measureCtx) measureCtx.fontKerning = 'none';
/** Lebar kotak = lebar cetak sebenarnya (override kalibrasi bila diatur). */
function widthOf(key) {
    const ov = state[key]?.max_width_mm;
    if (ov !== null && ov !== undefined && ov !== '' && !isNaN(parseFloat(ov))) return parseFloat(ov);
    return parseFloat(widths[key] ?? defaultWidths[key] ?? 60);
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
        const lineH = parseFloat(heights[key] ?? 5) || 5;
        el.style.left = (s.x_mm * SCALE) + 'px';
        el.style.top = (s.y_mm * SCALE) + 'px';
        el.style.width = (widthOf(key) * SCALE) + 'px';
        el.style.height = (lineH * lines * SCALE) + 'px';
        el.classList.toggle('one-line', lines === 1);
        el.classList.toggle('multi', lines > 1);
        el.dataset.align = aligns[key] || 'L';

        const txt = sample[key] || '';
        const txtEl = el.querySelector('.kal-txt');
        txtEl.textContent = txt === '' ? '(kosong — tidak dicetak)' : txt;
        txtEl.style.color = txt === '' ? '#94a3b8' : '#0f172a';
        // Gaya cetak: FPDF Cell(h=lineH) menaruh teks di tengah kotak, gaya
        // U/B/I mengikuti config elemen agar pratinjau = hasil cetak.
        const st = String(styles[key] || '');
        txtEl.style.fontStyle = (/I/.test(st) || txt === '') ? 'italic' : 'normal';
        txtEl.style.fontWeight = /B/.test(st) ? '700' : '400';
        txtEl.style.textDecoration = /U/.test(st) ? 'underline' : 'none';
        txtEl.style.lineHeight = (lineH * SCALE) + 'px';
        // Generator memotong/wrap pada lebar w-1 mm dan mulai teks di x+1mm
        // untuk rata kiri (Cell cMargin); rata tengah di x+w/2.
        txtEl.style.width = ((widthOf(key) - 1) * SCALE) + 'px';
        txtEl.style.marginLeft = ((aligns[key] === 'C' ? 0.5 : 1) * SCALE) + 'px';
        // Cetak mengecilkan font (min. 7 pt) agar teks satu baris tidak
        // terpotong; pratinjau harus mengikuti agar WYSIWYG.
        let fontPx = FONT_PX;
        if (lines === 1 && txt !== '') {
            const textW = measureCtx.measureText(txt).width;
            const boxW = (widthOf(key) - 1) * SCALE;
            if (textW > boxW) {
                fontPx = Math.max(boxW / textW * FONT_PX, 7 * (25.4 / 72) * SCALE);
            }
        }
        txtEl.style.fontSize = fontPx + 'px';

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
    // Lepas fokus input agar tombol panah menggerakkan kotak, bukan kursor input.
    const ae = document.activeElement;
    if (ae && /^(INPUT|TEXTAREA|SELECT)$/.test(ae.tagName)) ae.blur();
    select(key);
    const el = boxes[key];
    el.classList.add('dragging');
    try { el.setPointerCapture(e.pointerId); } catch (err) {}
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
        el.classList.remove('dragging');
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
    document.getElementById('kalSelName').textContent = key
        ? (labels[key] || key).replace(/^([IVX]+) - /, 'Bagian $1 — ')
        : 'Klik salah satu kotak di kanvas.';
}

// Klik area kosong kanvas / tekan Esc = batal pilih (keterangan ikut hilang).
canvas.addEventListener('pointerdown', e => {
    if (e.target === canvas) select(null);
});
let nudgeEl = null, nudgeTimer = null;
function flashBadge(key) {
    if (nudgeEl) nudgeEl.classList.remove('nudging');
    clearTimeout(nudgeTimer);
    nudgeEl = boxes[key] || null;
    if (!nudgeEl) return;
    nudgeEl.classList.add('nudging');
    nudgeTimer = setTimeout(() => nudgeEl && nudgeEl.classList.remove('nudging'), 900);
}
const NUDGE = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (selected) select(null);
        return;
    }
    const dir = NUDGE[e.key];
    if (!dir || !selected || !state[selected]) return;
    const t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    if (e.ctrlKey || e.metaKey) return;
    e.preventDefault();
    const step = e.altKey ? 10 : (e.shiftKey ? 0.1 : 1);
    const s = state[selected];
    const nx = Math.round(Math.min(PAGE_W, Math.max(0, Number(s.x_mm) + dir[0] * step)) * 100) / 100;
    const ny = Math.round(Math.min(PAGE_H, Math.max(0, Number(s.y_mm) + dir[1] * step)) * 100) / 100;
    if (nx === Number(s.x_mm) && ny === Number(s.y_mm)) return;
    s.x_mm = nx;
    s.y_mm = ny;
    layout();
    flashBadge(selected);
});
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
function placeLayer(el, p) {
    el.style.left = (p.x * SCALE) + 'px';
    el.style.top = (p.y * SCALE) + 'px';
    el.style.width = (p.w * SCALE) + 'px';
    el.style.height = (p.h * SCALE) + 'px';
}
/**
 * Pilih pemetaan referensi yang paling cocok dengan file:
 * - scan A4 penuh → 1:1 seluruh kertas (0,0,210×297 mm);
 * - gambar yang sudah dipangkas ke area isi form → area isi templat.
 * Tanpa ini, scan A4 penuh mengecil ~6% sehingga kotak tidak mungkin pas.
 */
function referencePlacement(w, h) {
    if (!w || !h) return null;
    const fileRatio = w / h;
    const a4Ratio = PAGE_W / PAGE_H, tplRatio = CONTENT_W / CONTENT_H;
    const devA4 = Math.abs(fileRatio - a4Ratio) / a4Ratio;
    const devTpl = Math.abs(fileRatio - tplRatio) / tplRatio;
    return devA4 <= devTpl
        ? { x: 0, y: 0, w: PAGE_W, h: PAGE_H, mode: 'A4 penuh 1:1', ratio: fileRatio, target: a4Ratio, dev: devA4 }
        : { x: OFF_X, y: OFF_Y, w: CONTENT_W, h: CONTENT_H, mode: 'area isi form', ratio: fileRatio, target: tplRatio, dev: devTpl };
}
function reportReference(w, h, label) {
    const p = referencePlacement(w, h);
    const box = document.getElementById('kalAspectWarn');
    if (!p) { box.innerHTML = ''; return null; }
    if (p.dev > 0.02) {
        box.innerHTML = '<div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:.8rem;">'
            + '<i class="bi bi-exclamation-triangle me-1"></i>Proporsi ' + label + ' (' + p.ratio.toFixed(3) + ') menyimpang '
            + (p.dev * 100).toFixed(1) + '% dari ' + p.mode + ' (' + p.target.toFixed(3) + '). Pangkas file tepat di tepi kertas lalu unggah ulang.</div>';
    } else {
        box.innerHTML = '';
    }
    return p;
}
function showImageBackground(url) {
    pdfCanvas.classList.add('d-none');
    const img = new Image();
    img.onload = () => {
        const p = reportReference(img.naturalWidth, img.naturalHeight, 'gambar');
        if (p) placeLayer(bgDiv, p);
        bgDiv.style.backgroundImage = "url('" + url + "')";
        document.getElementById('kalRefStatus').textContent = 'Background referensi: gambar, dipetakan ' + (p ? p.mode : '') + '.';
    };
    img.onerror = () => showAlert('Gambar referensi gagal dimuat.', 'danger');
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
        const p = reportReference(vp0.width, vp0.height, 'halaman 1 PDF');
        const targetW = Math.round((p ? p.w : CONTENT_W) * SCALE);
        const targetH = Math.round((p ? p.h : CONTENT_H) * SCALE);
        const vp = page.getViewport({ scale: 2 });
        const off = document.createElement('canvas');
        off.width = vp.width; off.height = vp.height;
        return page.render({ canvasContext: off.getContext('2d'), viewport: vp }).promise.then(() => {
            pdfCanvas.width = targetW;
            pdfCanvas.height = targetH;
            if (p) placeLayer(pdfCanvas, p);
            pdfCanvas.getContext('2d').drawImage(off, 0, 0, off.width, off.height, 0, 0, targetW, targetH);
            bgDiv.style.backgroundImage = 'none';
            pdfCanvas.classList.remove('d-none');
            document.getElementById('kalRefStatus').textContent =
                'Background referensi: PDF hal. 1, dipetakan ' + (p ? p.mode : '') + '.';
        });
    }).catch(err => showAlert('Gagal menampilkan PDF: ' + err.message, 'danger'));
}
if (REF_KIND === 'pdf' && REF_URL) {
    renderPdfBackground(REF_URL);
} else if (REF_KIND === 'image' && REF_URL) {
    showImageBackground(REF_URL);
}

// --- upload ---
document.getElementById('kalUpload').addEventListener('change', function() {
    if (!this.files.length) return;
    const fd = new FormData();
    fd.append('referensi', this.files[0]);
    fetchJson(BASE + 'spj/sppd2/kalibrasi/upload', { method: 'POST', body: fd }, 'upload')
        .then(({ status, body }) => {
            if (!body.ok) throw new Error(body.message || ('HTTP ' + status));
            if (!isDirty()) {
                window.location.reload();
                return;
            }
            // Ada posisi belum disimpan: jangan buang dengan reload.
            if (body.kind === 'pdf') { renderPdfBackground(body.url); }
            else { showImageBackground(body.url); }
            showAlert('Blanko tersimpan di pustaka dan aktif. Simpan posisi lalu muat ulang halaman untuk melihat daftar pustaka.', 'success');
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
    fetchJson(BASE + 'spj/sppd2/kalibrasi/simpan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ printer_id: PRINTER_ID, items })
    }, 'simpan')
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
            if (!r.ok || !(r.headers.get('content-type') || '').includes('pdf')) {
                return r.text().then(t => {
                    throw new Error(r.status === 401
                        ? 'Sesi login berakhir. Login ulang lalu coba lagi.'
                        : 'Server tidak mengirim PDF (HTTP ' + r.status + ').' + (/^\s*</.test(t) ? '' : ' ' + t.slice(0, 120)));
                });
            }
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

<div class="modal fade" id="modalPustakaBlanko" tabindex="-1" aria-labelledby="modalPustakaBlankoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6 fw-bold" id="modalPustakaBlankoLabel">
                    <i class="bi bi-collection me-1"></i>Pustaka Blanko Lembar 2 SPPD
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0">
                <?php if (empty($pustaka)): ?>
                    <div class="p-4 text-center text-muted" style="font-size:.9rem;">
                        Belum ada blanko di pustaka. Upload contoh blanko untuk mulai menyimpan.
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
                                    <form method="POST" action="<?= base_url('spj/sppd2/kalibrasi/blanko/aktifkan/' . $bId) ?>" class="m-0">
                                        <input type="hidden" name="printer" value="<?= $printerId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-check2-circle me-1"></i>Gunakan
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="<?= base_url('spj/sppd2/kalibrasi/blanko/hapus/' . $bId) ?>" class="m-0"
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
