<?php
/**
 * Partial: Pencarian Cepat Klasifikasi Anggaran (shortcut opsional).
 * Dipakai di form transaksi admin (single + batch) dan form seksi.
 *
 * Variabel (semua opsional, ada default):
 *   $qsEndpoint  URL endpoint AJAX ?q=&mode= (wajib diisi pemanggil)
 *   $qsProgram   id select Program            (default 'program_id')
 *   $qsKegiatan  id select Kegiatan           (default 'kegiatan_id')
 *   $qsSub       id select Sub Kegiatan       (default 'sub_kegiatan_id')
 *   $qsRekening  id select Rekening Belanja   (default 'rekening_id'; boleh tidak ada, mis. form batch)
 *   $qsTitle     label input                  (default 'Cari Sub Kegiatan / Rekening')
 *
 * Cara kerja: hasil klik -> set value + dispatch 'change' per level memakai
 * handler cascade YANG SUDAH ADA (tidak diduplikasi), menunggu tiap dropdown
 * terisi (polling), lalu highlight + scroll. Tidak mengubah logic cascade.
 */
$qsEndpoint = $qsEndpoint ?? '';
$qsProgram  = $qsProgram ?? 'program_id';
$qsKegiatan = $qsKegiatan ?? 'kegiatan_id';
$qsSub      = $qsSub ?? 'sub_kegiatan_id';
$qsRekening = $qsRekening ?? 'rekening_id';
$qsTitle    = $qsTitle ?? 'Cari Sub Kegiatan / Rekening';
?>
<style>
.qs-klasifikasi .qs-results { position: relative; z-index: 30; }
.qs-klasifikasi .qs-item { cursor: pointer; }
.qs-klasifikasi .qs-item:hover, .qs-klasifikasi .qs-item.active { background-color: #eef2ff; }
.qs-klasifikasi .qs-crumb { font-size: .75rem; color: #64748b; }
.qs-flash { outline: 2px solid #4f46e5 !important; outline-offset: 2px; box-shadow: 0 0 0 4px rgba(79,70,229,.15) !important; transition: box-shadow .3s ease; }
.qs-mode-btn.active { background-color: #1F3D2B; color: #fff; border-color: #1F3D2B; }
</style>
<div class="qs-klasifikasi mb-3"
     data-endpoint="<?= htmlspecialchars($qsEndpoint) ?>"
     data-program="<?= htmlspecialchars($qsProgram) ?>"
     data-kegiatan="<?= htmlspecialchars($qsKegiatan) ?>"
     data-sub="<?= htmlspecialchars($qsSub) ?>"
     data-rekening="<?= htmlspecialchars($qsRekening) ?>">
    <label class="form-label fw-semibold" style="font-size:.85rem;">
        <i class="bi bi-lightning-charge-fill text-warning me-1"></i><?= htmlspecialchars($qsTitle) ?>
        <span class="badge bg-light text-secondary border fw-normal ms-1" style="font-size:.7rem;">shortcut opsional</span>
    </label>
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
        <input type="text" class="form-control qs-input" placeholder="Ketik min. 3 huruf, mis. nama sub kegiatan..." autocomplete="off">
        <button type="button" class="btn btn-outline-secondary qs-reset" title="Kosongkan pencarian & kembalikan dropdown">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="d-flex gap-1 align-items-center mt-1 flex-wrap">
        <div class="btn-group btn-group-sm" role="group" aria-label="Mode pencarian">
            <button type="button" class="btn btn-outline-secondary qs-mode-btn active" data-mode="sub">Sub Kegiatan</button>
            <button type="button" class="btn btn-outline-secondary qs-mode-btn" data-mode="rekening">Rekening</button>
        </div>
        <small class="text-muted qs-status"></small>
    </div>
    <div class="list-group qs-results d-none mt-1 shadow-sm" style="max-height:260px;overflow-y:auto;"></div>
</div>
<script>
(function () {
    // Tangkap script tag saat parsing (dropdown di bawah belum ada di DOM),
    // inisialisasi aktual ditunda sampai DOMContentLoaded.
    const thisScript = document.currentScript;
    function init() {
    const root = thisScript ? thisScript.previousElementSibling : null;
    if (!root || !root.classList.contains('qs-klasifikasi')) return;
    const endpoint = root.dataset.endpoint;
    const sel = {
        program: document.getElementById(root.dataset.program),
        kegiatan: document.getElementById(root.dataset.kegiatan),
        sub: document.getElementById(root.dataset.sub),
        rekening: document.getElementById(root.dataset.rekening) || null,
    };
    // Jika dropdown Program tidak ada di halaman ini, nonaktifkan partial.
    if (!endpoint || !sel.program || !sel.kegiatan || !sel.sub) return;
    const input = root.querySelector('.qs-input');
    const resultsBox = root.querySelector('.qs-results');
    const statusEl = root.querySelector('.qs-status');
    const resetBtn = root.querySelector('.qs-reset');
    let mode = 'sub';
    let timer = null;
    let seq = 0;

    function setStatus(msg, isError) {
        statusEl.textContent = msg || '';
        statusEl.classList.toggle('text-danger', !!isError);
        statusEl.classList.toggle('text-muted', !isError);
    }

    root.querySelectorAll('.qs-mode-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            root.querySelectorAll('.qs-mode-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            mode = btn.dataset.mode;
            hideResults();
            if (input.value.trim().length >= 3) doSearch(input.value.trim());
        });
    });

    function hideResults() {
        resultsBox.classList.add('d-none');
        resultsBox.innerHTML = '';
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 3) { hideResults(); setStatus(q ? 'Ketik min. 3 karakter...' : ''); return; }
        timer = setTimeout(() => doSearch(q), 300);
    });
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideResults(); });
    document.addEventListener('click', (e) => { if (!root.contains(e.target)) hideResults(); });

    function doSearch(q) {
        const mySeq = ++seq;
        setStatus('Mencari...');
        fetch(endpoint + '?q=' + encodeURIComponent(q) + '&mode=' + encodeURIComponent(mode))
            .then(r => r.json())
            .then(data => {
                if (mySeq !== seq) return; // abaikan respons basi
                if (!Array.isArray(data)) throw new Error((data && data.error) || 'Respons tidak valid');
                renderResults(data);
                setStatus(data.length ? (data.length + ' hasil — klik untuk isi otomatis.') : 'Tidak ditemukan — coba kata kunci lain atau isi manual.');
            })
            .catch(() => setStatus('Gagal mencari. Periksa koneksi lalu coba lagi.', true));
    }

    function crumb(row) {
        const p = (row.kode_program || '') + ' ' + (row.nama_program || '');
        const k = (row.kode_kegiatan || '') + ' ' + (row.nama_kegiatan || '');
        const s = (row.kode_sub_kegiatan || '') + ' ' + (row.nama_sub_kegiatan || '');
        return mode === 'rekening'
            ? p + ' > ' + k + ' > ' + s
            : p + ' > ' + k;
    }

    function mainLabel(row) {
        return mode === 'rekening'
            ? ((row.kode_rekening || '') + ' - ' + (row.nama_rekening || ''))
            : ((row.kode_sub_kegiatan || '') + ' - ' + (row.nama_sub_kegiatan || ''));
    }

    function renderResults(rows) {
        resultsBox.innerHTML = '';
        if (!rows.length) { hideResults(); return; }
        rows.forEach(row => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action qs-item text-start';
            const main = document.createElement('div');
            main.className = 'fw-semibold';
            main.style.fontSize = '.85rem';
            main.textContent = mainLabel(row);
            const sub = document.createElement('div');
            sub.className = 'qs-crumb';
            sub.textContent = crumb(row);
            btn.appendChild(main);
            btn.appendChild(sub);
            btn.addEventListener('click', () => { hideResults(); autoFill(row); });
            resultsBox.appendChild(btn);
        });
        resultsBox.classList.remove('d-none');
    }

    // Tunggu dropdown anak terisi oleh handler cascade yang sudah ada (polling).
    function waitReady(select, value, timeoutMs) {
        timeoutMs = timeoutMs || 8000;
        return new Promise((resolve, reject) => {
            const t0 = Date.now();
            (function poll() {
                const hasOption = select && Array.from(select.options).some(o => String(o.value) === String(value));
                if (select && !select.disabled && select.options.length > 1 && hasOption) return resolve();
                if (Date.now() - t0 > timeoutMs) return reject(new Error('Dropdown tidak merespons (nilai tidak ditemukan).'));
                setTimeout(poll, 100);
            })();
        });
    }
    const sleep = ms => new Promise(r => setTimeout(r, ms));

    // Isi satu level memakai event change yang sama seperti pilihan manual.
    async function fillLevel(select, value) {
        if (!select) return;
        select.value = value;
        select.dispatchEvent(new Event('change'));
        await waitReady(select, value);
        // Pastikan nilai tidak tertimpa restore ganda: tegaskan sekali lagi.
        await sleep(150);
        if (String(select.value) !== String(value)) {
            select.value = value;
            select.dispatchEvent(new Event('change'));
            await waitReady(select, value);
        }
    }

    async function autoFill(row) {
        setStatus('Mengisi dropdown...');
        try {
            const prog = sel.program, keg = sel.kegiatan, sub = sel.sub, rek = sel.rekening;
            prog.value = row.program_id;
            prog.dispatchEvent(new Event('change'));
            await waitReady(keg, row.kegiatan_id);
            await fillLevel(keg, row.kegiatan_id);
            await waitReady(sub, row.sub_kegiatan_id);
            await fillLevel(sub, row.sub_kegiatan_id);
            if (rek && row.rekening_id) {
                await waitReady(rek, row.rekening_id);
                await fillLevel(rek, row.rekening_id);
            }
            // Verifikasi akhir (ulangi sekali bila balapan restore edit-mode)
            const targets = [[prog, row.program_id], [keg, row.kegiatan_id], [sub, row.sub_kegiatan_id]];
            if (rek && row.rekening_id) targets.push([rek, row.rekening_id]);
            const ok = targets.every(([s, v]) => String(s.value) === String(v));
            if (!ok) throw new Error('Isian belum sinkron, coba klik hasil lagi.');
            input.value = mainLabel(row);
            flash([prog, keg, sub].concat(rek && row.rekening_id ? [rek] : []));
            prog.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setStatus('Terisi otomatis dari pencarian.');
        } catch (err) {
            setStatus(err.message || 'Gagal mengisi otomatis.', true);
        }
    }

    function flash(selects) {
        selects.forEach(s => s && s.classList.add('qs-flash'));
        setTimeout(() => selects.forEach(s => s && s.classList.remove('qs-flash')), 1800);
    }

    resetBtn.addEventListener('click', () => {
        input.value = '';
        hideResults();
        setStatus('');
        // Kembalikan dropdown via event yang sama seperti pilihan manual:
        // mengosongkan Program membuat handler cascade mereset anak-anaknya.
        sel.program.value = '';
        sel.program.dispatchEvent(new Event('change'));
        sel.program.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    } // end init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
