<?php
/**
 * Modal isian pejabat instansi tujuan + cetak lembar 2 SPPD.
 * Pemicu: elemen dengan atribut data-sppd2-st & data-sppd2-nip,
 * atau panggil openSppd2(stId, nip) dari JS.
 */
$sppd2Base = rtrim(base_url(), '/') . '/';
$sppd2Elements = \App\Services\SppdLembar2Service::elementOptions();
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
                        <label class="form-label small fw-semibold" for="sppd2Nip">NIP (opsional)</label>
                        <input type="text" class="form-control form-control-sm" id="sppd2Nip" name="nip"
                               maxlength="30" inputmode="numeric" placeholder="18 digit NIP (kosongkan bila tidak ada)">
                    </div>
                    <div class="form-text">
                        Nama &amp; NIP dicetak rata kiri. Bila NIP dikosongkan, dianggap pejabat tidak
                        memiliki NIP sehingga tulisan &ldquo;NIP&rdquo; tidak dicetak. Isian terakhir tersimpan
                        per Surat Tugas dan akan muncul lagi saat cetak berikutnya.
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-semibold mb-0">Komponen yang dicetak</label>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="sppd2SelectAll" checked>
                                <label class="form-check-label small" for="sppd2SelectAll">Pilih semua</label>
                            </div>
                        </div>
                        <div class="border rounded p-2" style="max-height:180px;overflow-y:auto;">
                            <?php foreach ($sppd2Elements as $key => $label): ?>
                                <?php $elId = 'sppd2el_' . preg_replace('/[^a-z0-9_]/i', '_', (string) $key); ?>
                                <div class="form-check">
                                    <input class="form-check-input sppd2-el" type="checkbox"
                                           id="<?= $elId ?>" name="cetak_elemen[]"
                                           value="<?= htmlspecialchars((string) $key, ENT_QUOTES) ?>" checked>
                                    <label class="form-check-label small" for="<?= $elId ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="cetak_elemen_present" value="1">
                        <div class="form-text">Hilangkan centang komponen yang tidak ingin dicetak.</div>
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

    const selectAll = document.getElementById('sppd2SelectAll');
    const elChecks = Array.prototype.slice.call(form.querySelectorAll('.sppd2-el'));

    function syncSelectAll() {
        const checked = elChecks.filter(function (c) { return c.checked; }).length;
        selectAll.checked = checked === elChecks.length;
        selectAll.indeterminate = checked > 0 && checked < elChecks.length;
    }
    selectAll.addEventListener('change', function () {
        elChecks.forEach(function (c) { c.checked = selectAll.checked; });
        syncSelectAll();
    });
    elChecks.forEach(function (c) { c.addEventListener('change', syncSelectAll); });
    syncSelectAll();

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
                    : 'Belum ada isian tersimpan. Lengkapi jabatan dan nama pejabat tujuan (NIP opsional).';
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
