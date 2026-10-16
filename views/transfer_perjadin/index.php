<?php
$flash = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$configMap = $configMap ?? [];
$namaSuggestions = $namaSuggestions ?? [];
$seksiList = $seksiList ?? [];

$namaBulanMap = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];

$totalNilai = 0.0;
$totalPotongan = 0.0;
$configuredCount = 0;
foreach ($transaksiList as $t) {
    $totalNilai += (float) $t['nilai'];
    $cfg = $configMap[(int) $t['id']] ?? null;
    $totalPotongan += (float) ($cfg['potongan'] ?? 0);
}
foreach ($configMap as $c) {
    if (trim((string) ($c['transfer_ke'] ?? '')) !== '' || (float) ($c['potongan'] ?? 0) > 0) {
        $configuredCount++;
    }
}
$totalBersih = $totalNilai - $totalPotongan;
$tahunVal = (string) ($tahun ?? date('Y'));
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
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-arrow-left-right text-primary me-2"></i>Transfer Perjalanan Dinas
            </h1>
            <p class="text-muted small mb-0">
                Berbasis transaksi perjalanan dinas. Isi <strong>Transfer Ke</strong> dan <strong>Potongan</strong> per transaksi,
                lalu unduh Excel (sheet Detail + Rekap pivot per Transfer Ke).
            </p>
        </div>
        <form method="GET" action="<?= base_url('transfer-perjadin/export') ?>" class="d-flex align-items-center gap-2">
            <input type="number" name="tahun" class="form-control form-control-sm border" style="width:96px;" value="<?= htmlspecialchars($tahunVal) ?>" placeholder="Tahun" title="Filter tahun (kosongkan = semua)">
            <button type="submit" class="btn btn-sm btn-success px-3" <?= $configuredCount === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
            </button>
        </form>
    </div>

    <div class="alert alert-light border shadow-sm rounded-3 py-2 px-3 mb-4 d-flex align-items-center gap-2 flex-wrap" style="font-size:0.85rem;">
        <i class="bi bi-info-circle-fill text-primary"></i>
        <span>Transaksi perjalanan dinas: <strong><?= count($transaksiList) ?></strong></span>
        <span class="text-muted">·</span>
        <span>Sudah dikonfigurasi transfer: <strong><?= $configuredCount ?> transaksi</strong></span>
        <span class="text-muted">·</span>
        <span>Total penerimaan: <strong class="font-monospace">Rp <?= number_format($totalNilai, 0, ',', '.') ?></strong></span>
    </div>

    <form method="GET" action="<?= base_url('transfer-perjadin') ?>" class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small text-muted mb-1">Tahun</label>
                <input type="number" name="tahun" class="form-control form-control-sm" style="width:110px;" value="<?= htmlspecialchars($tahunVal) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Bulan</label>
                <select name="bulan" class="form-select form-select-sm" style="width:150px;">
                    <option value="">Semua</option>
                    <?php foreach ($namaBulanMap as $num => $nm): ?>
                        <option value="<?= $num ?>" <?= ((int) ($bulan ?? 0) === $num) ? 'selected' : '' ?>><?= $nm ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Seksi</label>
                <select name="seksi_id" class="form-select form-select-sm" style="width:220px;">
                    <option value="">Semua</option>
                    <?php foreach ($seksiList as $sx): ?>
                        <option value="<?= (int) $sx['id'] ?>" <?= ((int) ($seksiId ?? 0) === (int) $sx['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($sx['kode_seksi'] ?? '') . ' - ' . ($sx['nama_seksi'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-grow-1" style="min-width:180px;">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($q ?? '')) ?>" placeholder="Uraian / nama / nomor ST">
            </div>
            <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-filter me-1"></i>Tampilkan</button>
            <a href="<?= base_url('transfer-perjadin') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <form method="POST" action="<?= base_url('transfer-perjadin/save') ?>" id="formTransferPerjadin">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="bi bi-list-check text-success me-2"></i>Daftar Transaksi Perjalanan Dinas</h5>
                    <small class="text-muted">Transfer Ke diisi nama penerima transfer; Potongan memotong nilai transaksi.</small>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" id="badgeBersih">
                    Total bersih: Rp <?= number_format($totalBersih, 0, ',', '.') ?>
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($transaksiList)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                        Tidak ada transaksi perjalanan dinas pada filter ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="tabelTransaksi">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Tanggal</th>
                                    <th style="min-width:280px;">Uraian</th>
                                    <th style="min-width:180px;">Nama Penerima</th>
                                    <th>Seksi</th>
                                    <th class="text-end">Nilai</th>
                                    <th style="min-width:220px;">Transfer Ke</th>
                                    <th class="text-end" style="min-width:150px;">Potongan</th>
                                    <th class="text-end pe-4">Penerimaan Bersih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transaksiList as $t):
                                    $tid = (int) $t['id'];
                                    $nilai = (float) $t['nilai'];
                                    $cfg = $configMap[$tid] ?? null;
                                    $transferKe = (string) ($cfg['transfer_ke'] ?? '');
                                    $potongan = (float) ($cfg['potongan'] ?? 0);
                                    $isConfigured = $transferKe !== '' || $potongan > 0;
                                    ?>
                                    <tr data-nilai="<?= $nilai ?>" class="<?= $isConfigured ? 'table-success-subtle' : '' ?>">
                                        <td class="ps-4 text-muted small text-nowrap"><?= htmlspecialchars((string) ($t['tanggal'] ?? '-')) ?></td>
                                        <td>
                                            <div class="text-dark" style="font-size:.85rem;white-space:pre-wrap;"><?= htmlspecialchars((string) ($t['uraian'] ?? '-')) ?></div>
                                            <?php if (!empty($t['nomor_surat_tugas'])): ?>
                                                <div class="text-muted font-monospace" style="font-size:.7rem;">ST: <?= htmlspecialchars((string) $t['nomor_surat_tugas']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-dark"><?= htmlspecialchars((string) ($t['nama_penerima'] ?? '-')) ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars((string) ($t['kode_seksi'] ?? '-')) ?></td>
                                        <td class="text-end font-monospace">Rp <?= number_format($nilai, 0, ',', '.') ?></td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm input-transfer-ke"
                                                name="transfer_ke[<?= $tid ?>]" list="namaTransferKe"
                                                value="<?= htmlspecialchars($transferKe) ?>" placeholder="Nama penerima transfer">
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm justify-content-end" style="max-width:150px;margin-left:auto;">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" inputmode="numeric" class="form-control text-end input-potongan"
                                                    name="potongan[<?= $tid ?>]"
                                                    value="<?= $potongan > 0 ? number_format($potongan, 0, ',', '.') : '' ?>"
                                                    placeholder="0">
                                            </div>
                                        </td>
                                        <td class="text-end pe-4 font-monospace fw-semibold text-dark cell-bersih">Rp <?= number_format($nilai - $potongan, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td class="ps-4" colspan="4">TOTAL</td>
                                    <td class="text-end font-monospace" id="totalNilai">Rp <?= number_format($totalNilai, 0, ',', '.') ?></td>
                                    <td></td>
                                    <td class="text-end font-monospace" id="totalPotongan">Rp <?= number_format($totalPotongan, 0, ',', '.') ?></td>
                                    <td class="text-end pe-4 font-monospace" id="totalBersih">Rp <?= number_format($totalBersih, 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($transaksiList)): ?>
                <div class="card-footer bg-white py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Simpan Konfigurasi</button>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<datalist id="namaTransferKe">
    <?php foreach ($namaSuggestions as $nama): ?>
        <option value="<?= htmlspecialchars($nama, ENT_QUOTES) ?>"></option>
    <?php endforeach; ?>
</datalist>

<script>
(function () {
    const form = document.getElementById('formTransferPerjadin');
    if (!form) return;

    const rp = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    const parseAngka = (s) => {
        const d = (s || '').replace(/[^0-9]/g, '');
        return d === '' ? 0 : parseInt(d, 10);
    };

    function recalc() {
        let totalNilai = 0, totalPotongan = 0, totalBersih = 0;
        form.querySelectorAll('#tabelTransaksi tbody tr').forEach((tr) => {
            const nilai = parseFloat(tr.dataset.nilai || '0');
            const input = tr.querySelector('.input-potongan');
            const potongan = parseAngka(input ? input.value : '');
            const cell = tr.querySelector('.cell-bersih');
            if (cell) cell.textContent = rp(nilai - potongan);
            totalNilai += nilai;
            totalPotongan += potongan;
            totalBersih += (nilai - potongan);
        });
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('totalNilai', rp(totalNilai));
        set('totalPotongan', rp(totalPotongan));
        set('totalBersih', rp(totalBersih));
        set('badgeBersih', 'Total bersih: ' + rp(totalBersih));
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
