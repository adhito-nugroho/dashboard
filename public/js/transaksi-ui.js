/**
 * transaksi-ui.js — helper UI bersama halaman transaksi (admin & seksi).
 * - Masking Rupiah otomatis: input[data-rupiah]
 * - Normalisasi ke integer murni saat submit: form[data-rupiah-normalize]
 *   (via event 'formdata' agar tidak mengganggu validasi/confirm pembatalan)
 * - Expand/collapse teks uraian: [data-uraian-expand] + target clamp
 */
(function () {
    'use strict';

    function digitsOnly(str) {
        return String(str == null ? '' : str).replace(/[^\d]/g, '');
    }

    function formatRibuan(digits) {
        if (!digits) return '';
        // Hindari parseInt agar aman untuk angka sangat besar
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Masking dengan preservasi posisi kursor (digit di kiri kursor tetap sama)
    function maskRupiah(el) {
        var digits = digitsOnly(el.value).replace(/^0+(?=\d)/, '');
        var before = digitsOnly(el.value.slice(0, el.selectionStart || 0)).replace(/^0+(?=\d)/, '');
        el.value = formatRibuan(digits);
        if (document.activeElement === el && typeof el.setSelectionRange === 'function') {
            var pos = 0, seen = 0;
            var target = before.length;
            while (pos < el.value.length && seen < target) {
                if (/\d/.test(el.value[pos])) seen++;
                pos++;
            }
            try { el.setSelectionRange(pos, pos); } catch (e) { /* abaikan */ }
        }
    }

    // Nilai awal dari DB bisa berbentuk desimal mentah ("4880000.00"). Titik
    // desimal harus dibuang bersama pecahannya, bukan diperlakukan sebagai
    // pemisah ribuan (kalau salah, nilai tampil 100x lipat).
    function integerFromValue(str) {
        var s = String(str == null ? '' : str).trim();
        if (/^\d+\.\d{1,2}$/.test(s)) s = s.split('.')[0];
        return digitsOnly(s).replace(/^0+(?=\d)/, '');
    }

    function initRupiah(scope) {
        (scope || document).querySelectorAll('input[data-rupiah]').forEach(function (el) {
            if (el.dataset.rupiahInit) return;
            el.dataset.rupiahInit = '1';
            el.setAttribute('inputmode', 'numeric');
            if (el.value) el.value = formatRibuan(integerFromValue(el.value));
            el.addEventListener('input', function () { maskRupiah(el); });
        });
    }

    // Normalisasi via event 'formdata': hanya berjalan saat form BENAR-BENAR disubmit
    // (validasi/confirm yang membatalkan submit tidak memicu ini).
    function initNormalize(scope) {
        (scope || document).querySelectorAll('form[data-rupiah-normalize]').forEach(function (form) {
            if (form.dataset.rupiahNormalizeInit) return;
            form.dataset.rupiahNormalizeInit = '1';
            form.addEventListener('formdata', function (e) {
                form.querySelectorAll('input[data-rupiah]').forEach(function (el) {
                    if (!el.name || el.disabled) return;
                    e.formData.set(el.name, digitsOnly(el.value));
                });
            });
        });
    }

    // Tombol "Selengkapnya": toggle class clamp pada target.
    // <button data-uraian-expand data-uraian-target="idTarget">Selengkapnya</button>
    // Target memakai .ux-clamp-2/.ux-clamp-3 (+ .uraian-clamp lama).
    function initUraianExpand(scope) {
        (scope || document).querySelectorAll('[data-uraian-expand]').forEach(function (btn) {
            if (btn.dataset.uraianExpandInit) return;
            btn.dataset.uraianExpandInit = '1';
            var labelOpen = btn.dataset.labelOpen || 'Selengkapnya';
            var labelClose = btn.dataset.labelClose || 'Tutup';
            btn.addEventListener('click', function () {
                var target = null;
                if (btn.dataset.uraianTarget) {
                    target = document.getElementById(btn.dataset.uraianTarget);
                }
                if (!target) {
                    target = btn.closest('td, .uraian-wrap') || document;
                    target = target.querySelector('.ux-clamp-2, .ux-clamp-3, .uraian-clamp');
                }
                if (!target) return;
                var opened = target.classList.toggle('ux-clamp-off');
                // Paksa via inline style agar selalu menang dari CSS clamp
                // (class .uraian-clamp inline di view punya spesifisitas/urutan lebih kuat).
                if (opened) {
                    target.style.display = 'block';
                    target.style.overflow = 'visible';
                    target.style.maxHeight = 'none';
                } else {
                    target.style.display = '';
                    target.style.overflow = '';
                    target.style.maxHeight = '';
                }
                var labelEl = btn.querySelector('[data-uraian-label]') || btn;
                var html = opened ? labelClose : labelOpen;
                if (labelEl === btn && !btn.querySelector('[data-uraian-label]')) {
                    // Pertahankan ikon bila ada
                    var icon = btn.querySelector('i');
                    btn.textContent = html;
                    if (icon) btn.prepend(icon);
                } else {
                    labelEl.textContent = html;
                }
                btn.setAttribute('aria-expanded', opened ? 'true' : 'false');
            });
        });
    }

    // Switch LS: tegaskan state aktif via class is-on pada wrapper.
    function initSwitchLs(scope) {
        (scope || document).querySelectorAll('.switch-ls input[type="checkbox"]').forEach(function (el) {
            if (el.dataset.switchLsInit) return;
            el.dataset.switchLsInit = '1';
            var wrap = el.closest('.switch-ls');
            var sync = function () { if (wrap) wrap.classList.toggle('is-on', el.checked); };
            el.addEventListener('change', sync);
            sync();
        });
    }

    function initAll(scope) {
        initRupiah(scope);
        initNormalize(scope);
        initUraianExpand(scope);
        initSwitchLs(scope);
    }

    window.TrxUI = {
        digitsOnly: digitsOnly,
        formatRibuan: formatRibuan,
        maskRupiah: maskRupiah,
        initAll: initAll
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(document); });
    } else {
        initAll(document);
    }
})();
