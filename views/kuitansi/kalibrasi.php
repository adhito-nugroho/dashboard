<?php
/**
 * View: Editor Visual Kalibrasi Cetak Kuitansi (admin saja).
 * $kalibrasiData: positions [key=>[x_mm,y_mm,max_width_mm?]], labels, widths, refUrl, flash, flashType.
 */
$data = $kalibrasiData ?? [];
$positions = $data['positions'] ?? [];
$labels = $data['labels'] ?? [];
$widths = $data['widths'] ?? [];
$refUrl = $data['refUrl'] ?? null;
$flash = $data['flash'] ?? null;
$flashType = $data['flashType'] ?? 'info';

$dummy = [
    'no_bku' => 'BKU/001', 'no_program' => 'PRG.01', 'no_kegiatan' => 'KEG.02',
    'terima_dari' => '(kosong — contoh: Bendahara Pengeluaran)',
    'jumlah_terbilang' => 'Tiga Ratus Ribu Rupiah',
    'uraian' => 'Perjalanan Dinas dalam rangka koordinasi kehutanan ... (contoh, wrap otomatis)',
    'terbilang_rp' => '300.000', 'tempat_tanggal' => 'Bojonegoro, 27 Agustus 2026',
    'ttd_kpa_nama' => 'ENDANG HANDAYANI, S.P., M.Si.', 'ttd_kpa_nip' => 'NIP. 19760328 200003 2 003',
    'ttd_bendahara_nama' => 'ADHITO NUGROHO, S.Kom.', 'ttd_bendahara_nip' => 'NIP. 19840214 201001 1 011',
    'ttd_penerima_nama' => 'Budi Santoso',
];
$order = ['no_bku','no_program','no_kegiatan','terima_dari','jumlah_terbilang','uraian','terbilang_rp','tempat_tanggal','ttd_kpa_nama','ttd_kpa_nip','ttd_bendahara_nama','ttd_bendahara_nip','ttd_penerima_nama'];
?>
<style>
.kal-canvas-scroll { overflow:auto; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; max-width:100%; }
.kal-canvas { position:relative; width:860px; height:660px; background:#fff; flex:0 0 auto; }
.kal-canvas .kal-bg { position:absolute; inset:0; background-size:100% 100%; background-repeat:no-repeat; opacity:.5; pointer-events:none; }
.kal-box { position:absolute; border:1.5px solid #2563eb; background:rgba(37,99,235,.07); border-radius:4px; padding:2px 4px; cursor:move; user-select:none; touch-action:none; box-sizing:border-box; min-height:22px; }
.kal-box .kal-tag { display:block; font-size:10px; font-weight:700; color:#1d4ed8; line-height:1.2; }
.kal-box .kal-txt { display:block; font-size:10px; color:#0f172a; line-height:1.25; white-space:pre-wrap; word-break:break-word; }
.kal-box .kal-badge { position:absolute; top:-20px; left:0; font-size:10px; background:#0f172a; color:#fff; border-radius:4px; padding:1px 6px; white-space:nowrap; display:none; z-index:5; }
.kal-box.selected { border-color:#dc2626; background:rgba(220,38,38,.08); box-shadow:0 0 0 2px rgba(220,38,38,.25); }
.kal-box.selected .kal-badge { display:block; }
.kal-box.selected .kal-tag { color:#b91c1c; }
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
        Geser tiap kotak ke posisinya. Tabel lama <code>kalibrasi_kuitansi</code> tidak dihapus, hanya tidak dipakai lagi.
    </p>

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
            <label class="form-check-label" for="kalSnap" style="font-size:.85rem;">Snap 1mm</label>
        </div>
        <label class="btn btn-sm btn-outline-secondary mb-0" for="kalUpload">
            <i class="bi bi-image me-1"></i>Upload Contoh Kuitansi
        </label>
        <input type="file" id="kalUpload" accept=".jpg,.jpeg,.png" class="d-none">
        <span class="text-muted" style="font-size:.8rem;"><?= $refUrl ? 'Background referensi terpasang.' : 'Belum ada gambar referensi.' ?> (JPG/PNG, maks 5MB)</span>
        <div class="ms-auto d-flex gap-2">
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
                    <?php if ($refUrl): ?>
                        <div class="kal-bg" id="kalBg" style="background-image:url('<?= htmlspecialchars($refUrl) ?>');"></div>
                    <?php else: ?>
                        <div class="kal-bg" id="kalBg"></div>
                    <?php endif; ?>
                </div>
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
                        <div id="kalWrapW">
                            <label class="form-label mb-1" style="font-size:.8rem;">Lebar maks (mm) — khusus uraian</label>
                            <input type="number" class="form-control form-control-sm" id="kalInW" step="0.5" min="10" max="215">
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2" style="font-size:.85rem;">Semua elemen</h6>
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
const dummy = <?= json_encode($dummy, JSON_UNESCAPED_UNICODE) ?>;
const order = <?= json_encode($order) ?>;
let initial = JSON.stringify(state);
let selected = null;

const canvas = document.getElementById('kalCanvas');
const dirtyBadge = document.getElementById('kalDirty');
const alertBox = document.getElementById('kalAlert');
const BASE = '<?= rtrim(base_url(), '/') ?>/';

function showAlert(msg, type) {
    alertBox.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
        + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
function markDirty() {
    dirtyBadge.classList.toggle('d-none', JSON.stringify(state) === initial);
}
function widthOf(key) {
    if (key === 'uraian') return parseFloat(state.uraian?.max_width_mm ?? widths.uraian ?? 185);
    return parseFloat(widths[key] ?? 60);
}

// --- render kotak ---
const boxes = {};
order.forEach(key => {
    if (!state[key]) state[key] = { x_mm: 10, y_mm: 10 };
    const el = document.createElement('div');
    el.className = 'kal-box';
    el.dataset.key = key;
    el.innerHTML = '<span class="kal-badge"></span>'
        + '<span class="kal-tag"></span>'
        + '<span class="kal-txt"></span>'
        + (key === 'uraian' ? '<span class="kal-resize" title="Geser untuk atur lebar"></span>' : '');
    canvas.appendChild(el);
    boxes[key] = el;
    el.addEventListener('pointerdown', e => onBoxDown(e, key));
});
function layout() {
    order.forEach(key => {
        const el = boxes[key], s = state[key];
        el.style.left = (s.x_mm * SCALE) + 'px';
        el.style.top = (s.y_mm * SCALE) + 'px';
        el.style.width = (widthOf(key) * SCALE) + 'px';
        el.querySelector('.kal-tag').textContent = labels[key] || key;
        el.querySelector('.kal-txt').textContent = dummy[key] || '';
        el.querySelector('.kal-badge').textContent = 'x: ' + Number(s.x_mm).toFixed(1) + 'mm, y: ' + Number(s.y_mm).toFixed(1) + 'mm';
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

// --- resize lebar uraian ---
document.querySelector('.kal-resize')?.addEventListener('pointerdown', e => {
    e.preventDefault(); e.stopPropagation();
    const startX = e.clientX, startW = widthOf('uraian');
    const h = e.target;
    h.setPointerCapture(e.pointerId);
    const move = ev => {
        let w = startW + (ev.clientX - startX) / SCALE;
        w = Math.min(215, Math.max(10, snap(w)));
        state.uraian.max_width_mm = w;
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
});

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
    document.getElementById('kalInX').value = state[selected].x_mm;
    document.getElementById('kalInY').value = state[selected].y_mm;
    document.getElementById('kalWrapW').style.display = selected === 'uraian' ? '' : 'none';
    if (selected === 'uraian') document.getElementById('kalInW').value = widthOf('uraian');
}
document.getElementById('kalInX').addEventListener('input', e => {
    if (!selected) return;
    state[selected].x_mm = parseFloat(e.target.value || '0');
    layout();
});
document.getElementById('kalInY').addEventListener('input', e => {
    if (!selected) return;
    state[selected].y_mm = parseFloat(e.target.value || '0');
    layout();
});
document.getElementById('kalInW').addEventListener('input', e => {
    state.uraian.max_width_mm = Math.min(215, Math.max(10, parseFloat(e.target.value || '185')));
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
        a.querySelector('span').textContent = labels[key] || key;
        a.querySelector('small').textContent = Number(s.x_mm).toFixed(1) + ', ' + Number(s.y_mm).toFixed(1);
        a.addEventListener('click', () => select(key));
        list.appendChild(a);
    });
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
            document.getElementById('kalBg').style.backgroundImage = "url('" + body.url + "')";
            showAlert('Gambar referensi terpasang sebagai background kanvas.', 'success');
        })
        .catch(err => showAlert('Upload gagal: ' + err.message, 'danger'))
        .finally(() => { document.getElementById('kalUpload').value = ''; });
});

// --- simpan ---
document.getElementById('kalSimpan').addEventListener('click', () => {
    const items = order.map(key => ({
        elemen_key: key,
        label: labels[key] || key,
        x_mm: state[key].x_mm,
        y_mm: state[key].y_mm,
        max_width_mm: key === 'uraian' ? widthOf('uraian') : null
    }));
    fetch(BASE + 'kuitansi/kalibrasi/simpan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items })
    })
        .then(r => r.json().then(j => ({ status: r.status, body: j })))
        .then(({ status, body }) => {
            if (!body.ok) throw new Error(body.message || ('HTTP ' + status));
            showAlert('Tersimpan: ' + body.saved + ' elemen.', 'success');
            initial = JSON.stringify(state);
            markDirty();
        })
        .catch(err => showAlert('Simpan gagal: ' + err.message, 'danger'));
});

// --- cetak uji (state kanvas saat ini, belum tentu tersimpan) ---
document.getElementById('kalUji').addEventListener('click', () => {
    const positions = {};
    order.forEach(key => {
        positions[key] = {
            x_mm: state[key].x_mm,
            y_mm: state[key].y_mm,
            max_width_mm: key === 'uraian' ? widthOf('uraian') : null
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
        .catch(err => showAlert('Cetak uji gagal: ' + err.message, 'danger'));
});

layout();
})();
</script>
