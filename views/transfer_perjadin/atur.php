<?php
$flash = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$stNomor = (string) ($st['nomor_surat'] ?? '');
$totalPenerimaan = 0.0;
$totalPotongan = 0.0;
foreach ($pegawaiList as $p) {
    $totalPenerimaan += (float) $p['penerimaan'];
    $totalPotongan += (float) $p['potongan'];
}
$totalBersih = $totalPenerimaan - $totalPotongan;
?>
<div class="container-fluid px-4 py-3">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb" class="mb-1">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= base_url('transfer-perjadin') ?>" class="text-decoration-none">Transfer Perjalanan Dinas</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Atur</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold text-dark mb-1">Atur Transfer — <?= htmlspecialchars($stNomor) ?></h1>
            <p class="text-muted small mb-0">
                <?= htmlspecialchars((string) ($st['untuk'] ?? '-')) ?>
                <span class="text-muted">·</span>
                <?= htmlspecialchars((string) ($st['tanggal_mulai'] ?? '')) ?>
            </p>
        </div>
        <a href="<?= base_url('transfer-perjadin') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <form method="POST" action="<?= base_url('transfer-perjadin/save') ?>" id="formTransferPerjadin">
        <input type="hidden" name="surat_tugas_id" value="<?= (int) $st['id'] ?>">

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="bi bi-people text-success me-2"></i>Daftar Pegawai</h5>
                    <small class="text-muted">Pilih satu penerima transfer dan isi potongan tiap pegawai.</small>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" id="badgeBersih">
                    Total bersih: Rp <?= number_format($totalBersih, 0, ',', '.') ?>
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pegawaiList)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                        Surat Tugas ini belum memiliki data pegawai.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="tabelPegawai">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="min-width:260px;">Nama / NIP</th>
                                    <th class="text-end">Penerimaan</th>
                                    <th class="text-center">Transfer Ke</th>
                                    <th class="text-end" style="min-width:160px;">Potongan</th>
                                    <th class="text-end pe-4">Penerimaan Bersih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pegawaiList as $p):
                                    $nip = (string) $p['nip'];
                                    $penerimaan = (float) $p['penerimaan'];
                                    $potongan = (float) $p['potongan'];
                                    ?>
                                    <tr data-penerimaan="<?= $penerimaan ?>">
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars((string) $p['nama']) ?></div>
                                            <div class="text-muted small font-monospace">NIP <?= htmlspecialchars($nip) ?></div>
                                            <?php if (!empty($p['jabatan'])): ?>
                                                <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars((string) $p['jabatan']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end font-monospace">Rp <?= number_format($penerimaan, 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <input type="radio" class="form-check-input" name="transfer_ke" value="<?= htmlspecialchars($nip, ENT_QUOTES) ?>"
                                                <?= !empty($p['is_transfer_ke']) ? 'checked' : '' ?>
                                                title="Tandai pegawai ini sebagai penerima transfer">
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm justify-content-end" style="max-width:170px;margin-left:auto;">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" inputmode="numeric" class="form-control text-end input-potongan"
                                                    name="potongan[<?= htmlspecialchars($nip, ENT_QUOTES) ?>]"
                                                    value="<?= $potongan > 0 ? number_format($potongan, 0, ',', '.') : '' ?>"
                                                    placeholder="0">
                                            </div>
                                        </td>
                                        <td class="text-end pe-4 font-monospace fw-semibold text-dark cell-bersih">Rp <?= number_format($penerimaan - $potongan, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td class="ps-4">TOTAL</td>
                                    <td class="text-end font-monospace" id="totalPenerimaan">Rp <?= number_format($totalPenerimaan, 0, ',', '.') ?></td>
                                    <td></td>
                                    <td class="text-end font-monospace" id="totalPotongan">Rp <?= number_format($totalPotongan, 0, ',', '.') ?></td>
                                    <td class="text-end pe-4 font-monospace" id="totalBersih">Rp <?= number_format($totalBersih, 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($pegawaiList)): ?>
                <div class="card-footer bg-white py-3 px-4 d-flex justify-content-end gap-2">
                    <a href="<?= base_url('transfer-perjadin') ?>" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Simpan Konfigurasi</button>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('formTransferPerjadin');
    if (!form) return;

    const rp = (n) => 'Rp ' + Math.max(0, Math.round(n)).toLocaleString('id-ID');

    function parseAngka(s) {
        s = (s || '').replace(/[^0-9]/g, '');
        return s === '' ? 0 : parseInt(s, 10);
    }

    function recalc() {
        let totalPenerimaan = 0, totalPotongan = 0, totalBersih = 0;
        form.querySelectorAll('#tabelPegawai tbody tr').forEach((tr) => {
            const penerimaan = parseFloat(tr.dataset.penerimaan || '0');
            const input = tr.querySelector('.input-potongan');
            const potongan = parseAngka(input ? input.value : '');
            const bersih = penerimaan - potongan;
            const cell = tr.querySelector('.cell-bersih');
            if (cell) cell.textContent = rp(bersih);
            totalPenerimaan += penerimaan;
            totalPotongan += potongan;
            totalBersih += bersih;
        });
        const el = (id) => document.getElementById(id);
        if (el('totalPenerimaan')) el('totalPenerimaan').textContent = rp(totalPenerimaan);
        if (el('totalPotongan')) el('totalPotongan').textContent = rp(totalPotongan);
        if (el('totalBersih')) el('totalBersih').textContent = rp(totalBersih);
        if (el('badgeBersih')) el('badgeBersih').textContent = 'Total bersih: ' + rp(totalBersih);
    }

    form.querySelectorAll('.input-potongan').forEach((input) => {
        input.addEventListener('input', function () {
            const digits = this.value.replace(/[^0-9]/g, '');
            this.value = digits === '' ? '' : Number(digits).toLocaleString('id-ID');
            recalc();
        });
    });

    recalc();
})();
</script>
