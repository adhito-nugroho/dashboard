<?php
/**
 * Modal isian pejabat instansi tujuan + cetak lembar 2 SPPD.
 * Pemicu: elemen dengan atribut data-sppd2-st & data-sppd2-nip,
 * atau panggil openSppd2(stId, nip) dari JS.
 */
$sppd2Base = rtrim(base_url(), '/') . '/';
?>
<div class="modal fade" id="sppd2Modal" tabindex="-1" aria-labelledby="sppd2ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="sppd2Form" method="POST" target="_blank" action="#" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold" id="sppd2ModalLabel">
                        <i class="bi bi-file-earmark-plus me-1"></i>Cetak Lembar 2 SPPD
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3" id="sppd2Status">Memuat data pejabat&hellip;</div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="sppd2Jabatan">Jabatan pejabat instansi tujuan</label>
                        <input type="text" class="form-control form-control-sm" id="sppd2Jabatan" name="jabatan"
                               maxlength="255" placeholder="mis. Kepala Kantor Regional II BKN" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="sppd2Nama">Nama pejabat</label>
                        <input type="text" class="form-control form-control-sm" id="sppd2Nama" name="nama"
                               maxlength="255" placeholder="Nama lengkap (dengan gelar bila ada)" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold" for="sppd2Nip">NIP</label>
                        <input type="text" class="form-control form-control-sm" id="sppd2Nip" name="nip"
                               maxlength="30" inputmode="numeric" placeholder="18 digit NIP" required>
                    </div>
                    <div class="form-text">
                        Nama &amp; NIP dicetak rata kiri. Isian terakhir tersimpan per Surat Tugas dan
                        akan muncul lagi saat cetak berikutnya.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="sppd2Submit" disabled>
                        <i class="bi bi-printer me-1"></i>Cetak / Pratinjau PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const base = <?= json_encode($sppd2Base) ?>;
    const modalEl = document.getElementById('sppd2Modal');
    const form = document.getElementById('sppd2Form');
    const statusEl = document.getElementById('sppd2Status');
    const btn = document.getElementById('sppd2Submit');
    const inJabatan = document.getElementById('sppd2Jabatan');
    const inNama = document.getElementById('sppd2Nama');
    const inNip = document.getElementById('sppd2Nip');
    let seq = 0;

    window.openSppd2 = function (stId, nip) {
        const token = ++seq;
        form.action = base + 'spj/sppd2/cetak/' + encodeURIComponent(stId) + '/' + encodeURIComponent(nip);
        inJabatan.value = '';
        inNama.value = '';
        inNip.value = '';
        btn.disabled = true;
        statusEl.textContent = 'Memuat data pejabat…';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        fetch(base + 'spj/sppd2/pejabat/' + encodeURIComponent(stId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (token !== seq) return;
                if (!d || !d.ok) throw new Error('bad');
                inJabatan.value = d.jabatan || '';
                inNama.value = d.nama || '';
                inNip.value = d.nip || '';
                const ada = !!(d.jabatan || d.nama || d.nip);
                statusEl.textContent = ada
                    ? 'Isian terakhir dimuat. Ubah bila perlu, lalu cetak.'
                    : 'Belum ada isian tersimpan. Lengkapi jabatan, nama, dan NIP pejabat tujuan.';
                btn.disabled = false;
                inJabatan.focus();
            })
            .catch(function () {
                if (token !== seq) return;
                statusEl.textContent = 'Gagal memuat isian tersimpan. Lengkapi manual lalu cetak.';
                btn.disabled = false;
                inJabatan.focus();
            });
    };

    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-sppd2-st]');
        if (!trigger) return;
        e.preventDefault();
        window.openSppd2(trigger.getAttribute('data-sppd2-st'), trigger.getAttribute('data-sppd2-nip') || '');
    });

    inNip.addEventListener('input', function () {
        inNip.value = inNip.value.replace(/\D+/g, '');
    });

    form.addEventListener('submit', function () {
        const inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
    });
})();
</script>
